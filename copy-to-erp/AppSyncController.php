<?php

namespace App\Http\Controllers;

use App\Models\Carro;
use App\Models\ChecklistSection;
use App\Models\Funcionarios;
use App\Models\Ordem;
use App\Models\OrdensHistorico;
use App\Models\User;
use App\Services\MobileCommissionIndicationService;
use App\Services\OrdemSeparacaoSelectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AppSyncController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();
            // 0. Fetch the active employee (emails can be reused by different people)
            $funcionario = Funcionarios::where('Email', $request->email)->whereNull('Data demissão')->first()
                ?? Funcionarios::where('Email', $request->email)->first();

            // Fallback: If no employee found by email, try matching by nomepgi or user name
            if (! $funcionario && $user) {
                // Try nomepgi first, then fall back to the user's name
                $identifier = ! empty($user->nomepgi) ? $user->nomepgi : $user->name;

                if (! empty($identifier)) {
                    $funcionario = Funcionarios::where(function ($q) use ($identifier) {
                        $q->where('Nome conhecido', $identifier)
                            ->orWhere('Nome funcionario', $identifier);
                    })->whereNull('Data demissão')->first()
                        ?? Funcionarios::where(function ($q) use ($identifier) {
                            $q->where('Nome conhecido', $identifier)
                                ->orWhere('Nome funcionario', $identifier);
                        })->first();
                }
            }

            // 1. Check if user exists and password is correct
            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Credenciais inválidas (User not found or password mismatch)',
                ], 401);
            }

            // 2. Strict Verification: Check if linked employee exists
            if (! $funcionario) {
                return response()->json([
                    'message' => 'Usuário sem registro de funcionário vinculado.',
                ], 403);
            }

            // 3. Strict Verification: Check if employee is strictly active (NOT fired)
            $dataDemissao = $funcionario->{'Data demissão'};
            if ($dataDemissao !== null) {
                return response()->json([
                    'message' => 'Usuário desativado ou demitido.',
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'funcionario' => $funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'} ?? '',
            ]);
        } catch (\Throwable $e) {
            // FALLBACK FOR DEV ENVIRONMENT WITHOUT SQL SERVER DRIVER
            // STRICT MOCK: Only allow specific credentials to simulate real security.
            if (str_contains($e->getMessage(), 'could not find driver') || str_contains($e->getMessage(), 'Connection refused')) {

                // MOCK CREDENTIALS CHECK
                if ($request->email === 'teste@tecnoar.com.br' && $request->password === '123456') {
                    return response()->json([
                        'access_token' => 'mock-token-for-dev',
                        'token_type' => 'Bearer',
                        'user' => [
                            'id' => 1,
                            'name' => 'Renato Técnico (Modo Offline/Sem Driver)',
                            'email' => $request->email,
                        ],
                        'funcionario' => 'Renato Técnico',
                    ]);
                } else {
                    // Simulate Invalid Credentials even in Mock Mode
                    return response()->json([
                        'message' => "Credenciais inválidas. Esperado: teste@tecnoar.com.br / 123456. Recebido: {$request->email} / {$request->password}",
                    ], 401);
                }
            }

            return response()->json([
                'message' => 'Server Error: '.$e->getMessage(),
            ], 500);
        }
    }

    private function resolveAuthenticatedFuncionario(?User $user): ?Funcionarios
    {
        if (! $user) {
            return null;
        }

        $funcionario = Funcionarios::where('Email', $user->email)->whereNull('Data demissão')->first()
            ?? Funcionarios::where('Email', $user->email)->first();

        if ($funcionario) {
            return $funcionario;
        }

        $identifier = ! empty($user->nomepgi) ? $user->nomepgi : $user->name;

        if (empty($identifier)) {
            return null;
        }

        return Funcionarios::where(function ($q) use ($identifier) {
            $q->where('Nome conhecido', $identifier)
                ->orWhere('Nome funcionario', $identifier);
        })->whereNull('Data demissão')->first()
            ?? Funcionarios::where(function ($q) use ($identifier) {
                $q->where('Nome conhecido', $identifier)
                    ->orWhere('Nome funcionario', $identifier);
            })->first();
    }

    private function resolveAuthenticatedFuncionarioName(?User $user): string
    {
        $funcionario = $this->resolveAuthenticatedFuncionario($user);

        return $funcionario
            ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'} ?? 'Mobile App')
            : ($user->name ?? 'Mobile App');
    }

    private function isClientLocalImageReference(?string $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('#^(file|content|ph|asset|assets-library)://#i', $value)) {
            return true;
        }

        return preg_match('#^/(data|storage|mnt|private|var)/#i', $value) === 1;
    }

    private function looksLikeImageReference(?string $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return str_starts_with($value, 'data:image')
            || $this->isClientLocalImageReference($value)
            || preg_match('#(ordem-fotos/|/ordem-fotos/|private-foto\?path=|/assinaturas/|/fotos-adicionais/|/fotos-gerais/)#i', $value) === 1
            || preg_match('#^\d+/(fotos-adicionais|fotos-gerais|assinaturas)/#i', $value) === 1;
    }

    private function normalizeStoredImageReference(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || $this->isClientLocalImageReference($value)) {
            return null;
        }

        if (preg_match('#[?&]path=([^&]+)#', $value, $matches) && str_contains($value, 'private-foto')) {
            $value = rawurldecode($matches[1]);
        }

        $value = str_replace('\\', '/', $value);

        if (preg_match('#(ordem-fotos/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|gif|webp))#i', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('#(\d+/(?:fotos-adicionais|fotos-gerais|assinaturas)/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|gif|webp))#i', $value, $matches)) {
            $value = $matches[1];
        }

        $value = ltrim($value, '/');
        $value = preg_replace('#^private/#i', '', $value);

        if (preg_match('#^ordem-fotos/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|gif|webp)$#i', $value)) {
            return Storage::disk('public')->exists($value) ? $value : null;
        }

        if (preg_match('#^\d+/(fotos-adicionais|fotos-gerais|assinaturas)/[A-Za-z0-9._-]+\.(?:jpg|jpeg|png|gif|webp)$#i', $value)) {
            // Check both local and public storage as mobile uploads might end up in either depending on context
            if (Storage::disk('local')->exists($value)) {
                return $value;
            }
            if (Storage::disk('public')->exists($value)) {
                return $value;
            }
            return null;
        }

        return null;
    }

    private function encodeStoredImageReference($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image')) {
            return $value;
        }

        $normalizedPath = $this->normalizeStoredImageReference($value);
        if ($normalizedPath === null) {
            return str_starts_with($value, 'http') && ! $this->looksLikeImageReference($value) ? $value : null;
        }

        if (str_starts_with($normalizedPath, 'ordem-fotos/')) {
            return $normalizedPath;
        }

        // Removemos o Base64 para otimização de memória
        return url('/api/ordens/private-foto?path=' . urlencode($normalizedPath));
    }

    private function serializeMobilePhotoReference($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image')) {
            return $value;
        }

        return $this->normalizeStoredImageReference($value);
    }

    private function isSignaturePath(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), 'assinaturas/');
    }

    private function collectChecklistPrivatePhotoReferences(array $checklist, string $basePath): array
    {
        $references = [];
        $normalizedBasePath = trim($basePath, '/').'/';

        $pushReference = function ($value) use (&$references, $normalizedBasePath) {
            if (! is_string($value)) {
                return;
            }

            $normalizedPath = $this->normalizeStoredImageReference($value);
            if (! is_string($normalizedPath) || $normalizedPath === '' || str_starts_with($normalizedPath, 'ordem-fotos/')) {
                return;
            }

            if ($this->isSignaturePath($normalizedPath) || ! str_starts_with($normalizedPath, $normalizedBasePath)) {
                return;
            }

            $references[$normalizedPath] = true;
        };

        foreach (($checklist['photos'] ?? []) as $photoReference) {
            $pushReference($photoReference);
        }

        foreach ($checklist as $sectionKey => $sectionValue) {
            if (! is_array($sectionValue) || in_array((string) $sectionKey, ['photos', 'times'], true)) {
                continue;
            }

            foreach ($sectionValue as $itemValue) {
                if (! is_array($itemValue)) {
                    continue;
                }

                foreach (($itemValue['p'] ?? []) as $photoReference) {
                    $pushReference($photoReference);
                }

                $fields = $itemValue['f'] ?? [];
                if (! is_array($fields)) {
                    continue;
                }

                foreach ($fields as $fieldValue) {
                    if (! is_array($fieldValue)) {
                        continue;
                    }

                    foreach (($fieldValue['p'] ?? []) as $photoReference) {
                        $pushReference($photoReference);
                    }
                }
            }
        }

        return array_keys($references);
    }

    private function deleteChecklistPrivatePhotos(array $photoReferences): void
    {
        foreach ($photoReferences as $photoReference) {
            if (! is_string($photoReference)) {
                continue;
            }

            $normalizedPath = $this->normalizeStoredImageReference($photoReference);
            if (! is_string($normalizedPath) || $normalizedPath === '' || str_starts_with($normalizedPath, 'ordem-fotos/')) {
                continue;
            }

            if ($this->isSignaturePath($normalizedPath)) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($normalizedPath)) {
                    Storage::disk('local')->delete($normalizedPath);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('StoreOS: failed to delete removed checklist photo.', [
                    'path' => $normalizedPath,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function normalizeSignatureMatch(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = mb_strtolower($value);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function inferSignatureRole(
        ?string $itemId = null,
        ?string $itemCode = null,
        ?string $itemTitle = null,
        ?string $topLevelKey = null
    ): ?string {
        $candidates = array_filter([
            $topLevelKey,
            $itemTitle,
            $itemCode,
            $itemId,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeSignatureMatch($candidate);
            if ($normalized === '') {
                continue;
            }

            if ($normalized === 'techsignature' || str_contains($normalized, 'tecnico') || in_array($normalized, ['64', '74', '44'], true)) {
                return 'tecnico';
            }

            if ($normalized === 'signature' || str_contains($normalized, 'cliente') || in_array($normalized, ['63', '73', '43'], true)) {
                return 'cliente';
            }
        }

        return null;
    }

    private function buildSignatureTargetMeta(
        ?string $sectionId,
        ?string $itemId,
        ?string $itemCode = null,
        ?string $itemTitle = null
    ): ?array {
        $normalizedSectionId = trim((string) $sectionId);
        $normalizedItemId = trim((string) $itemId);

        if ($normalizedSectionId === '' || $normalizedItemId === '') {
            return null;
        }

        return [
            'sectionId' => $normalizedSectionId,
            'id' => $normalizedItemId,
            'code' => trim((string) $itemCode),
            'title' => trim((string) $itemTitle),
            'role' => $this->inferSignatureRole($normalizedItemId, $itemCode, $itemTitle),
        ];
    }

    private function buildSignatureTargetKey(?array $targetMeta = null, ?string $topLevelKey = null): ?string
    {
        $sectionId = trim((string) ($targetMeta['sectionId'] ?? ''));
        $itemId = trim((string) ($targetMeta['id'] ?? ''));

        if ($sectionId !== '' && $itemId !== '') {
            return "{$sectionId}:{$itemId}";
        }

        $topLevelKey = trim((string) $topLevelKey);

        return $topLevelKey !== '' ? "top:{$topLevelKey}" : null;
    }

    private function extractChecklistSignatureValue(array $checklist, ?array $targetMeta): ?string
    {
        if (! is_array($targetMeta)) {
            return null;
        }

        $sectionId = trim((string) ($targetMeta['sectionId'] ?? ''));
        $itemId = trim((string) ($targetMeta['id'] ?? ''));
        $itemCode = trim((string) ($targetMeta['code'] ?? ''));

        if ($sectionId === '' || ! isset($checklist[$sectionId]) || ! is_array($checklist[$sectionId])) {
            return null;
        }

        $storedValue = $checklist[$sectionId][$itemId] ?? null;
        if ($storedValue === null && $itemCode !== '') {
            $storedValue = $checklist[$sectionId][$itemCode] ?? null;
        }

        if (is_array($storedValue)) {
            $storedValue = $storedValue['v'] ?? null;
        }

        return is_string($storedValue) && trim($storedValue) !== '' ? $storedValue : null;
    }

    private function canonicalSignaturePath(string $basePath, ?array $targetMeta = null, ?string $topLevelKey = null): string
    {
        $normalizedBasePath = trim($basePath, '/');
        $sectionId = trim((string) ($targetMeta['sectionId'] ?? ''));
        $itemId = trim((string) ($targetMeta['id'] ?? ''));

        if ($sectionId !== '' && $itemId !== '') {
            $safeSectionId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $sectionId) ?: 'section';
            $safeItemId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $itemId) ?: 'item';

            return "{$normalizedBasePath}/assinaturas/{$normalizedBasePath}-sec{$safeSectionId}-item{$safeItemId}.jpg";
        }

        $fallbackSuffix = preg_replace('/[^A-Za-z0-9_-]/', '-', trim((string) $topLevelKey)) ?: 'signature';

        return "{$normalizedBasePath}/assinaturas/{$normalizedBasePath}-{$fallbackSuffix}.jpg";
    }

    private function copyLocalImageContents(string $sourcePath, string $targetPath): bool
    {
        if ($sourcePath === $targetPath) {
            return Storage::disk('local')->exists($targetPath);
        }

        if (! Storage::disk('local')->exists($sourcePath)) {
            return false;
        }

        $contents = Storage::disk('local')->get($sourcePath);
        if ($contents === false || $contents === null) {
            return false;
        }

        return $this->replaceLocalImageContents($targetPath, $contents);
    }

    private function replaceLocalImageContents(string $path, string $contents): bool
    {
        try {
            $absolutePath = Storage::disk('local')->path($path);
            $absoluteDirectory = dirname($absolutePath);

            if (! is_dir($absoluteDirectory) && ! @mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
                return false;
            }

            $temporaryPath = "{$absolutePath}.tmp";

            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }

            if (@file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                return false;
            }

            if (file_exists($absolutePath) && ! @unlink($absolutePath)) {
                @unlink($temporaryPath);

                return false;
            }

            if (! @rename($temporaryPath, $absolutePath)) {
                $copied = @copy($temporaryPath, $absolutePath);
                @unlink($temporaryPath);
                if (! $copied) {
                    return false;
                }
            }

            clearstatcache(true, $absolutePath);

            return is_file($absolutePath) && filesize($absolutePath) >= 0;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('StoreOS: failed to atomically replace local image.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function saveCanonicalSignatureImage(
        $rawValue,
        string $basePath,
        ?array $targetMeta = null,
        ?string $topLevelKey = null,
        $fallbackValue = null
    )
    {
        $canonicalPath = $this->canonicalSignaturePath($basePath, $targetMeta, $topLevelKey);
        $normalizedFallback = $this->normalizeStoredImageReference(is_string($fallbackValue) ? $fallbackValue : null);
        $targetKey = $this->buildSignatureTargetKey($targetMeta, $topLevelKey);

        if (! is_string($rawValue)) {
            return $normalizedFallback;
        }

        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return $normalizedFallback;
        }

        if (! str_starts_with($rawValue, 'data:image')) {
            $normalizedReference = $this->normalizeStoredImageReference($rawValue);
            if ($normalizedReference !== null) {
                if ($normalizedReference === $canonicalPath || $normalizedReference === 'private/'.$canonicalPath) {
                    return 'private/'.$canonicalPath;
                }

                if ($this->isSignaturePath($normalizedReference) && $this->copyLocalImageContents($normalizedReference, $canonicalPath)) {
                    return 'private/'.$canonicalPath;
                }

                if (($normalizedFallback === $canonicalPath || $normalizedFallback === 'private/'.$canonicalPath) && Storage::disk('local')->exists($canonicalPath)) {
                    return 'private/'.$canonicalPath;
                }

                return $normalizedReference;
            }

            if ($this->looksLikeImageReference($rawValue)) {
                \Illuminate\Support\Facades\Log::warning("StoreOS: Ignoring invalid signature reference for OS {$basePath}", [
                    'target' => $targetKey,
                    'value' => $rawValue,
                    'fallback' => $normalizedFallback,
                ]);
            }

            return $normalizedFallback;
        }

        $imageParts = explode(',', $rawValue, 2);
        if (count($imageParts) !== 2) {
            \Illuminate\Support\Facades\Log::warning("StoreOS: Invalid base64 signature payload for OS {$basePath}", [
                'target' => $targetKey,
            ]);

            return $rawValue;
        }

        $decoded = base64_decode($imageParts[1], true);
        if ($decoded === false) {
            \Illuminate\Support\Facades\Log::warning("StoreOS: Failed to decode base64 signature for OS {$basePath}", [
                'target' => $targetKey,
            ]);

            return $rawValue;
        }

        $signatureDir = trim($basePath, '/').'/assinaturas';
        if (! Storage::disk('local')->exists($signatureDir)) {
            Storage::disk('local')->makeDirectory($signatureDir);
        }

        $contentsToWrite = $decoded;
        $image = @imagecreatefromstring($decoded);
        if ($image) {
            $width = imagesx($image);
            $height = imagesy($image);
            $whiteImage = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($whiteImage, 255, 255, 255);
            imagefilledrectangle($whiteImage, 0, 0, $width, $height, $white);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            imagecopy($whiteImage, $image, 0, 0, 0, 0, $width, $height);

            ob_start();
            imagejpeg($whiteImage, null, 85);
            $jpegContent = ob_get_clean();
            imagedestroy($image);
            imagedestroy($whiteImage);

            if ($jpegContent !== false) {
                $contentsToWrite = $jpegContent;
            }
        }

        if (! $this->replaceLocalImageContents($canonicalPath, $contentsToWrite)) {
            \Illuminate\Support\Facades\Log::error("StoreOS: Failed to persist canonical signature for OS {$basePath}", [
                'target' => $targetKey,
                'path' => $canonicalPath,
            ]);

            return $rawValue;
        }

        return 'private/'.$canonicalPath;
    }

    private function synchronizeChecklistSignatureReferences(
        array $checklist,
        string $basePath,
        array $signatureItemMetaByAlias,
        array $signatureItemTargetsByTopLevelKey
    ): array {
        $resolvedPathsByTarget = [];
        $rawTopLevelValues = [];

        foreach (['signature', 'techSignature'] as $topLevelKey) {
            if (! isset($checklist[$topLevelKey]) || ! is_string($checklist[$topLevelKey])) {
                continue;
            }

            $targetMeta = $signatureItemTargetsByTopLevelKey[$topLevelKey] ?? null;
            $targetKey = $this->buildSignatureTargetKey($targetMeta, $topLevelKey);
            $savedPath = $this->saveCanonicalSignatureImage(
                $checklist[$topLevelKey],
                $basePath,
                $targetMeta,
                $topLevelKey,
                $targetKey !== null ? ($resolvedPathsByTarget[$targetKey] ?? null) : null
            );
            if (is_string($savedPath) && trim($savedPath) !== '') {
                $checklist[$topLevelKey] = $savedPath;
                $rawTopLevelValues[$topLevelKey] = $savedPath;
                $normalizedPath = $this->normalizeStoredImageReference($savedPath);
                if ($targetKey !== null) {
                    $resolvedPathsByTarget[$targetKey] = $normalizedPath ?? $savedPath;
                }
            }
        }

        foreach ($checklist as $sectionKey => $sectionValue) {
            if (! is_array($sectionValue) || in_array((string) $sectionKey, ['photos', 'times'], true)) {
                continue;
            }

            foreach ($sectionValue as $itemKey => $itemValue) {
                $itemKeyString = trim((string) $itemKey);
                $itemMeta = $signatureItemMetaByAlias[$itemKeyString] ?? null;
                $currentValue = is_array($itemValue) ? ($itemValue['v'] ?? null) : $itemValue;

                if (! is_string($currentValue) || trim($currentValue) === '') {
                    continue;
                }

                if (! is_array($itemMeta)) {
                    $normalizedCurrentValue = $this->normalizeStoredImageReference($currentValue);
                    if (is_string($normalizedCurrentValue) && $this->isSignaturePath($normalizedCurrentValue)) {
                        if (is_array($itemValue)) {
                            $checklist[$sectionKey][$itemKey]['v'] = '';
                        } else {
                            $checklist[$sectionKey][$itemKey] = '';
                        }
                    }

                    continue;
                }

                $targetKey = $this->buildSignatureTargetKey($itemMeta);
                $savedPath = $this->saveCanonicalSignatureImage(
                    $currentValue,
                    $basePath,
                    $itemMeta,
                    null,
                    $targetKey !== null ? ($resolvedPathsByTarget[$targetKey] ?? null) : null
                );
                if (! is_string($savedPath) || trim($savedPath) === '') {
                    continue;
                }

                if (is_array($itemValue)) {
                    $checklist[$sectionKey][$itemKey]['v'] = $savedPath;
                } else {
                    $checklist[$sectionKey][$itemKey] = $savedPath;
                }

                $normalizedPath = $this->normalizeStoredImageReference($savedPath);
                if ($targetKey !== null) {
                    $resolvedPathsByTarget[$targetKey] = $normalizedPath ?? $savedPath;
                }
            }
        }

        foreach (['signature', 'techSignature'] as $topLevelKey) {
            $targetMeta = $signatureItemTargetsByTopLevelKey[$topLevelKey] ?? null;
            $targetKey = $this->buildSignatureTargetKey($targetMeta, $topLevelKey);
            $finalSignatureValue = $targetKey !== null ? ($resolvedPathsByTarget[$targetKey] ?? null) : null;

            if ((! is_string($finalSignatureValue) || $finalSignatureValue === '') && isset($rawTopLevelValues[$topLevelKey])) {
                $finalSignatureValue = $rawTopLevelValues[$topLevelKey];
            }

            if (is_string($finalSignatureValue) && $finalSignatureValue !== '') {
                $checklist[$topLevelKey] = $finalSignatureValue;
            } else {
                unset($checklist[$topLevelKey]);
            }

            if (! is_array($targetMeta) || ! is_string($finalSignatureValue) || $finalSignatureValue === '') {
                continue;
            }

            $sectionId = trim((string) ($targetMeta['sectionId'] ?? ''));
            $itemId = trim((string) ($targetMeta['id'] ?? ''));
            $itemCode = trim((string) ($targetMeta['code'] ?? ''));

            if ($sectionId === '' || $itemId === '') {
                continue;
            }

            if (! isset($checklist[$sectionId]) || ! is_array($checklist[$sectionId])) {
                $checklist[$sectionId] = [];
            }

            if ($itemCode !== '' && $itemCode !== $itemId && isset($checklist[$sectionId][$itemCode])) {
                unset($checklist[$sectionId][$itemCode]);
            }

            $existingValue = $checklist[$sectionId][$itemId] ?? null;
            if (is_array($existingValue)) {
                $checklist[$sectionId][$itemId]['v'] = $finalSignatureValue;
            } else {
                $checklist[$sectionId][$itemId] = $finalSignatureValue;
            }
        }

        return [
            'checklist' => $checklist,
            'paths' => array_values(array_unique(array_filter(
                $resolvedPathsByTarget,
                fn ($value) => is_string($value) && trim($value) !== ''
            ))),
        ];
    }

    private function deleteChecklistSignatureFilesExcept(string $basePath, array $keepPaths): void
    {
        $signatureDir = trim($basePath, '/').'/assinaturas';
        $keepSet = [];

        foreach ($keepPaths as $keepPath) {
            $normalizedPath = $this->normalizeStoredImageReference(is_string($keepPath) ? $keepPath : null);
            if (! is_string($normalizedPath) || $normalizedPath === '' || ! $this->isSignaturePath($normalizedPath)) {
                continue;
            }

            if (! str_starts_with($normalizedPath, trim($basePath, '/').'/')) {
                continue;
            }

            $keepSet[$normalizedPath] = true;
        }

        try {
            $signatureFiles = Storage::disk('local')->files($signatureDir);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('StoreOS: failed to list signature directory for cleanup.', [
                'directory' => $signatureDir,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($signatureFiles as $signatureFile) {
            $normalizedPath = $this->normalizeStoredImageReference($signatureFile);
            if (! is_string($normalizedPath) || $normalizedPath === '' || isset($keepSet[$normalizedPath])) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($normalizedPath)) {
                    Storage::disk('local')->delete($normalizedPath);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('StoreOS: failed to delete extra signature file.', [
                    'path' => $normalizedPath,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function putLocalImageContents(string $path, string $contents): bool
    {
        $written = Storage::disk('local')->put($path, $contents);

        return $written !== false && Storage::disk('local')->exists($path);
    }

    private function buildCreatedMobileOsSummary(Ordem $ordem): array
    {
        $cliente = $ordem->clienteByCodigo;
        $ordemApi = $ordem->ordem_api;
        $phone = '';

        if ($cliente && $cliente->telefones && $cliente->telefones->isNotEmpty()) {
            $phone = $cliente->telefones->first()->Fone ?? '';
        }

        if ($phone === '') {
            $phone = trim((string) ($ordem->Fone ?? ''))
                ?: trim((string) ($cliente->Telefone1 ?? $cliente->{'Celular boleto'} ?? ''));
        }

        $address = trim((string) ($ordem->Localizacao ?? ''));
        if ($address === '' && $cliente) {
            $address = trim(collect([
                $cliente->Endereco ?? null,
                $cliente->Numero ?? null,
                $cliente->Bairro ?? null,
            ])->filter(fn ($value) => filled($value))->implode(', '));
        }

        $scheduledDate = $ordemApi?->data_visita?->format('Y-m-d');

        return [
            'id' => 'OS-'.(string) $ordem->{'Numero ordem'},
            'numero_ordem' => (int) $ordem->{'Numero ordem'},
            'cached_parts' => [],
            'client' => $cliente->{'Nome conhecido'} ?? $ordem->Cliente ?? 'Desconhecido',
            'client_id' => $ordem->{'Codigo cliente'},
            'phone' => $phone,
            'address' => $address,
            'date' => $ordem->{'Data emissao'},
            'description' => $ordem->{'Descrição problema'} ?? '',
            'status' => 'PENDING',
            'priority' => $ordem->Prioridade ?? 'MEDIUM',
            'checklist' => [],
            'report' => '',
            'photos' => [],
            'workSessions' => [],
            'lunchSessions' => [],
            'checkInTime' => null,
            'checkOutTime' => null,
            'serviceType' => '',
            'contact' => $ordem->Contato ?: ($cliente->Contato ?? ''),
            'observations' => $ordem->Obs ?? '',
            'services' => [],
            'sector' => $ordem->Setor ?? 'ASSISTENCIA',
            'equipment' => $ordem->{'Descrição equipamento'} ?? $ordem->Equipto ?? 'N/A',
            'brand' => $ordem->Marca,
            'model' => $ordem->{'Modelo tabela'} ?? $ordem->Modelo ?? '',
            'serial_number' => $ordem->{'No série'} ?? '',
            'scheduled_date' => $scheduledDate,
            'is_future_schedule' => $scheduledDate ? ($scheduledDate > now()->toDateString()) : false,
            'signature' => null,
            'techSignature' => null,
            'clientName' => null,
            'clientCpf' => null,
            'technician' => $ordem->Tecnico ?? '',
            'assistants' => array_values(array_filter([
                $ordemApi->ajudante1 ?? null,
                $ordemApi->ajudante2 ?? null,
                $ordemApi->ajudante3 ?? null,
                $ordemApi->ajudante4 ?? null,
            ])),
            'all_technicians' => array_values(array_filter([$ordem->Tecnico ?? null])),
            'route_order' => null,
            'route_id' => null,
        ];
    }

    public function createOrdemMeta(Request $request): JsonResponse
    {
        $sector = strtoupper((string) $request->query('sector', 'ASSISTENCIA'));
        if (! in_array($sector, ['ASSISTENCIA', 'OFICINA'], true)) {
            $sector = 'ASSISTENCIA';
        }

        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $numeroOrdem = app(OrdensController::class)->gerarNumeroOrdem();
        $funcionarioNome = $this->resolveAuthenticatedFuncionarioName($user);

        $vendedores = DB::table('Funcionários')
            ->select('Nome conhecido as nome')
            ->whereNull('Data demissão')
            ->orderBy('Nome conhecido')
            ->get()
            ->pluck('nome')
            ->map(fn ($nome) => trim((string) $nome))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $carros = Carro::orderBy('apelido')
            ->get()
            ->map(function ($carro) {
                $label = $carro->apelido
                    ? trim("{$carro->apelido} - {$carro->placa}")
                    : trim("{$carro->placa} - {$carro->modelo}");

                return [
                    'id' => (int) $carro->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'order_number' => $numeroOrdem,
            'sector' => $sector,
            'default_vendor' => $funcionarioNome,
            'default_attendant' => $funcionarioNome,
            'vendors' => $vendedores,
            'cars' => $carros,
        ]);
    }

    public function searchCreateClientes(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('query', ''));
        $nome = $request->input('nome');
        $cod = $request->input('cod');
        $doc = $request->input('doc');

        if ($query !== '' && ! $nome && ! $cod && ! $doc) {
            $digits = preg_replace('/\D/', '', $query);

            if ($digits !== '' && strlen($digits) >= 9) {
                $doc = $digits;
            } elseif ($digits !== '') {
                $cod = $digits;
                $doc = $digits;
            } else {
                $nome = $query;
            }
        }

        $forwardRequest = Request::create('/clientes/buscar-cliente', 'POST', [
            'nome' => $nome,
            'cod' => $cod,
            'doc' => $doc,
        ]);

        return app(ClienteController::class)->buscaCliente($forwardRequest);
    }

    public function searchCreateEquipamentos(Request $request): JsonResponse
    {
        $forwardRequest = Request::create('/ordens/busca-equipto', 'GET', [
            'marca' => $request->query('marca'),
            'equipamento' => $request->query('equipamento'),
        ]);

        return app(OrdensController::class)->buscaCascata($forwardRequest);
    }

    public function createOrdem(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'sector' => 'required|string|in:ASSISTENCIA,OFICINA',
            'order_number' => 'required|numeric',
            'cliente_codigo' => 'required|numeric',
            'cliente_nome' => 'required|string|max:100',
            'situacao_problema' => 'required|string|max:4000',
            'descricao_equipamento' => 'nullable|string|max:255',
            'equipto' => 'nullable|string|max:255',
            'marca' => 'nullable|string|max:100',
            'modelo_tabela' => 'nullable|string|max:255',
            'numero_serie' => 'nullable|string|max:255',
            'patrimonio' => 'nullable|string|max:255',
            'cor' => 'nullable|string|max:100',
            'voltagem' => 'nullable|string|max:50',
            'contato' => 'nullable|string|max:255',
            'fone' => 'nullable|string|max:255',
            'obs' => 'nullable|string|max:1000',
            'acessorio' => 'nullable|string|max:1000',
            'estado_aparelho' => 'nullable|string|max:1000',
            'vendedor' => 'nullable|string|max:255',
            'atendente' => 'nullable|string|max:255',
            'data_visita' => 'nullable|date',
            'sequencia' => 'nullable|string|max:20',
            'carro_id' => 'nullable|numeric',
            'ajudante1' => 'nullable|string|max:255',
            'ajudante2' => 'nullable|string|max:255',
            'ajudante3' => 'nullable|string|max:255',
            'ajudante4' => 'nullable|string|max:255',
            'skip_route_sync' => 'nullable|boolean',
        ]);

        $sector = strtoupper((string) $validated['sector']);
        $funcionarioNome = $this->resolveAuthenticatedFuncionarioName($user);
        $skipRouteSync = $request->boolean('skip_route_sync');
        $resolvedVendedor = trim((string) ($validated['vendedor'] ?? '')) ?: $funcionarioNome;
        $resolvedAtendente = trim((string) ($validated['atendente'] ?? '')) ?: $resolvedVendedor;

        $payload = [
            'no_ordem' => (int) $validated['order_number'],
            'setor' => $sector,
            'cliente_codigo' => (int) $validated['cliente_codigo'],
            'cliente_nome' => $validated['cliente_nome'],
            'contato' => $validated['contato'] ?? '',
            'fone' => $validated['fone'] ?? '',
            'vendedor' => $resolvedVendedor,
            'obs' => $validated['obs'] ?? '',
            'situacao_problema' => $validated['situacao_problema'],
            'data_emissao' => now()->format('Y-m-d'),
            'marca' => $validated['marca'] ?? '',
            'equipto' => $validated['equipto'] ?? ($validated['descricao_equipamento'] ?? ''),
            'codigo_equipamento' => $request->input('codigo_equipamento'),
            'modelo_tabela' => $validated['modelo_tabela'] ?? '',
            'codigo_modelo' => $request->input('codigo_modelo'),
            'descricao_equipamento' => $validated['descricao_equipamento'] ?? '',
            'numero_serie' => $validated['numero_serie'] ?? '',
            'patrimonio' => $validated['patrimonio'] ?? '',
            'cor' => $validated['cor'] ?? '',
            'voltagem' => $validated['voltagem'] ?? '',
            'estado_aparelho' => $validated['estado_aparelho'] ?? '',
            'acessorio' => $validated['acessorio'] ?? '',
        ];

        if ($sector === 'ASSISTENCIA' && ! $skipRouteSync) {
            $payload['tecnico'] = $resolvedAtendente;
            $payload['atendente'] = $resolvedAtendente;
            $payload['data_visita'] = $validated['data_visita'] ?? now()->format('Y-m-d');
            $payload['sequencia'] = $validated['sequencia'] ?? 'primeira';
            $payload['carro_id'] = $validated['carro_id'] ?? null;
            $payload['ajudante1'] = $validated['ajudante1'] ?? null;
            $payload['ajudante2'] = $validated['ajudante2'] ?? null;
            $payload['ajudante3'] = $validated['ajudante3'] ?? null;
            $payload['ajudante4'] = $validated['ajudante4'] ?? null;
        }

        $payload['skip_route_sync'] = $request->boolean('skip_route_sync');

        $forwardRequest = Request::create('/ordens/store', 'POST', $payload);
        $forwardRequest->setUserResolver(fn () => $user);

        $response = app(OrdensController::class)->store($forwardRequest);
        $responsePayload = $response->getData(true);

        if (($responsePayload['success'] ?? false) !== true) {
            return response()->json($responsePayload, $response->getStatusCode());
        }

        $ordem = Ordem::with(['clienteByCodigo.telefones', 'ordem_api'])
            ->where('Numero ordem', (int) $validated['order_number'])
            ->first();

        if (! $ordem) {
            return response()->json([
                'success' => false,
                'error' => 'OS criada, mas não foi possível carregar o resumo para o app.',
            ], 500);
        }

        if ($sector === 'ASSISTENCIA' && $skipRouteSync) {
            Ordem::where('Numero ordem', (int) $validated['order_number'])->update([
                'Tecnico' => '',
                'Atendente' => $resolvedVendedor,
                'Situacao' => 'ABERTA',
            ]);

            if ($ordem->ordem_api) {
                $ordem->ordem_api->update([
                    'atendente' => '',
                    'carro_id' => null,
                    'dia_visita' => null,
                    'sequencia' => null,
                    'data_visita' => null,
                    'ajudante1' => '',
                    'ajudante2' => '',
                    'ajudante3' => '',
                    'ajudante4' => '',
                ]);
            }

            $ordem = Ordem::with(['clienteByCodigo.telefones', 'ordem_api'])
                ->where('Numero ordem', (int) $validated['order_number'])
                ->first();
        } elseif ($sector === 'ASSISTENCIA' && $resolvedAtendente !== '') {
            Ordem::where('Numero ordem', (int) $validated['order_number'])->update([
                'Tecnico' => $resolvedAtendente,
            ]);

            if ($ordem->ordem_api) {
                $ordem->ordem_api->update([
                    'atendente' => $resolvedAtendente,
                ]);
            }

            $ordem = Ordem::with(['clienteByCodigo.telefones', 'ordem_api'])
                ->where('Numero ordem', (int) $validated['order_number'])
                ->first();
        }

        return response()->json([
            'success' => true,
            'message' => $responsePayload['message'] ?? 'OS criada com sucesso.',
            'sequence_adjusted' => $responsePayload['sequence_adjusted'] ?? false,
            'roteiro_numero' => $responsePayload['roteiro_numero'] ?? null,
            'seq_solicitada' => $responsePayload['seq_solicitada'] ?? null,
            'seq_final' => $responsePayload['seq_final'] ?? null,
            'os' => $this->buildCreatedMobileOsSummary($ordem),
        ], 200);
    }

    public function syncCreatedOrdemRoute(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'numero_ordem' => 'required|numeric',
            'atendente' => 'required|string|max:255',
            'data_visita' => 'required|date',
            'sequencia' => 'nullable|string|max:20',
            'carro_id' => 'nullable|numeric',
            'ajudante1' => 'nullable|string|max:255',
            'ajudante2' => 'nullable|string|max:255',
            'ajudante3' => 'nullable|string|max:255',
            'ajudante4' => 'nullable|string|max:255',
        ]);

        $numeroOrdem = (int) $validated['numero_ordem'];
        $ordem = Ordem::with('ordem_api')
            ->where('Numero ordem', $numeroOrdem)
            ->first();

        if (! $ordem) {
            return response()->json([
                'success' => false,
                'error' => 'OS nao encontrada.',
            ], 404);
        }

        if (! $ordem->ordem_api) {
            return response()->json([
                'success' => false,
                'error' => 'Esta OS nao possui dados suficientes para entrar na rota.',
            ], 422);
        }

        $syncRequest = Request::create('/app-ordens/create/route-sync', 'POST', [
            'atendente' => $validated['atendente'],
            'data_visita' => $validated['data_visita'],
            'sequencia' => $validated['sequencia'] ?? 'primeira',
            'carro_id' => $validated['carro_id'] ?? null,
            'ajudante1' => $validated['ajudante1'] ?? null,
            'ajudante2' => $validated['ajudante2'] ?? null,
            'ajudante3' => $validated['ajudante3'] ?? null,
            'ajudante4' => $validated['ajudante4'] ?? null,
        ]);
        $syncRequest->setUserResolver(fn () => $user);

        $syncResult = app(OrdensController::class)->autoSyncOrdemWithRoute($syncRequest, $numeroOrdem);
        $roteiroNumero = \App\Models\RoteiroTecnicoItem::where('No os', $numeroOrdem)->value('Numero roteiro');

        return response()->json([
            'success' => true,
            'message' => 'OS adicionada à rota de hoje.',
            'sequence_adjusted' => $syncResult['sequence_adjusted'] ?? false,
            'roteiro_numero' => $syncResult['roteiro_numero'] ?? $roteiroNumero,
            'seq_solicitada' => $syncResult['seq_solicitada'] ?? null,
            'seq_final' => $syncResult['seq_final'] ?? null,
        ], 200);
    }

    public function listOrdens(Request $request): JsonResponse
    {
        try {
            // Fetch OS from Database for the logged-in technician
            $user = Auth::user();

            // Manual fetch to avoid Connection Inheritance from User model (tecnoar_api)
            // Functionarios should be in Default DB, prioritize active employees
            $funcionario = \App\Models\Funcionarios::where('Email', $user->email)->whereNull('Data demissão')->first()
                ?? \App\Models\Funcionarios::where('Email', $user->email)->first();

            // Fallback: Use nomepgi or name if email lookup fails (consistent with login logic)
            if (! $funcionario && $user) {
                $identifier = ! empty($user->nomepgi) ? $user->nomepgi : $user->name;

                if (! empty($identifier)) {
                    $funcionario = \App\Models\Funcionarios::where(function ($q) use ($identifier) {
                        $q->where('Nome conhecido', $identifier)
                            ->orWhere('Nome funcionario', $identifier);
                    })->whereNull('Data demissão')->first()
                        ?? \App\Models\Funcionarios::where(function ($q) use ($identifier) {
                            $q->where('Nome conhecido', $identifier)
                                ->orWhere('Nome funcionario', $identifier);
                        })->first();
                }
            }

            $isSummary = filter_var($request->query('summaries'), FILTER_VALIDATE_BOOLEAN);

            $checklistSections = collect();
            if (! $isSummary) {
                // Re-fetch checklist structure specifically for embedding and sort by code
                $checklistSections = ChecklistSection::with(['items.fields', 'items.services', 'services'])->get()->sortBy(function ($section) {
                    return (float) $section->code;
                })->values();
            }

            // Dynamic Checklist Construction is now inside the transform loop to support per-OS parts injection.

            $osList = collect();

            $nomeTecnico = $funcionario ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'}) : 'FUNC_NULL';

            $today = now()->format('Y-m-d');

            // ✅ ABORDAGEM COMBINADA E CONFIÁVEL:
            // Há DOIS caminhos de atribuição:
            // A) Formulário da OS → atualiza `Ordens.Tecnico`
            // B) Roteiro/Planejamento → atualiza `OrdemApi.atendente` (string composta)
            //
            // Precisamos cobrir os dois caminhos.

            // A) OSs pelo campo legado Ordens.Tecnico
            $osViaLegacyTecnico = DB::table('Ordens')
                ->where('Tecnico', $nomeTecnico)
                ->whereNotIn('Situacao', ['ENCERRADA', 'CANCELADA'])
                ->pluck('Numero ordem')
                ->map(fn ($n) => (int) $n)
                ->toArray();

            // B) OSs via OrdemApi.atendente (string composta pelo buildAtendenteString)
            // Formato: "NOME CARRO - SEQ" ou "NOME/AJUDANTE CARRO - SEQ"
            // O nome principal é SEMPRE o texto antes do primeiro '/' ou espaço.
            // Matchs válidos: nome exato, nome seguido de ' ' (CARRO), nome seguido de '/' (ajudante)
            $osViaAtendenteString = \App\Models\OrdemApi::where(function ($q) use ($nomeTecnico) {
                $q->where('atendente', $nomeTecnico)          // exato
                    ->orWhere('atendente', 'LIKE', "{$nomeTecnico}/%")   // NOME/AJUDANTE...
                    ->orWhere('atendente', 'LIKE', "{$nomeTecnico} %");  // NOME CARRO...
            })->pluck('numero_ordem')->toArray();

            // C) OSs onde o usuário é ajudante (campos dedicados)
            $helperOsNumbers = \App\Models\OrdemApi::where(function ($q) use ($nomeTecnico) {
                $q->where('ajudante1', $nomeTecnico)
                    ->orWhere('ajudante2', $nomeTecnico)
                    ->orWhere('ajudante3', $nomeTecnico)
                    ->orWhere('ajudante4', $nomeTecnico);
            })->pluck('numero_ordem')->toArray();

            // Junção: todo OS que o técnico é responsável (por qualquer caminho) ou é ajudante
            $involvedOsNumbers = array_unique(array_merge($osViaLegacyTecnico, $osViaAtendenteString, $helperOsNumbers));

            // Status IN_PROGRESS: Sempre visível se eu estou nela
            $myInProgressOsNumbers = \App\Models\OrdemApi::where('status', 'IN_PROGRESS')
                ->whereIn('numero_ordem', $involvedOsNumbers)
                ->pluck('numero_ordem')->toArray();

            // Status COMPLETED: Visível até o fim do dia se concluída hoje
            $myRecentlyCompletedOsNumbers = \App\Models\OrdemApi::where('status', 'COMPLETED')
                ->whereIn('numero_ordem', $involvedOsNumbers)
                ->whereDate('updated_at', $today)
                ->pluck('numero_ordem')->toArray();

            $sector = $request->query('sector');

            $relations = ['clienteByCodigo.telefones', 'tecnicos', 'servicos'];
            $relations[] = 'pecas.pecaByCodigo';

            $query = \App\Models\Ordem::query();

            // Apply Technician/Employee Filters (GLOBAL - Applied to all except OFICINA)
            if ($funcionario && $sector !== 'OFICINA') {
                // SIMPLE AND RELIABLE: Only show OSs where this tech is directly involved
                // (either as Ordens.Tecnico or as ajudante1-4 in OrdemApi)
                $query->whereIn('Ordens.Numero ordem', $involvedOsNumbers);
            }

            // Excluir OSs que já foram encerradas ou finalizadas no backoffice (legado)
            $query->whereNotIn('Ordens.Situacao', ['ENCERRADA', 'CANCELADA']);

            if ($sector === 'OFICINA') {
                $query->where('Ordens.Setor', 'OFICINA')
                    ->with($relations);
            } else {
                // Assistance Sector

                // Sector Filter
                if ($sector) {
                    $query->where('Ordens.Setor', $sector);
                } else {
                    $query->whereIn('Ordens.Setor', ['ASSISTENCIA', 'INTERNO']);
                }

                // Future vs Today logic
                $isFuture = filter_var($request->query('future'), FILTER_VALIDATE_BOOLEAN);

                if ($isFuture) {
                    // Future OS: Only later visits
                    $query->join('Roteiros tecnicos itens as rti', function ($join) use ($today) {
                        $join->on('Ordens.Numero ordem', '=', 'rti.No os')
                            ->whereRaw('CAST(rti.[Data visita] AS DATE) > ?', [$today]);
                    });
                } else {
                    // Today's OS: Today's visits OR IN_PROGRESS status OR finished today by this tech
                    $query->leftJoin('Roteiros tecnicos itens as rti', function ($join) use ($today, $nomeTecnico) {
                        $join->on('Ordens.Numero ordem', '=', 'rti.No os')
                            ->whereRaw('CAST(rti.[Data visita] AS DATE) = ?', [$today])
                            ->whereExists(function ($ex) use ($nomeTecnico) {
                                $ex->select(DB::raw(1))
                                    ->from('Roteiros tecnicos as rt')
                                    ->whereColumn('rt.Numero roteiro', 'rti.Numero roteiro')
                                    ->where('rt.Tecnico', $nomeTecnico);
                            });
                    });

                    $query->where(function ($q) use ($myInProgressOsNumbers, $myRecentlyCompletedOsNumbers, $nomeTecnico, $today, $involvedOsNumbers) {
                        $q->whereIn('Ordens.Numero ordem', $myInProgressOsNumbers) // In progress always show (linked to user)
                            ->orWhereIn('Ordens.Numero ordem', $myRecentlyCompletedOsNumbers) // Completed today show (linked to user)
                            ->orWhere(function ($sq) use ($involvedOsNumbers, $today) {
                                $sq->whereIn('Ordens.Numero ordem', $involvedOsNumbers)
                                    ->whereExists(function ($ex) use ($today) {
                                        $ex->select(DB::raw(1))
                                            ->from('Roteiros tecnicos itens as rti_any')
                                            ->whereColumn('rti_any.No os', 'Ordens.Numero ordem')
                                            ->whereRaw('CAST(rti_any.[Data visita] AS DATE) = ?', [$today]);
                                    });
                            }) // Any scheduled visit for today where user is involved
                            ->orWhereHas('tecnicos', function ($sq) use ($nomeTecnico, $today) {
                                $sq->where('Técnico', $nomeTecnico)
                                    ->whereRaw('CAST([Data fim conserto] AS DATE) = ?', [$today]);
                            });
                    });
                }

                $query->select([
                    'Ordens.*',
                    'rti.Seq tecnico',
                    'rti.Numero roteiro',
                    'rti.Data visita as data_visita_roteiro',
                ])->with($relations);
            }

            // Apply Search Filter (Client, OS, OR Tech Name)
            $search = $request->query('search');
            if (! empty($search)) {
                $cleanSearch = preg_replace('/^OS-/i', '', trim($search));

                // Pre-fetch OS numbers that match tech name in OrdemApi
                $apiSearchOsNumbers = \App\Models\OrdemApi::where(function ($q) use ($search) {
                    $q->where('atendente', 'LIKE', "%{$search}%")
                        ->orWhere('ajudante1', 'LIKE', "%{$search}%")
                        ->orWhere('ajudante2', 'LIKE', "%{$search}%")
                        ->orWhere('ajudante3', 'LIKE', "%{$search}%")
                        ->orWhere('ajudante4', 'LIKE', "%{$search}%");
                })->pluck('numero_ordem')->toArray();

                $query->where(function ($q) use ($search, $cleanSearch, $apiSearchOsNumbers) {
                    $q->where('Ordens.Numero ordem', 'LIKE', "%{$cleanSearch}%")
                        ->orWhere('Ordens.Cliente', 'LIKE', "%{$search}%")
                        ->orWhereIn('Ordens.Numero ordem', $apiSearchOsNumbers);
                });
            }

            $perPage = $request->query('per_page', 10);

            if ($sector === 'OFICINA') {
                $paginated = $query->orderBy('Ordens.Data emissao', 'desc')
                    ->orderBy('Ordens.Numero ordem', 'desc')
                    ->paginate($perPage);
            } else {
                if ($isFuture) {
                    // Strict chronological order for future OS
                    $query->orderBy('rti.Data visita', 'asc');
                }

                $paginated = $query->orderByRaw('CASE WHEN [rti].[Seq tecnico] IS NULL THEN 999 ELSE [rti].[Seq tecnico] END ASC')
                    ->orderBy('Ordens.Data emissao', 'desc')
                    ->orderBy('Ordens.Numero ordem', 'desc')
                    // simplePaginate because we don't need total count (expensive)
                    ->simplePaginate($perPage);
            }

            $osList = collect($paginated->items());

            // Fetch relevant OrdemApi records for the OS list
            $osIds = $osList->pluck('Numero ordem')->toArray();
            $osIdsInt = array_map('intval', $osIds);

            // Fetch relevant OrdemApi records for the OS list
            // Use toBase() to bypass Eloquent casting issues with JSON on SQL Server
            $ordemApiData = \App\Models\OrdemApi::whereIn('numero_ordem', $osIdsInt)
                ->toBase()
                ->get()
                ->keyBy('numero_ordem'); // keyBy works on Collection, key is 'numero_ordem' column

            \Illuminate\Support\Facades\Log::info('ListOrdens: Found '.$ordemApiData->count().' api records for '.count($osList).' OSs. OS IDs: '.implode(',', $osIds));

            $isSummary = filter_var($request->query('summaries'), FILTER_VALIDATE_BOOLEAN);

            // Transform to App format
            $osList = $osList->map(function ($os) use ($checklistSections, $ordemApiData, $today, $sector, $isSummary) {
                try {
                    // Robust helper to encode local paths to Base64 for App consumption
                    // Checks both local/public disks and various common prefixes
                    $encodeIfPath = function ($val) {
                        return $this->encodeStoredImageReference($val);
                    };

                    $cliente = $os->clienteByCodigo;

                    // Phone Logic - Prioritize Relation as per ClienteController logic
                    $phone = '';
                    if ($cliente && $cliente->telefones && $cliente->telefones->isNotEmpty()) {
                        $phone = $cliente->telefones->first()->Fone;
                    }

                    // Fallback to existing fields if relation is empty
                    if (empty($phone)) {
                        $phone = trim($os->Fone ?? '') ?:
                            ($cliente->Telefone1 ?? $cliente->{'Celular boleto'} ?? '');
                    }

                    // Address Logic
                    $address = trim($os->Localizacao ?? '') ?:
                        ($cliente ? trim("{$cliente->Endereco}, {$cliente->Numero} - {$cliente->Bairro}") : '');

                    // ID Logic: App expects distinct string ID.
                    $osNumber = (string) ($os->{'Numero ordem'} ?? $os->numero_ordem);
                    $id = 'OS-'.$osNumber;

                    // Lookup record in OrdemApi mapped by numeric numero_ordem
                    $osNumberInt = (int) $osNumber;
                    $apiRecord = $ordemApiData[$osNumberInt] ?? null;

                    if (! $apiRecord && $ordemApiData->isNotEmpty()) {
                        \Illuminate\Support\Facades\Log::debug("ListOrdens: apiRecord not found for OS $osNumber (tried key $osNumberInt)");
                    }

                    // Status Logic (Only from OrdemApi)
                    $status = $apiRecord->status ?? 'PENDING';

                    // Real Parts from Eager Loaded relation
                    $realPecas = $os->pecas;

                    // Decode checklist JSON even in summary mode because the list response
                    // still depends on metadata such as serviceType, times and signatures.
                    $checklistJsonRaw = $apiRecord ? $apiRecord->checklist_json : null;
                    $checklistJson = [];
                    if (! empty($checklistJsonRaw)) {
                        $decoded = $checklistJsonRaw;
                        while (is_string($decoded)) {
                            $decoded = json_decode($decoded, true);
                        }
                        if (is_array($decoded)) {
                            $checklistJson = $decoded;
                        }
                    }

                    // Hydrate Checklist
                    // Skip hydration if in summary mode to optimize performance
                    if ($isSummary) {
                        $hydratedChecklist = [];
                    } else {
                        \Illuminate\Support\Facades\Log::debug("ListOrdens: Raw checklistJson for OS $osNumber: ".json_encode($checklistJson));

                        // Deep copy and hydrate checklist if we have data, otherwise return empty structure
                        // We must map it freshly to avoid referencing the same object for all OSs
                        // 1. Build Dynamic Checklist for this OS
                        $hydratedChecklist = $checklistSections->map(function ($section, $secIndex) use ($realPecas, $checklistJson, $encodeIfPath, $sector, $osNumber, $os) {
                            $titleLower = mb_strtolower(trim($section->title));
                            $isPieceSection = (str_contains($titleLower, 'peça') || str_contains($titleLower, 'produto')) && !str_contains($titleLower, 'medi');

                            // Clone items to avoid global mutation, but skip if it's the pieces section (we'll use realPecas instead)
                            $items = $isPieceSection ? collect([]) : $section->items->sort(function ($a, $b) {
                                return strnatcmp($a->code, $b->code);
                            })->values();

                            // Inject Real Parts if this is the Pieces section
                            if ($isPieceSection) {
                                $dynamicItems = $realPecas->map(function ($p, $pIndex) {
                                    // Prioritize the actual SKU from the catalog if available via eager loading
                                    $sku = trim($p->pecaByCodigo->{'Codigo peca'} ?? $p->{'Código fabricante'} ?? $p->{'Codigo peca'} ?? '???');
                                    $itemCode = $sku;

                                    // Mocking ChecklistItem structure loosely
                                    $mockItem = new \stdClass;
                                    $mockItem->id = 'PECA-'.$p->Item; // Keep original item index as ID to handle duplicates correctly in sync
                                    $mockItem->code = $itemCode;
                                    $mockItem->title = trim($p->{'Peça'});
                                    $mockItem->input_type = 'checkbox';
                                    $mockItem->value = '';
                                    $mockItem->db_column = null;
                                    $mockItem->services = collect([]);

                                    // Fields
                                    // Get Código Fabricante with fallback logic
                                    $codigoFabricante = $p->{'Código fabricante'} ?? '';
                                    if (empty($codigoFabricante) && $p->pecaByCodigo) {
                                        $codigoFabricante = $p->pecaByCodigo->{'Código fabricante'} ?? '';
                                    }
                                    if (empty($codigoFabricante)) {
                                        $peca = \App\Models\Pecas::where('Descricao peca', trim($p->{'Peça'}))->first();
                                        if ($peca) {
                                            $codigoFabricante = $peca->{'Código fabricante'} ?? '';
                                        }
                                    }

                                    $mockItem->fields = collect([
                                        (object) ['id' => 'sku', 'label' => 'SKU', 'type' => 'text', 'default_value' => $sku, 'db_column' => null],
                                        (object) ['id' => 'f1', 'label' => 'Qtd Levada', 'type' => 'number', 'default_value' => (string) floatval($p->Qtde ?? 0), 'db_column' => null],
                                        (object) ['id' => 'f2', 'label' => 'Qtd Util.', 'type' => 'number', 'default_value' => '0', 'db_column' => null],
                                        (object) ['id' => 'f3', 'label' => 'Valor Unit.', 'type' => 'number', 'default_value' => number_format(floatval($p->{'Valor informado'} ?? 0) > 0 ? floatval($p->{'Valor informado'}) : floatval($p->{'Valor tabela'} ?? 0), 2, '.', ''), 'db_column' => null],
                                        (object) [
                                            'id' => 'f4',
                                            'label' => 'Valor Total',
                                            'type' => 'number',
                                            'default_value' => '0.00',
                                            'db_column' => null,
                                        ],
                                        (object) ['id' => 'f5', 'label' => 'Cód. Fab.', 'type' => 'text', 'default_value' => trim($codigoFabricante), 'db_column' => null],
                                    ]);

                                    // OPTIMIZATION (Round 10): Use pre-warmed image map (no per-request disk I/O)
                                    $skuStr = (string) $sku;
                                    $imageMap = $this->getImageMap();
                                    $mockItem->image_url = $imageMap[$skuStr] ?? null;

                                    return $mockItem;
                                });

                                foreach ($dynamicItems as $dItem) {
                                    $items->push($dItem);
                                }
                            }

                            if ($sector === 'OFICINA') {
                                \Illuminate\Support\Facades\Log::debug("ListOrdens (OFICINA): Hydrating section {$section->id} ({$section->title}) for OS {$osNumber}. Initial values count: ".count($checklistJson));
                            }

                            // Hydration Logic: Try ID first, then Code
                            $sectionValues = [];
                            $vId = $checklistJson[$section->id] ?? null;
                            $vCode = $checklistJson[$section->code] ?? null;

                            // Use Union operator (+) to preserve numeric keys (like item IDs "37", "63")
                            // array_merge would reindex these to 0, 1, 2...
                            if (is_array($vCode)) {
                                $sectionValues = $vCode + $sectionValues;
                            }
                            if (is_array($vId)) {
                                $sectionValues = $vId + $sectionValues;
                            }

                            // Special case for Acceptance (Section 7)
                            if ($section->id == 7) {
                                $v7 = $checklistJson[7] ?? $checklistJson['7'] ?? [];
                                $v70 = $checklistJson['7.0'] ?? [];
                                if (is_array($v70)) {
                                    $sectionValues = $v70 + $sectionValues;
                                }
                                if (is_array($v7)) {
                                    $sectionValues = $v7 + $sectionValues;
                                }
                            }

                            $mappedItems = $items->map(function ($item, $itemIndex) use ($section, $sectionValues, $secIndex, $encodeIfPath, $os, $isPieceSection) {
                                $itemDisplayCode = ($item instanceof \App\Models\ChecklistItem)
                                    ? ($secIndex + 1).'.'.($itemIndex + 1)
                                    : $item->code;

                                // Check if we are dealing with a model or a mock object
                                // Robust ID Extraction: Try property, attribute, or array key
                                $itemId = '';
                                if (is_object($item)) {
                                    // Try direct property (mock) or magic getter (model)
                                    $itemId = $item->id ?? $item->getAttribute('id') ?? '';
                                } elseif (is_array($item)) {
                                    $itemId = $item['id'] ?? '';
                                }
                                $itemId = (string) $itemId;

                                $itemCode = (string) ($item->code ?? '');

                                $storedValue = $sectionValues[$itemId] ?? $sectionValues[$itemCode] ?? null;

                                // Fallback to item_index if ID lookup failed (Backward compatibility for payloadMapper fallback)
                                if ($storedValue === null) {
                                    $storedValue = $sectionValues["item_{$itemIndex}"] ?? null;
                                }

                                $mainValue = null;
                                $fieldsData = [];
                                $observation = '';
                                $photos = [];

                                if ($storedValue !== null) {
                                    if (is_array($storedValue)) {
                                        $mainValue = $storedValue['v'] ?? null;
                                        $fieldsData = $storedValue['f'] ?? [];
                                        $observation = $storedValue['o'] ?? '';
                                        $photos = $storedValue['p'] ?? [];
                                    } else {
                                        $mainValue = $storedValue;
                                    }
                                }

                                $mainValue = $this->resolveMirroredChecklistValue($item, $mainValue, $os);

                                if ($isPieceSection) {
                                    \Illuminate\Support\Facades\Log::debug("Hydrating Piece: $itemId / $itemCode | Stored: ".(is_array($storedValue) ? json_encode($storedValue) : $storedValue));
                                }

                                // Default from JSON config
                                $inputType = $item->input_type ?? '';
                                if ($mainValue === null && ($inputType === 'select' || $inputType === 'button')) {
                                    if (isset($item->value) && strpos($item->value, '{') === 0) {
                                        $config = json_decode($item->value, true);
                                        if (isset($config['selected'])) {
                                            $mainValue = $config['selected'];
                                        }
                                    }
                                }

                                // 3. Encode Photos if stored locally
                                if (! empty($photos)) {
                                    $photos = array_values(array_filter(array_map($encodeIfPath, $photos)));
                                }

                                // 4. Encode Main Value if it looks like a signature/image path (Signature items)
                                if ($item->input_type === 'signature' || $item->input_type === 'photo') {
                                    $mainValue = $encodeIfPath($mainValue);
                                }

                                $itemData = [
                                    'id' => $itemId,
                                    'code' => $itemDisplayCode,
                                    'title' => $item->title ?? '',
                                    'completed' => ($mainValue === true || $mainValue === '1' || (is_string($mainValue) && strlen(trim($mainValue)) > 0)),
                                    'inputType' => $inputType,
                                    'value' => $mainValue,
                                    'observation' => $observation,
                                    'photos' => $photos,
                                    'db_column' => $item->db_column ?? null,
                                    'is_required' => (bool) ($item->is_required ?? false),
                                    'services' => ($item instanceof \App\Models\ChecklistItem)
                                        ? $item->services->pluck('name')->toArray()
                                        : (($item->services ?? collect([]))->pluck('name')->toArray()),
                                    'image_url' => $item->image_url ?? null, // Map image_url if present
                                ];

                                if (isset($item->fields) && $item->fields->isNotEmpty()) {
                                    $itemData['fields'] = $item->fields->map(function ($field) use ($fieldsData, $section, $os, $encodeIfPath, $isPieceSection) {
                                        $fVal = null;
                                        $fid = (string) $field->id;
                                        $flabel = $field->label ?? '';
                                        if (isset($fieldsData[$fid])) {
                                            $fVal = $fieldsData[$fid];
                                        } elseif (isset($fieldsData[$flabel])) {
                                            $fVal = $fieldsData[$flabel];
                                        }

                                        $defaultValue = $field->default_value ?? '';
                                        $fieldObservation = '';
                                        $fieldPhotos = [];

                                        if (is_array($fVal)) {
                                            $fieldObservation = (string) ($fVal['o'] ?? $fVal['observation'] ?? '');
                                            if (isset($fVal['p']) && is_array($fVal['p'])) {
                                                $fieldPhotos = array_values(array_filter(array_map($encodeIfPath, $fVal['p'])));
                                            }
                                            $fVal = $fVal['v'] ?? $defaultValue;
                                        }

                                        // For the Peças (Parts) section, ALWAYS use the freshly computed
                                        // default_value from the DB. This prevents stale cached values
                                        // (e.g. old Valor item used as unit price) from persisting.
                                        $isPecaSection = $isPieceSection;
                                        if (! $isPecaSection && $section->title) {
                                            $t = mb_strtolower($section->title);
                                            $isPecaSection = (str_contains($t, 'peça') || str_contains($t, 'produto')) && !str_contains($t, 'medi');
                                        }

                                        // Intercept Service Subtotal to map from real OS values
                                        if ($flabel === 'Subtotal Serviço' || $flabel === 'Subtotal serviços') {
                                            $totalServicos = (float) ($os->{'Valor serviços'} ?? 0);
                                            $fVal = number_format($totalServicos, 2, ',', '');
                                        }

                                        if ($flabel === 'Subtotal Peças' || $flabel === 'Subtotal peças') {
                                            $totalPecas = (float) ($os->{'Total peças'} ?? 0);
                                            $fVal = number_format($totalPecas, 2, ',', '');
                                        }

                                        if ($flabel === 'Total Geral' || str_contains($flabel, 'Valor total')) {
                                            $totalGeral = (float) ($os->{'Valor total'} ?? 0);
                                            $fVal = number_format($totalGeral, 2, ',', '');
                                        }

                                        $shouldUseStoredPieceValue = $isPecaSection && in_array($flabel, ['Qtd Util.', 'Valor Total'], true);

                                        return [
                                            'id' => $fid,
                                            'label' => $flabel,
                                            'type' => $field->type ?? 'text',
                                            'value' => $shouldUseStoredPieceValue
                                                ? ($fVal ?? $defaultValue)
                                                : ($isPecaSection ? $defaultValue : ($fVal ?? $defaultValue)),
                                            'observation' => $fieldObservation,
                                            'photos' => $fieldPhotos,
                                            'db_column' => $field->db_column ?? null,
                                        ];
                                    })->values()->toArray();
                                }

                                // INJECTION: If it's "RESUMO DE CUSTOS", ensure the 3 required fields exist
                                if (mb_strtolower($itemData['title'] ?? '') === 'resumo de custos') {
                                    $totalPecas = (float) ($os->{'Total peças'} ?? 0);
                                    $totalServicos = (float) ($os->{'Valor serviços'} ?? 0);
                                    $totalGeral = (float) ($os->{'Valor total'} ?? ($totalPecas + $totalServicos));

                                    $requiredFields = [
                                        ['id' => 42, 'label' => 'Subtotal Serviço', 'type' => 'text', 'value' => number_format($totalServicos, 2, ',', ''), 'db_column' => null],
                                        ['id' => 43, 'label' => 'Subtotal Peças', 'type' => 'text', 'value' => number_format($totalPecas, 2, ',', ''), 'db_column' => null],
                                        ['id' => 44, 'label' => 'Total Geral', 'type' => 'text', 'value' => number_format($totalGeral, 2, ',', ''), 'db_column' => null],
                                    ];

                                    if (!isset($itemData['fields']) || empty($itemData['fields'])) {
                                        $itemData['fields'] = $requiredFields;
                                    } else {
                                        // Ensure all 3 required fields are present even if some exist
                                        foreach ($requiredFields as $rf) {
                                            $exists = false;
                                            foreach ($itemData['fields'] as $ef) {
                                                if ($ef['label'] === $rf['label']) {
                                                    $exists = true;
                                                    break;
                                                }
                                            }
                                            if (!$exists) {
                                                $itemData['fields'][] = $rf;
                                            }
                                        }
                                    }
                                }

                                return $itemData;
                            })->values()->toArray();

                            return [
                                'id' => (string) $section->id,
                                'code' => $section->code ?? (($secIndex + 1).'.0'),
                                'title' => $section->title,
                                'services' => $section->services->pluck('name')->toArray(),
                                'items' => $mappedItems,
                            ];
                        })->values()->toArray();
                    }

                    // 5. CACHED PARTS (LITE PRELOAD) - Round 7 Optimization
                    // Provide a lightweight list of parts immediately, even in summary mode.
                    $cachedParts = $realPecas->map(function ($p) {
                        $sku = trim($p->pecaByCodigo->{'Codigo peca'} ?? $p->{'Código fabricante'} ?? $p->{'Codigo peca'} ?? '???');
                        $skuStr = (string) $sku;

                        // OPTIMIZATION (Round 10): Use pre-warmed image map (no per-request disk I/O)
                        $imageMap = $this->getImageMap();
                        $imageUrl = $imageMap[$skuStr] ?? null;

                        return [
                            'id' => 'PECA-'.$p->Item,
                            'codigo' => $sku,
                            'cod_fabricante' => trim($p->{'Código fabricante'} ?? $p->pecaByCodigo->{'Código fabricante'} ?? ''),
                            'descricao' => trim($p->{'Peça'}),
                            'qtde' => (float) ($p->Qtde ?? 0),
                            'image_url' => $imageUrl,
                        ];
                    })->values()->toArray();

                    return [
                        'id' => $id,
                        'numero_ordem' => $os->{'Numero ordem'},
                        'cached_parts' => $cachedParts, // New field for instant loading
                        'client' => $cliente->{'Nome conhecido'} ?? $os->Cliente ?? 'Desconhecido',
                        'client_id' => $os->{'Codigo cliente'},
                        'phone' => $phone,
                        'address' => $address,
                        'date' => $os->{'Data emissao'},
                        'description' => $os->{'Descrição problema'} ?? '',
                        'status' => $status,
                        'priority' => $os->Prioridade ?? 'MEDIUM',
                        'checklist' => $hydratedChecklist, // Send hydrated checklist
                        'report' => ($isSummary) ? '' : ($checklistJson['report'] ?? ''),
                        'photos' => ($isSummary) ? [] : array_values(array_filter(array_map(function ($value) {
                            return $this->serializeMobilePhotoReference($value);
                        }, $checklistJson['photos'] ?? []))),
                        'workSessions' => $checklistJson['times']['workSessions'] ?? [],
                        'lunchSessions' => $checklistJson['times']['lunchSessions'] ?? [],
                        'checkInTime' => $checklistJson['times']['checkIn'] ?? ($os->{'Data entrada'} ?? null),
                        'checkOutTime' => $checklistJson['times']['checkOut'] ?? ($os->{'Data saida'} ?? null),
                        'serviceType' => $this->resolveStoredChecklistServiceType($checklistJson),
                        'contact' => $os->Contato ?: ($cliente->Contato ?? ''),
                        'observations' => $os->Obs ?? '',
                        'services' => $os->servicos->pluck('Descrição serviços')->toArray(),
                        'sector' => $os->Setor ?? 'ASSISTENCIA',
                        'equipment' => $os->{'Descrição equipamento'} ?? $os->Equipto ?? 'N/A',
                        'brand' => $os->Marca,
                        // Prioritize "Modelo tabela", which is the value edited in the "Equipamento da OS" card.
                        'model' => $os->{'Modelo tabela'} ?? $os->Modelo,
                        'serial_number' => $os->{'No série'} ?? 'N/A',
                        'scheduled_date' => $os->data_visita_roteiro ?? null,
                        'is_future_schedule' => ($os->data_visita_roteiro && $status === 'PENDING') ? (\Illuminate\Support\Carbon::parse($os->data_visita_roteiro)->toDateString() > $today) : false,
                        'signature' => $encodeIfPath($checklistJson['signature'] ?? null),
                        'techSignature' => $encodeIfPath($checklistJson['techSignature'] ?? null),
                        'clientName' => $checklistJson['participantName'] ?? $checklistJson['clientName'] ?? null,
                        'clientCpf' => $checklistJson['participantCpf'] ?? $checklistJson['clientCpf'] ?? null,
                        'technician' => $os->Tecnico ?? '',
                        'assistants' => array_values(array_filter([
                            $apiRecord->ajudante1 ?? null,
                            $apiRecord->ajudante2 ?? null,
                            $apiRecord->ajudante3 ?? null,
                            $apiRecord->ajudante4 ?? null,
                        ])),
                        // All technicians from Ordens técnicos table (for multi-tech indicator)
                        'all_technicians' => $os->tecnicos ? $os->tecnicos->pluck('Técnico')->filter()->unique()->values()->toArray() : [],
                        'route_order' => $os->{'Seq tecnico'} ?? null,
                        'route_id' => $os->{'Numero roteiro'} ?? null,
                    ];
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('ListOrdens: Error transforming OS '.($os->{'Numero ordem'} ?? 'UNKNOWN').': '.$e->getMessage());
                    \Illuminate\Support\Facades\Log::error('Stack trace: '.$e->getTraceAsString());
                    throw $e; // Re-throw to see the full error
                }
            });

            $serviceTypes = \App\Models\ChecklistService::pluck('name')->toArray();

            return response()->json([
                'os_list' => $osList,
                'service_types' => $serviceTypes,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => method_exists($paginated, 'lastPage') ? $paginated->lastPage() : null,
                    'total' => method_exists($paginated, 'total') ? $paginated->total() : null,
                    'per_page' => $paginated->perPage(),
                    'has_more' => $paginated->hasMorePages(),
                ],
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AppSync ListOrdens Error: '.$e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Server Error: '.$e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Lightweight real-time sync endpoint for a single OS.
     * Returns the checklist_json plus lightweight metadata for live OS refresh.
     * Designed to be polled rapidly (every 4s) while inside an OS.
     * The client sends its last known updated_at — if nothing changed, returns 304-style response.
     */
    public function syncOS(Request $request, int $osNumber)
    {
        try {
            $since = $request->query('since'); // ISO timestamp of last known update

            $record = \App\Models\OrdemApi::where('numero_ordem', $osNumber)
                ->select(['numero_ordem', 'checklist_json', 'status', 'updated_at'])
                ->first();

            if (! $record) {
                return response()->json(['changed' => false]);
            }

            $serverUpdatedAt = $record->updated_at ? $record->updated_at->toISOString() : null;

            // If client already has this version, don't send the full payload
            if ($since && $serverUpdatedAt && $since === $serverUpdatedAt) {
                return response()->json(['changed' => false, 'updated_at' => $serverUpdatedAt]);
            }

            $checklistJson = $this->normalizeChecklistArray($record->checklist_json ?? []);
            $photos = array_values(array_filter(array_map(function ($value) {
                return $this->serializeMobilePhotoReference($value);
            }, $checklistJson['photos'] ?? [])));

            return response()->json([
                'changed' => true,
                'updated_at' => $serverUpdatedAt,
                'status' => $record->status,
                'service_type' => $this->resolveStoredChecklistServiceType($record->checklist_json),
                'checklist_json' => $record->checklist_json,
                'photos' => $photos,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("AppSync syncOS Error for OS {$osNumber}: ".$e->getMessage());

            return response()->json(['changed' => false], 500);
        }
    }

    /**
     * CASE centralizado: status da OS (fluxo completo atualizado)
     * Copiado de OrdensController para consistência.
     */
    public function situacaoCaseExpression(): string
    {
        return "CASE
            WHEN NULLIF(LTRIM(RTRIM([Data saida])), '') IS NOT NULL
                 AND TRY_CONVERT(datetime2, [Data saida], 103) IS NOT NULL THEN 'FINALIZADA'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('23', '7', '8')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'FINALIZADA'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('17')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'AGUARDANDO RETIRADA'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('77', '15')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) OR (NULLIF(LTRIM(RTRIM([Data fim conserto])), '') IS NOT NULL
                 AND TRY_CONVERT(datetime2, [Data fim conserto], 103) IS NOT NULL) THEN 'PRONTO'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('100')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'ENTREGA DE PEÇAS'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('97', '91', '206')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'SEPARAÇÃO DE PEÇAS'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('144')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) OR (NULLIF(LTRIM(RTRIM([Data início conserto])), '') IS NOT NULL
                 AND TRY_CONVERT(datetime2, [Data início conserto], 103) IS NOT NULL) THEN 'INICIO CONSERTO'
            WHEN NULLIF(LTRIM(RTRIM([Data aprovação])), '') IS NOT NULL
                 AND TRY_CONVERT(datetime2, [Data aprovação], 103) IS NOT NULL THEN 'APROVADA'
            WHEN NULLIF(LTRIM(RTRIM([Data envio])), '') IS NOT NULL
                 AND TRY_CONVERT(datetime2, [Data envio], 103) IS NOT NULL THEN 'ENVIADA'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('11')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'CONFERIDO'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('12')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'DIGITAÇÃO'
            WHEN NULLIF(LTRIM(RTRIM([Data orçamento])), '') IS NOT NULL
                 AND TRY_CONVERT(datetime2, [Data orçamento], 103) IS NOT NULL THEN 'ORCADA'
            WHEN EXISTS (
                SELECT 1 FROM [Ordens técnicos] ot 
                WHERE ot.[Numero ordem] = [Ordens].[Numero ordem] 
                AND ot.Tarefa IN ('1')
                AND ot.Num = (SELECT MAX(Num) FROM [Ordens técnicos] WHERE [Numero ordem] = ot.[Numero ordem])
            ) THEN 'PRÉ ORÇAMENTO'
            ELSE 'ABERTA'
        END";
    }

    public function storeOS(Request $request, MobileCommissionIndicationService $commissionIndicationService)
    {
        \Illuminate\Support\Facades\Log::info('StoreOS Request Payload: '.json_encode($request->all()));
        try {
            // Get user to verify auth
            $user = Auth::user();
            if (! $user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Expect payload generated by payloadMapper.ts
            // "Numero ordem" is the key.
            $data = $request->all();

            $numeroOrdem = $data['Numero ordem'] ?? null;
            if (! $numeroOrdem) {
                return response()->json(['message' => 'Número da ordem is required'], 400);
            }

            // Remove 'Numero ordem' from data part for update, but keep it for identification
            // Actually updateOrCreate takes (attributes to match, values to update)

            // Filter data to only allowed columns (though fillable handles this, it's good to be safe)
            // We just pass $data and let Eloquent handle fillable.

            $numeroOrdem = $data['Numero ordem'] ?? '0000';
            $basePath = "{$numeroOrdem}";
            \Illuminate\Support\Facades\Log::info("StoreOS: Processing OS $basePath");

            // Snapshot atual para comparar mudanças de checklist e status no histórico.
            $existingApiRecord = \App\Models\OrdemApi::where('numero_ordem', (int) $numeroOrdem)->first();
            $existingChecklistSnapshot = $this->normalizeChecklistArray($existingApiRecord->checklist_json ?? []);
            $existingPhotoReferences = $this->collectChecklistPrivatePhotoReferences($existingChecklistSnapshot, $basePath);
            $previousStatus = $existingApiRecord->status ?? null;

            $itemsMeta = \App\Models\ChecklistItem::select('id', 'code', 'title', 'checklist_section_id', 'input_type')->get();
            $itemTitleMap = [];
            $itemAliases = [];
            $signatureItemMetaByAlias = [];
            $signatureItemTargetsByTopLevelKey = [
                'signature' => null,
                'techSignature' => null,
            ];
            foreach ($itemsMeta as $itemMeta) {
                $itemId = trim((string) ($itemMeta->id ?? ''));
                $itemCode = trim((string) ($itemMeta->code ?? ''));
                $itemTitle = trim((string) ($itemMeta->title ?? ''));
                $sectionId = trim((string) ($itemMeta->checklist_section_id ?? ''));
                $isSignatureItem = ($itemMeta->input_type ?? null) === 'signature';
                $itemMetaPayload = null;

                if ($isSignatureItem) {
                    $itemMetaPayload = $this->buildSignatureTargetMeta(
                        $sectionId,
                        $itemId !== '' ? $itemId : $itemCode,
                        $itemCode,
                        $itemTitle
                    ) ?? [
                        'id' => $itemId !== '' ? $itemId : $itemCode,
                        'code' => $itemCode,
                        'title' => $itemTitle,
                        'sectionId' => $sectionId,
                        'role' => $this->inferSignatureRole($itemId, $itemCode, $itemTitle),
                    ];
                }

                if ($itemId !== '') {
                    $itemAliases[$itemId] = $itemId;
                    if ($itemTitle !== '') {
                        $itemTitleMap[$itemId] = $itemTitle;
                    }
                    if (($itemMetaPayload['role'] ?? null) !== null) {
                        $signatureItemMetaByAlias[$itemId] = $itemMetaPayload;
                    }
                }

                if ($itemCode !== '') {
                    $itemAliases[$itemCode] = $itemId !== '' ? $itemId : $itemCode;
                    if ($itemTitle !== '') {
                        $itemTitleMap[$itemCode] = $itemTitle;
                    }
                    if (($itemMetaPayload['role'] ?? null) !== null) {
                        $signatureItemMetaByAlias[$itemCode] = $itemMetaPayload;
                    }
                }

                if (($itemMetaPayload['role'] ?? null) !== null && $sectionId !== '' && ($itemMetaPayload['id'] ?? '') !== '') {
                    $topLevelKey = $itemMetaPayload['role'] === 'tecnico' ? 'techSignature' : 'signature';
                    if ($signatureItemTargetsByTopLevelKey[$topLevelKey] === null) {
                        $signatureItemTargetsByTopLevelKey[$topLevelKey] = $itemMetaPayload;
                    }
                }
            }

            $sectionAliases = [];
            $sectionsMeta = \App\Models\ChecklistSection::select('id', 'code')->get();
            foreach ($sectionsMeta as $sectionMeta) {
                $sectionId = trim((string) ($sectionMeta->id ?? ''));
                $sectionCode = trim((string) ($sectionMeta->code ?? ''));

                if ($sectionId !== '') {
                    $sectionAliases[$sectionId] = $sectionId;
                }
                if ($sectionCode !== '') {
                    $sectionAliases[$sectionCode] = $sectionId !== '' ? $sectionId : $sectionCode;
                }
            }

            // Special debug for OS 61666
            if ($numeroOrdem == '61666') {
                \Illuminate\Support\Facades\Log::info('StoreOS [61666]: Full payload received: '.json_encode($data));
                \Illuminate\Support\Facades\Log::info('StoreOS [61666]: checklist_json in payload: '.json_encode($data['checklist_json'] ?? 'NOT_PRESENT'));
            }

            // Ensure directories exist (Recursive)
            $subfolders = ['assinaturas', 'fotos-gerais', 'fotos-adicionais'];
            foreach ($subfolders as $sub) {
                $dirPath = "{$basePath}/{$sub}";
                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($dirPath)) {
                    $ok = \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($dirPath);
                    \Illuminate\Support\Facades\Log::info("StoreOS: Created directory $dirPath: ".($ok ? 'YES' : 'NO'));
                }
            }

            $checklistJson = $data['checklist_json'] ?? [];
            if (is_string($checklistJson)) {
                $checklistJson = json_decode($checklistJson, true) ?: [];
            }
            \Illuminate\Support\Facades\Log::info("StoreOS: Processing checklist for OS $numeroOrdem. Initial count: ".count($checklistJson));

            // Helper for Base64 -> JPG (Stable MD5 Filename)
            $saveImage = function ($rawValue, $subfolder, $customFilename = null, $fallbackValue = null) use ($basePath, $numeroOrdem) {
                $normalizedFallback = $this->normalizeStoredImageReference(is_string($fallbackValue) ? $fallbackValue : null);

                if (! is_string($rawValue)) {
                    return $normalizedFallback;
                }

                $rawValue = trim($rawValue);
                if ($rawValue === '') {
                    return $normalizedFallback;
                }

                if (strpos($rawValue, 'data:image') !== 0) {
                    $normalizedReference = $this->normalizeStoredImageReference($rawValue);
                    if ($normalizedReference !== null) {
                        return $normalizedReference;
                    }

                    if ($this->looksLikeImageReference($rawValue)) {
                        \Illuminate\Support\Facades\Log::warning("StoreOS: Ignoring invalid image reference for OS {$numeroOrdem}", [
                            'value' => $rawValue,
                            'fallback' => $normalizedFallback,
                        ]);
                    }

                    return $normalizedFallback;
                }

                $imageParts = explode(',', $rawValue, 2);
                if (count($imageParts) !== 2) {
                    \Illuminate\Support\Facades\Log::warning("StoreOS: Invalid base64 image payload for OS {$numeroOrdem}");

                    return $rawValue;
                }

                $decoded = base64_decode($imageParts[1], true);
                if ($decoded === false) {
                    \Illuminate\Support\Facades\Log::warning("StoreOS: Failed to decode base64 image for OS {$numeroOrdem}");

                    return $rawValue;
                }

                $hash = md5($decoded);
                $filename = $customFilename ? "{$customFilename}-{$hash}" : $hash;
                $path = "{$basePath}/{$subfolder}/{$filename}.jpg";

                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists("{$basePath}/{$subfolder}")) {
                    \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory("{$basePath}/{$subfolder}");
                }

                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                    return $path;
                }

                $contentsToWrite = $decoded;
                $image = @imagecreatefromstring($decoded);
                if ($image) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    $whiteImage = imagecreatetruecolor($width, $height);
                    $white = imagecolorallocate($whiteImage, 255, 255, 255);
                    imagefilledrectangle($whiteImage, 0, 0, $width, $height, $white);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                    imagecopy($whiteImage, $image, 0, 0, 0, 0, $width, $height);

                    ob_start();
                    imagejpeg($whiteImage, null, 85);
                    $jpegContent = ob_get_clean();
                    imagedestroy($image);
                    imagedestroy($whiteImage);

                    if ($jpegContent !== false) {
                        $contentsToWrite = $jpegContent;
                    }
                }

                if (! $this->putLocalImageContents($path, $contentsToWrite)) {
                    \Illuminate\Support\Facades\Log::error("StoreOS: Failed to persist image to {$path} for OS {$numeroOrdem}");

                    return $rawValue;
                }

                \Illuminate\Support\Facades\Log::info("StoreOS: Saved new image {$path}");

                return $path;
            };

            // Get technician name
            $nomeVendedor = 'Mobile App';
            $funcionario = \App\Models\Funcionarios::where('Email', $user->email)->first();
            if ($funcionario) {
                $nomeVendedor = $funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'};
            }

            foreach ($checklistJson as $sectionKey => $items) {
                if (! is_array($items)) {
                    continue;
                }

                $section = null;
                if (is_numeric($sectionKey)) {
                    $section = \App\Models\ChecklistSection::find($sectionKey);
                } else {
                    $section = \App\Models\ChecklistSection::where('code', $sectionKey)->first();
                }

                $targetSectionKey = $section ? (string) $section->id : $sectionKey;
                // If the key changed due to normalization, ensure we clean the old one later or update $checklistJson
                if ($targetSectionKey !== (string) $sectionKey) {
                    $checklistJson[$targetSectionKey] = $checklistJson[$sectionKey];
                    unset($checklistJson[$sectionKey]);
                    $sectionKey = $targetSectionKey;
                }

                $isPartsSection = false;
                if ($section && $section->title) {
                    $t = mb_strtolower($section->title);
                    if ((str_contains($t, 'peça') || str_contains($t, 'produto')) && !str_contains($t, 'medi')) {
                        $isPartsSection = true;
                    }
                }

                foreach ($items as $itemId => $value) {
                    if (empty($value)) {
                        continue;
                    }

                    $previousStoredValue = $existingChecklistSnapshot[$sectionKey][$itemId] ?? null;

                    // 1. JSON Simplification (Save only 'selected' value for configs)
                    $itemMainValue = is_array($value) ? ($value['v'] ?? null) : $value;
                    if (is_string($itemMainValue) && strpos($itemMainValue, '{') === 0) {
                        $decoded = json_decode($itemMainValue, true);
                        if (is_array($decoded) && isset($decoded['selected'])) {
                            if (is_array($value)) {
                                $value['v'] = $decoded['selected'];
                                $checklistJson[$sectionKey][$itemId]['v'] = $decoded['selected'];
                            } else {
                                $value = $decoded['selected'];
                                $checklistJson[$sectionKey][$itemId] = $decoded['selected'];
                            }
                        }
                    }

                    // 2. Handle Parts
                    if ($isPartsSection && strpos($itemId, 'PECA-') === 0) {
                        $itemSeq = str_replace('PECA-', '', $itemId);
                        $fields = is_array($value) && isset($value['f']) ? $value['f'] : [];
                        $qtdUtilRaw = $fields['f2'] ?? $fields['Qtd Util.'] ?? null;
                        $qtdUtil = is_array($qtdUtilRaw) ? ($qtdUtilRaw['v'] ?? null) : $qtdUtilRaw;
                        if ($qtdUtil !== null) {
                            $pecaOrcada = \App\Models\OrdemPeca::where('Numero ordem', $numeroOrdem)
                                ->where('Item', $itemSeq)
                                ->first();

                            if ($pecaOrcada && (float) $pecaOrcada->{'Qtde utilizada'} != (float) $qtdUtil) {
                                $sku = trim($pecaOrcada->{'Código fabricante'} ?? $pecaOrcada->{'Codigo peca'} ?? '???');

                                OrdensHistorico::create([
                                    'cod_ordem' => $numeroOrdem,
                                    'vendedor' => $nomeVendedor,
                                    'acao' => 'ALTERAÇÃO',
                                    'detalhes' => "[Mobile] Alterou Qtde Utilizada produto #{$sku} para ".floatval($qtdUtil),
                                ]);

                                // Use a specific where clause instead of instance->update()
                                // because OrdemPeca primary key might not be unique (composite logic)
                                \App\Models\OrdemPeca::where('Numero ordem', $numeroOrdem)
                                    ->where('Item', $itemSeq)
                                    ->update([
                                        'Qtde utilizada' => floatval($qtdUtil),
                                        'Qtde compra' => floatval($qtdUtil),
                                        'Reserva' => floatval($qtdUtil) > 0 ? 'B' : '',
                                    ]);
                            }
                        }
                    }

                    // 3. Handle Item-Level Photos and Signatures
                    if (is_array($value)) {
                        // Complex Item {v: ..., f: ..., o: ..., p: [...]}
                        if (isset($value['p']) && is_array($value['p'])) {
                            $previousItemPhotos = is_array($previousStoredValue) && isset($previousStoredValue['p']) && is_array($previousStoredValue['p'])
                                ? $previousStoredValue['p']
                                : [];
                            $normalizedItemPhotos = [];

                            foreach ($value['p'] as $idx => $photoBase64) {
                                $savedPhoto = $saveImage(
                                    $photoBase64,
                                    'fotos-adicionais',
                                    null,
                                    $previousItemPhotos[$idx] ?? null
                                );

                                if (is_string($savedPhoto) && trim($savedPhoto) !== '') {
                                    $normalizedItemPhotos[] = $savedPhoto;
                                }
                            }

                            if (! empty($normalizedItemPhotos)) {
                                $checklistJson[$sectionKey][$itemId]['p'] = $normalizedItemPhotos;
                            } else {
                                unset($checklistJson[$sectionKey][$itemId]['p']);
                            }
                        }
                        if (isset($value['f']) && is_array($value['f'])) {
                            foreach ($value['f'] as $fieldKey => $fieldValue) {
                                if (! is_array($fieldValue) || ! isset($fieldValue['p']) || ! is_array($fieldValue['p'])) {
                                    continue;
                                }

                                $previousFieldPhotos = is_array($previousStoredValue)
                                    && isset($previousStoredValue['f'][$fieldKey])
                                    && is_array($previousStoredValue['f'][$fieldKey])
                                    && isset($previousStoredValue['f'][$fieldKey]['p'])
                                    && is_array($previousStoredValue['f'][$fieldKey]['p'])
                                    ? $previousStoredValue['f'][$fieldKey]['p']
                                    : [];
                                $safeFieldKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $fieldKey) ?: 'field';
                                $normalizedFieldPhotos = [];

                                foreach ($fieldValue['p'] as $photoIndex => $photoBase64) {
                                    $savedPhoto = $saveImage(
                                        $photoBase64,
                                        'fotos-adicionais',
                                        "item-{$itemId}-{$safeFieldKey}-{$photoIndex}",
                                        $previousFieldPhotos[$photoIndex] ?? null
                                    );

                                    if (is_string($savedPhoto) && trim($savedPhoto) !== '') {
                                        $normalizedFieldPhotos[] = $savedPhoto;
                                    }
                                }

                                if (! empty($normalizedFieldPhotos)) {
                                    $checklistJson[$sectionKey][$itemId]['f'][$fieldKey]['p'] = $normalizedFieldPhotos;
                                } else {
                                    unset($checklistJson[$sectionKey][$itemId]['f'][$fieldKey]['p']);
                                }
                            }
                        }
                        // If value is signature in custom object
                        if (isset($value['v']) && is_string($value['v']) && $this->looksLikeImageReference($value['v'])) {
                            $currentItemMeta = $signatureItemMetaByAlias[(string) $itemId]
                                ?? $this->buildSignatureTargetMeta(
                                    (string) $sectionKey,
                                    (string) $itemId,
                                    $itemAliases[(string) $itemId] ?? (string) $itemId,
                                    $itemTitleMap[(string) $itemId] ?? null
                                );

                            $savedSignature = $this->saveCanonicalSignatureImage(
                                $value['v'],
                                $basePath,
                                $currentItemMeta,
                                null,
                                is_array($previousStoredValue) ? ($previousStoredValue['v'] ?? null) : null
                            );

                            if (is_string($savedSignature) && trim($savedSignature) !== '') {
                                $checklistJson[$sectionKey][$itemId]['v'] = $savedSignature;
                            } else {
                                unset($checklistJson[$sectionKey][$itemId]['v']);
                            }
                        }
                    } elseif (is_string($value) && $this->looksLikeImageReference($value)) {
                        $currentItemMeta = $signatureItemMetaByAlias[(string) $itemId]
                            ?? $this->buildSignatureTargetMeta(
                                (string) $sectionKey,
                                (string) $itemId,
                                $itemAliases[(string) $itemId] ?? (string) $itemId,
                                $itemTitleMap[(string) $itemId] ?? null
                            );

                        $savedSignature = $this->saveCanonicalSignatureImage(
                            $value,
                            $basePath,
                            $currentItemMeta,
                            null,
                            $previousStoredValue
                        );
                        if (is_string($savedSignature) && trim($savedSignature) !== '') {
                            $checklistJson[$sectionKey][$itemId] = $savedSignature;
                        } else {
                            unset($checklistJson[$sectionKey][$itemId]);
                        }
                    }
                }
            }

            $initialSignatureSyncResult = $this->synchronizeChecklistSignatureReferences(
                $checklistJson,
                $basePath,
                $signatureItemMetaByAlias,
                $signatureItemTargetsByTopLevelKey
            );
            $checklistJson = $initialSignatureSyncResult['checklist'];

            // 3. Handle Global Photos
            $globalPhotos = $data['photos'] ?? [];
            $savedGlobalPhotos = [];
            $previousGlobalPhotos = isset($existingChecklistSnapshot['photos']) && is_array($existingChecklistSnapshot['photos'])
                ? $existingChecklistSnapshot['photos']
                : [];
            foreach ($globalPhotos as $idx => $pBase64) {
                $savedPhoto = $saveImage($pBase64, 'fotos-gerais', null, $previousGlobalPhotos[$idx] ?? null);
                if (is_string($savedPhoto) && trim($savedPhoto) !== '') {
                    $savedGlobalPhotos[] = $savedPhoto;
                }
            }
            $checklistJson['report'] = $data['report'] ?? '';
            \Illuminate\Support\Facades\Log::info('StoreOS: Saving report: '.($checklistJson['report'] ?: 'EMPTY'));
            $checklistJson['photos'] = $savedGlobalPhotos;

            // Handle Top-Level Signatures from Payload
            $currentClientSignatureValue = $this->extractChecklistSignatureValue(
                $checklistJson,
                $signatureItemTargetsByTopLevelKey['signature'] ?? null
            );
            if (
                isset($data['signature'])
                && is_string($data['signature'])
                && $this->looksLikeImageReference($data['signature'])
            ) {
                $savedSignature = $this->saveCanonicalSignatureImage(
                    $data['signature'],
                    $basePath,
                    $signatureItemTargetsByTopLevelKey['signature'] ?? null,
                    'signature',
                    $currentClientSignatureValue ?? ($checklistJson['signature'] ?? ($existingChecklistSnapshot['signature'] ?? null))
                );

                if (is_string($savedSignature) && trim($savedSignature) !== '') {
                    $checklistJson['signature'] = $savedSignature;
                }
            }
            $currentTechSignatureValue = $this->extractChecklistSignatureValue(
                $checklistJson,
                $signatureItemTargetsByTopLevelKey['techSignature'] ?? null
            );
            if (
                isset($data['techSignature'])
                && is_string($data['techSignature'])
                && $this->looksLikeImageReference($data['techSignature'])
            ) {
                $savedTechSignature = $this->saveCanonicalSignatureImage(
                    $data['techSignature'],
                    $basePath,
                    $signatureItemTargetsByTopLevelKey['techSignature'] ?? null,
                    'techSignature',
                    $currentTechSignatureValue ?? ($checklistJson['techSignature'] ?? ($existingChecklistSnapshot['techSignature'] ?? null))
                );

                if (is_string($savedTechSignature) && trim($savedTechSignature) !== '') {
                    $checklistJson['techSignature'] = $savedTechSignature;
                }
            }

            if (! isset($checklistJson['signature']) && ! empty($existingChecklistSnapshot['signature'])) {
                $checklistJson['signature'] = $existingChecklistSnapshot['signature'];
            }
            if (! isset($checklistJson['techSignature']) && ! empty($existingChecklistSnapshot['techSignature'])) {
                $checklistJson['techSignature'] = $existingChecklistSnapshot['techSignature'];
            }

            $signatureSyncResult = $this->synchronizeChecklistSignatureReferences(
                $checklistJson,
                $basePath,
                $signatureItemMetaByAlias,
                $signatureItemTargetsByTopLevelKey
            );
            $checklistJson = $signatureSyncResult['checklist'];
            $signatureKeepPaths = $signatureSyncResult['paths'];

            // Handle Client Info (from Payload)
            if (isset($data['clientCpf'])) {
                $checklistJson['clientCpf'] = $data['clientCpf'];
            }

            $serviceType = $this->normalizeChecklistServiceType($data['service_type'] ?? ($checklistJson['serviceType'] ?? $checklistJson['service_type'] ?? null));
            if ($serviceType !== '') {
                $checklistJson['serviceType'] = $serviceType;
            } else {
                unset($checklistJson['serviceType'], $checklistJson['service_type']);
            }

            // Validate JSON Encoding before saving
            $encoded = json_encode($checklistJson);
            if ($encoded === false) {
                \Illuminate\Support\Facades\Log::error("StoreOS JSON Encode Error (OS $numeroOrdem): ".json_last_error_msg());

                // Attempt to sanitize by forcing UTF-8
                array_walk_recursive($checklistJson, function (&$item, $key) {
                    if (is_string($item) && ! mb_check_encoding($item, 'UTF-8')) {
                        $item = mb_convert_encoding($item, 'UTF-8', 'ISO-8859-1');
                    }
                });

                // Re-check
                $encoded = json_encode($checklistJson);
                if ($encoded === false) {
                    // Last resort: invalid substitution
                    $encoded = json_encode($checklistJson, JSON_INVALID_UTF8_SUBSTITUTE);
                    $checklistJson = json_decode($encoded, true); // Get back the sanitized array
                    \Illuminate\Support\Facades\Log::warning("StoreOS: Forced UTF-8 substitution for OS $numeroOrdem");
                }
            }

            $data['checklist_json'] = $checklistJson;
            $ordemMirrorUpdates = $this->buildOrdensChecklistMirrorUpdates($data, $checklistJson);
            $recommendationText = $this->resolveCommissionRecommendationText($data, $checklistJson, $ordemMirrorUpdates);
            \Illuminate\Support\Facades\Log::info("StoreOS Final Checklist JSON for OS $numeroOrdem: ".$encoded);

            // 1. Save Checklist Data (Legacy/Detailed Table)
            \Illuminate\Support\Facades\Log::info("StoreOS: About to save OS $numeroOrdem. Checklist array count: ".count($data['checklist_json']).' | Type: '.gettype($data['checklist_json']));
            \Illuminate\Support\Facades\Log::info('StoreOS: Checklist keys: '.implode(', ', array_keys($data['checklist_json'])));

            // SAFETY CHECK: Prevent overwriting existing data with empty checklist if OS is finalized or just to be safe
            $existingChecklist = \App\Models\OrdemApi::where('numero_ordem', (int) $numeroOrdem)->value('checklist_json');

            // Should we block empty checklist save?
            // Only if existing has data and new is COMPLETELY empty (null or empty array).
            // A valid app payload from execution.tsx ALWAYS includes at least the 'times' key.
            $isNewChecklistEmpty = ! isset($data['checklist_json']) || ! is_array($data['checklist_json']) || count($data['checklist_json']) === 0;

            if (! empty($existingChecklist) && $isNewChecklistEmpty) {
                \Illuminate\Support\Facades\Log::warning("StoreOS: BLOCKED saving empty checklist over existing data for OS $numeroOrdem. Possible payload failure.");
                // Use existing data instead of overwriting with empty
                $data['checklist_json'] = $existingChecklist;
            }

            $currentChecklistSnapshot = $this->normalizeChecklistArray($data['checklist_json'] ?? []);
            $currentPhotoReferences = $this->collectChecklistPrivatePhotoReferences($currentChecklistSnapshot, $basePath);
            $removedPhotoReferences = array_values(array_diff($existingPhotoReferences, $currentPhotoReferences));

            $this->syncMobileSessionTasks(
                (int) $numeroOrdem,
                $existingApiRecord?->checklist_json ?? [],
                $data['checklist_json'] ?? [],
                $data['status'] ?? $previousStatus,
                $nomeVendedor
            );

            if (! empty($ordemMirrorUpdates)) {
                DB::table('Ordens')
                    ->where('Numero ordem', $numeroOrdem)
                    ->update($ordemMirrorUpdates);
            }

            $checklistChanges = $this->buildChecklistHistoryChanges(
                $existingChecklistSnapshot,
                $this->normalizeChecklistArray($data['checklist_json'] ?? []),
                $itemTitleMap,
                $previousStatus,
                $data['status'] ?? null,
                [
                    'sections' => $sectionAliases,
                    'items' => $itemAliases,
                ]
            );

            // Usa o snapshot já carregado no início para preservar ajudantes e atendente.

            // Determine the correct atendente:
            // If the saving user is a HELPER (appears in ajudante1-4), preserve the existing atendente.
            // Only update atendente if the user is NOT a helper (i.e. they are already the main tech).
            $existingAtendente = $existingApiRecord?->atendente ?? null;
            $callerIsHelper = $existingApiRecord && (
                strtoupper($existingApiRecord?->ajudante1 ?? '') === strtoupper($nomeVendedor) ||
                strtoupper($existingApiRecord?->ajudante2 ?? '') === strtoupper($nomeVendedor) ||
                strtoupper($existingApiRecord?->ajudante3 ?? '') === strtoupper($nomeVendedor) ||
                strtoupper($existingApiRecord?->ajudante4 ?? '') === strtoupper($nomeVendedor)
            );

            // Helpers never overwrite atendente; only the main tech can change it
            $atendenteToSave = $callerIsHelper
                ? $existingAtendente       // Preserve current atendente
                : $nomeVendedor;           // Caller is main tech: update atendente

            $checklistData = \App\Models\OrdemApi::updateOrCreate(
                ['numero_ordem' => (int) $numeroOrdem],
                [
                    'checklist_json' => $data['checklist_json'],
                    'status' => $data['status'] ?? null,
                    'atendente' => $atendenteToSave,
                    // Preserve helper assignments from web app - DO NOT overwrite with null from the mobile
                    'ajudante1' => $existingApiRecord?->ajudante1 ?? null,
                    'ajudante2' => $existingApiRecord?->ajudante2 ?? null,
                    'ajudante3' => $existingApiRecord?->ajudante3 ?? null,
                    'ajudante4' => $existingApiRecord?->ajudante4 ?? null,
                ]
            );

            /*
            $commissionOrderNumber = $commissionIndicationService->ensureCommissionOrderForRecommendation(
                $checklistData,
                $nomeVendedor,
                $recommendationText
            );
            */
            $commissionOrderNumber = null;

            if (! empty($checklistChanges)) {
                $this->upsertChecklistHistoryEntry(
                    (int) $numeroOrdem,
                    $nomeVendedor,
                    '[Mobile] Alteração no checklist',
                    $checklistChanges
                );
            }

            \Illuminate\Support\Facades\Log::info('StoreOS: Saved successfully. Verifying saved data...');
            if ($commissionOrderNumber) {
                \Illuminate\Support\Facades\Log::info("StoreOS: OS de comissão por indicação vinculada à OS {$numeroOrdem}.", [
                    'numero_ordem_comissao' => $commissionOrderNumber,
                ]);
            }

            // Force refresh from database and clear cache
            $checklistData->refresh();
            \Illuminate\Support\Facades\Cache::forget('ordem_api_'.$numeroOrdem);

            $verification = \App\Models\OrdemApi::where('numero_ordem', (int) $numeroOrdem)->first();
            if ($verification) {
                $savedJson = $verification->checklist_json;
                $rawJson = $verification->getRawOriginal('checklist_json');
                \Illuminate\Support\Facades\Log::info('StoreOS: Verification - Raw DB value: '.($rawJson ?? 'NULL'));
                \Illuminate\Support\Facades\Log::info('StoreOS: Verification - Saved checklist count: '.(is_array($savedJson) ? count($savedJson) : 'NOT_ARRAY').' | Type: '.gettype($savedJson));
                if (is_array($savedJson) && count($savedJson) > 0) {
                    \Illuminate\Support\Facades\Log::info('StoreOS: Verification - Keys: '.implode(', ', array_keys($savedJson)));
                }
            } else {
                \Illuminate\Support\Facades\Log::error('StoreOS: Verification FAILED - Record not found!');
            }

            if (! empty($removedPhotoReferences)) {
                $this->deleteChecklistPrivatePhotos($removedPhotoReferences);
            }

            $this->deleteChecklistSignatureFilesExcept($basePath, $signatureKeepPaths ?? []);

            // PDF is now generated dynamically via OrdensController@gerarPdfAppCompletion

            return response()->json([
                'success' => true,
                'message' => 'Checklist salvo com sucesso!',
                'data' => $checklistData,
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('StoreOS Error: '.$e->getMessage());

            return response()->json([
                'message' => 'Erro ao salvar checklist: '.$e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function showOrdem(Ordem $ordem)
    {
        try {
            $osNumber = $ordem->{'Numero ordem'};

            // 1. Fetch Compact Checklist JSON from API Table
            $ordemApi = \App\Models\OrdemApi::where('numero_ordem', $osNumber)->first();
            $checklistJson = $ordemApi ? $ordemApi->checklist_json : [];

            // 2. Fetch Real Parts and Services for this OS
            $realPecas = \App\Models\OrdemPeca::where('Numero ordem', $osNumber)->with('pecaByCodigo')->get();
            $realServicos = \App\Models\OrdemMaodeObra::where('Numero ordem', $osNumber)->get();

            // 2.1 Create a map of part names to Código fabricante for quick lookup
            $pecasMap = [];
            foreach ($realPecas as $ordemPeca) {
                $nomePeca = trim($ordemPeca->{'Peça'});
                if ($nomePeca) {
                    // Try to get from OrdemPeca first
                    $codigoFab = $ordemPeca->{'Código fabricante'};

                    // If empty, try from relationship
                    if (empty($codigoFab) && $ordemPeca->pecaByCodigo) {
                        $codigoFab = $ordemPeca->pecaByCodigo->{'Código fabricante'};
                    }

                    // If still empty, query Pecas table by name
                    if (empty($codigoFab)) {
                        $peca = \App\Models\Pecas::where('Descricao peca', $nomePeca)->first();
                        if ($peca) {
                            $codigoFab = $peca->{'Código fabricante'};
                        }
                    }

                    $pecasMap[$nomePeca] = $codigoFab ?? '';
                    \Log::info("[PECAS MAP] {$nomePeca} => ".($codigoFab ?? 'VAZIO'));
                }
            }

            // 3. Fetch Base Checklist Structure
            $checklistSections = ChecklistSection::with(['items.fields', 'items.services', 'services'])->get()->sortBy(function ($s) {
                return (float) $s->code;
            })->values();

            $checklist = $checklistSections->map(function ($section, $secIndex) use ($checklistJson, $realPecas, $pecasMap, $ordem) {
                $titleLower = mb_strtolower(trim($section->title));
                $isPieceSection = (str_contains($titleLower, 'peça') || str_contains($titleLower, 'produto')) && !str_contains($titleLower, 'medi');

                // Determine Section Values (Robust lookup: ID > Code)
                $vId = $checklistJson[$section->id] ?? null;
                $vCode = $checklistJson[$section->code] ?? null;

                $sectionValues = [];
                if (is_array($vCode)) {
                    $sectionValues = $vCode;
                }
                if (is_array($vId)) {
                    $sectionValues = $vId + $sectionValues;
                }

                // Compatibility: If logic ID is 7, check for old keys (7.0, 99.0)
                if ($section->id === 7) {
                    $v7 = $checklistJson[7] ?? [];
                    $v70 = $checklistJson['7.0'] ?? [];
                    if (! empty($v70) && is_array($v70)) {
                        $sectionValues = $v70 + $sectionValues;
                    }
                    if (! empty($v7) && is_array($v7)) {
                        $sectionValues = $v7 + $sectionValues;
                    }
                }

                return [
                    'id' => $section->id,
                    'code' => $section->code ?? (($secIndex + 1).'.0'),
                    'title' => $section->title,
                    'services' => $section->services->pluck('name')->toArray(),
                    'items' => ($isPieceSection)
                        ? $realPecas->map(function ($p, $pIndex) use ($secIndex, $pecasMap, $sectionValues) {
                            $itemCode = 'P-'.$p->Item;
                            $itemDisplayCode = ($secIndex + 1).'.'.($pIndex + 1);
                            $nomePeca = trim($p->{'Peça'});
                            $codigoFabricante = $pecasMap[$nomePeca] ?? '';
                            $itemId = 'PECA-'.$p->Item;
                            $storedItem = $sectionValues[$itemId] ?? ($sectionValues[$itemCode] ?? null);
                            $storedFields = is_array($storedItem) ? ($storedItem['f'] ?? []) : [];
                            $storedQtdUtilRaw = $storedFields['f2'] ?? ($storedFields['Qtd Util.'] ?? null);
                            $storedValorTotalRaw = $storedFields['f4'] ?? ($storedFields['Valor Total'] ?? null);

                            $storedQtdUtil = is_array($storedQtdUtilRaw) ? ($storedQtdUtilRaw['v'] ?? null) : $storedQtdUtilRaw;
                            $storedValorTotal = is_array($storedValorTotalRaw) ? ($storedValorTotalRaw['v'] ?? null) : $storedValorTotalRaw;

                            $qtdUtilValue = ($storedQtdUtil !== null && $storedQtdUtil !== '') ? (string) $storedQtdUtil : '0';
                            $valorTotalValue = ($storedValorTotal !== null && $storedValorTotal !== '') ? (string) $storedValorTotal : '0.00';

                            return [
                                'id' => $itemId,
                                'code' => $itemDisplayCode,
                                'title' => $nomePeca,
                                'completed' => floatval(str_replace(',', '.', $qtdUtilValue)) > 0,
                                'inputType' => 'checkbox',
                                'value' => '',
                                'is_required' => false,
                                'services' => [],
                                'fields' => [
                                    ['id' => 'f1', 'label' => 'Qtd Levada', 'type' => 'number', 'value' => (string) intval($p->Qtde)],
                                    ['id' => 'f2', 'label' => 'Qtd Util.', 'type' => 'number', 'value' => $qtdUtilValue],
                                    ['id' => 'f3', 'label' => 'Valor Unit.', 'type' => 'number', 'value' => number_format(floatval($p->{'Valor informado'} ?? 0) > 0 ? floatval($p->{'Valor informado'}) : floatval($p->{'Valor tabela'} ?? 0), 2, '.', '')],
                                    ['id' => 'f4', 'label' => 'Valor Total', 'type' => 'number', 'value' => $valorTotalValue],
                                    ['id' => 'f5', 'label' => 'Cód. Fab.', 'type' => 'text', 'value' => $codigoFabricante],
                                ],
                                'image_url' => null, // Placeholder for future image support
                            ];
                        })->values()
                        : $section->items->sort(function ($a, $b) {
                            return strnatcmp($a->code, $b->code);
                        })->values()->map(function ($item, $itemIndex) use ($sectionValues, $checklistJson, $secIndex, $ordem) {
                            $itemDisplayCode = ($secIndex + 1).'.'.($itemIndex + 1);

                            // Robust Lookup: ID > Code > Semantic Keys
                            $storedValue = $sectionValues[$item->id] ?? $sectionValues[$item->code] ?? null;

                            if ($storedValue === null && $item->input_type === 'signature') {
                                $signatureRole = $this->inferSignatureRole(
                                    (string) $item->id,
                                    (string) $item->code,
                                    (string) $item->title
                                );

                                if ($signatureRole === 'cliente') {
                                    $storedValue = $sectionValues['signature'] ?? ($checklistJson['signature'] ?? null);
                                } elseif ($signatureRole === 'tecnico') {
                                    $storedValue = $sectionValues['techSignature'] ?? ($checklistJson['techSignature'] ?? null);
                                }
                            }

                            // Hydrate logic
                            $observation = '';
                            $photos = [];
                            $mainValue = null;
                            $fieldsData = [];

                            if ($storedValue !== null) {
                                $mainValue = $storedValue;
                                if (is_array($storedValue)) {
                                    $mainValue = $storedValue['v'] ?? null;
                                    $fieldsData = $storedValue['f'] ?? [];
                                    $observation = $storedValue['o'] ?? '';
                                    if (isset($storedValue['p']) && is_array($storedValue['p'])) {
                                        foreach ($storedValue['p'] as $imgPath) {
                                            $encodedImage = $this->encodeStoredImageReference($imgPath);
                                            if (is_string($encodedImage) && $encodedImage !== '') {
                                                $photos[] = $encodedImage;
                                            }
                                        }
                                    }
                                }
                            }

                            if ($item->input_type !== 'signature' && is_string($mainValue)) {
                                $normalizedMainValue = $this->normalizeStoredImageReference($mainValue);
                                if (is_string($normalizedMainValue) && $this->isSignaturePath($normalizedMainValue)) {
                                    $mainValue = null;
                                }
                            }

                            $mainValue = $this->resolveMirroredChecklistValue($item, $mainValue, $ordem);

                            $value = '';
                            $completed = false;

                            if ($mainValue !== null) {
                                if ($item->input_type === 'select' || $item->input_type === 'button') {
                                    if ($item->value && str_starts_with($item->value, '{')) {
                                        $config = json_decode($item->value, true);
                                        if (is_array($config) && isset($config['buttons'])) {
                                            $config['selected'] = (string) $mainValue;
                                            $value = json_encode($config);
                                        } else {
                                            $value = (string) $mainValue;
                                        }
                                        $completed = ! empty($value);
                                    } else {
                                        $value = (string) $mainValue;
                                        $completed = ! empty($value);
                                    }
                                } elseif ($item->input_type === 'checkbox') {
                                    $completed = filter_var($mainValue, FILTER_VALIDATE_BOOLEAN);
                                    $value = (string) $mainValue;
                                } elseif ($item->input_type === 'signature') {
                                    $value = (string) $mainValue;
                                    $value = $this->encodeStoredImageReference($value) ?? '';
                                    $completed = $value !== '';
                                } else {
                                    $value = (string) $mainValue;
                                    $completed = ! empty($value);
                                }

                                $itemData = [
                                    'id' => $item->id,
                                    'code' => $itemDisplayCode,
                                    'title' => $item->title,
                                    'completed' => $completed,
                                    'inputType' => $item->input_type,
                                    'value' => $value,
                                    'observation' => $observation,
                                    'photos' => $photos,
                                    'db_column' => $item->db_column,
                                    'is_required' => (bool) ($item->is_required ?? false),
                                    'services' => $item->services->pluck('name')->toArray(),
                                ];

                                if ($item->fields->isNotEmpty()) {
                                    $itemData['fields'] = $item->fields->map(function ($field) use ($fieldsData) {
                                        $rawFieldValue = $fieldsData[$field->id] ?? $fieldsData[$field->label] ?? null;
                                        $fieldValue = $field->default_value ?? '';
                                        $fieldObservation = '';
                                        $fieldPhotos = [];

                                        if (is_array($rawFieldValue)) {
                                            $fieldValue = (string) ($rawFieldValue['v'] ?? $field->default_value ?? '');
                                            $fieldObservation = (string) ($rawFieldValue['o'] ?? $rawFieldValue['observation'] ?? '');

                                            if (isset($rawFieldValue['p']) && is_array($rawFieldValue['p'])) {
                                                foreach ($rawFieldValue['p'] as $imgPath) {
                                                    $encodedImage = $this->encodeStoredImageReference($imgPath);
                                                    if (is_string($encodedImage) && $encodedImage !== '') {
                                                        $fieldPhotos[] = $encodedImage;
                                                    }
                                                }
                                            }
                                        } elseif ($rawFieldValue !== null) {
                                            $fieldValue = (string) $rawFieldValue;
                                        }

                                        // INTERCEPT TOTALS
                                        $flabel = $field->label ?? '';
                                        if ($flabel === 'Subtotal Serviço' || $flabel === 'Subtotal serviços') {
                                            $totalServicos = (float) ($ordem->{'Valor serviços'} ?? 0);
                                            $fieldValue = number_format($totalServicos, 2, ',', '');
                                        }

                                        if ($flabel === 'Subtotal Peças' || $flabel === 'Subtotal peças') {
                                            $totalPecas = (float) ($ordem->{'Total peças'} ?? 0);
                                            $fieldValue = number_format($totalPecas, 2, ',', '');
                                        }

                                        if ($flabel === 'Total Geral' || str_contains($flabel, 'Valor total')) {
                                            $totalGeral = (float) ($ordem->{'Valor total'} ?? 0);
                                            $fieldValue = number_format($totalGeral, 2, ',', '');
                                        }

                                        return [
                                            'id' => $field->id,
                                            'label' => $flabel,
                                            'type' => $field->type,
                                            'value' => $fieldValue,
                                            'observation' => $fieldObservation,
                                            'photos' => $fieldPhotos,
                                            'db_column' => $field->db_column,
                                        ];
                                    })->toArray();
                                }

                                // INJECTION: If it's "RESUMO DE CUSTOS", ensure the 3 required fields exist
                                if (mb_strtolower($item->title ?? '') === 'resumo de custos') {
                                    $totalPecas = (float) ($ordem->{'Total peças'} ?? 0);
                                    $totalServicos = (float) ($ordem->{'Valor serviços'} ?? 0);
                                    $totalGeral = (float) ($ordem->{'Valor total'} ?? ($totalPecas + $totalServicos));

                                    $requiredFields = [
                                        ['id' => 42, 'label' => 'Subtotal Serviço', 'type' => 'text', 'value' => number_format($totalServicos, 2, ',', ''), 'db_column' => null],
                                        ['id' => 43, 'label' => 'Subtotal Peças', 'type' => 'text', 'value' => number_format($totalPecas, 2, ',', ''), 'db_column' => null],
                                        ['id' => 44, 'label' => 'Total Geral', 'type' => 'text', 'value' => number_format($totalGeral, 2, ',', ''), 'db_column' => null],
                                    ];

                                    if (!isset($itemData['fields']) || empty($itemData['fields'])) {
                                        $itemData['fields'] = $requiredFields;
                                    } else {
                                        // Ensure all 3 required fields are present even if some exist
                                        foreach ($requiredFields as $rf) {
                                            $exists = false;
                                            foreach ($itemData['fields'] as $ef) {
                                                if ($ef['label'] === $rf['label']) {
                                                    $exists = true;
                                                    break;
                                                }
                                            }
                                            if (!$exists) {
                                                $itemData['fields'][] = $rf;
                                            }
                                        }
                                    }
                                }

                                return $itemData;
                            }
                        }),
                ];
            })->toArray();

            // 3. Construct Response
            $cliente = $ordem->clienteByCodigo;
            $phone = '';
            if ($cliente && $cliente->telefones && $cliente->telefones->isNotEmpty()) {
                $phone = $cliente->telefones->first()->Fone;
            } else {
                $phone = trim($ordem->Fone ?? '') ?: ($cliente->Telefone1 ?? $cliente->{'Celular boleto'} ?? '');
            }
            $address = trim($ordem->Localizacao ?? '') ?: ($cliente ? trim("{$cliente->Endereco}, {$cliente->Numero} - {$cliente->Bairro}") : '');

            $data = [
                'id' => 'OS-'.$ordem->{'Numero ordem'},
                'client' => $cliente->{'Nome conhecido'} ?? $ordem->Cliente ?? 'Desconhecido',
                'phone' => $phone,
                'address' => $address,
                'date' => $ordem->{'Data emissao'},
                'description' => $ordem->{'Descrição problema'} ?? '',
                'checklist' => $checklist,
                'report' => $checklistJson['report'] ?? '',
                'photos' => array_values(array_filter(array_map(function ($p) {
                    return $this->encodeStoredImageReference($p);
                }, $checklistJson['photos'] ?? []))),
                'workSessions' => $checklistJson['times']['workSessions'] ?? [],
                'lunchSessions' => $checklistJson['times']['lunchSessions'] ?? [],
                'checkInTime' => $checklistJson['times']['checkIn'] ?? ($ordem->{'Data entrada'} ?? null),
                'checkOutTime' => $checklistJson['times']['checkOut'] ?? ($ordem->{'Data saida'} ?? null),
                'serviceType' => $this->resolveStoredChecklistServiceType($checklistJson),
                'status' => $ordemApi->status ?? 'PENDING',
            ];

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server Error: '.$e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    private function buildOrdensChecklistMirrorUpdates(array $payload, array $checklistJson): array
    {
        $updates = $this->buildOrdensTimeMirrorUpdates($payload, $checklistJson);

        foreach ($this->getChecklistMirrorMappings() as $mapping) {
            if (array_key_exists($mapping['payload_key'], $payload)) {
                $updates[$mapping['ordem_column']] = $this->normalizeChecklistMirrorText($payload[$mapping['payload_key']]);

                continue;
            }

            $legacyValue = $this->extractChecklistMirrorValue($checklistJson, $mapping);
            if ($legacyValue !== null) {
                $updates[$mapping['ordem_column']] = $legacyValue;
            }
        }

        return $updates;
    }

    private function buildOrdensTimeMirrorUpdates(array $payload, array $checklistJson): array
    {
        $times = isset($checklistJson['times']) && is_array($checklistJson['times'])
            ? $checklistJson['times']
            : [];

        $checkInAt = $this->parseMobileOrdemEventAt(
            $times['checkIn'] ?? ($payload['checkInTime'] ?? null)
        );
        $checkOutAt = $this->parseMobileOrdemEventAt(
            $times['checkOut'] ?? ($payload['checkOutTime'] ?? null)
        );

        $updates = [];

        if ($checkInAt) {
            $updates['Data início conserto'] = $checkInAt->toDateString();
            $updates['Hora inicio conserto'] = $checkInAt->format('H:i:s');
        }

        if ($checkOutAt) {
            $updates['Data fim conserto'] = $checkOutAt->toDateString();
            $updates['Hora fim conserto'] = $checkOutAt->format('H:i:s');
        }

        return $updates;
    }

    private function parseMobileOrdemEventAt($timestamp): ?\Illuminate\Support\Carbon
    {
        if (! is_scalar($timestamp) && $timestamp !== null) {
            return null;
        }

        $timestamp = trim((string) ($timestamp ?? ''));
        if ($timestamp === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($timestamp)->setTimezone(config('app.timezone'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('⚠️ [AppSync] Falha ao converter timestamp mobile para espelho em Ordens.', [
                'timestamp' => $timestamp,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizeChecklistServiceType($value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }

        return trim((string) ($value ?? ''));
    }

    private function resolveStoredChecklistServiceType($checklistJson): string
    {
        if (is_string($checklistJson)) {
            $decoded = json_decode($checklistJson, true);
            if (is_array($decoded)) {
                $checklistJson = $decoded;
            }
        }

        if (! is_array($checklistJson)) {
            return '';
        }

        return $this->normalizeChecklistServiceType(
            $checklistJson['serviceType'] ?? $checklistJson['service_type'] ?? ''
        );
    }

    private function resolveCommissionRecommendationText(
        array $payload,
        array $checklistJson,
        array $ordemMirrorUpdates
    ): string {
        if (array_key_exists('Complemento solucao', $ordemMirrorUpdates)) {
            return (string) $this->normalizeChecklistMirrorText($ordemMirrorUpdates['Complemento solucao']);
        }

        foreach ($this->getChecklistMirrorMappings() as $mapping) {
            if (($mapping['ordem_column'] ?? null) !== 'Complemento solucao') {
                continue;
            }

            if (array_key_exists($mapping['payload_key'], $payload)) {
                return (string) $this->normalizeChecklistMirrorText($payload[$mapping['payload_key']]);
            }

            return (string) ($this->extractChecklistMirrorValue($checklistJson, $mapping) ?? '');
        }

        return '';
    }

    private function resolveMirroredChecklistValue($item, $mainValue, Ordem $ordem)
    {
        $currentValue = $this->normalizeChecklistMirrorText($mainValue, true);
        if ($currentValue !== null && $currentValue !== '') {
            return $mainValue;
        }

        $itemId = is_object($item) ? ($item->id ?? null) : ($item['id'] ?? null);
        $itemCode = is_object($item) ? ($item->code ?? null) : ($item['code'] ?? null);
        $itemTitle = is_object($item) ? ($item->title ?? null) : ($item['title'] ?? null);
        $mapping = $this->resolveChecklistMirrorMapping($itemId, $itemCode, $itemTitle);

        if (! $mapping) {
            return $mainValue;
        }

        $fallbackValue = $this->normalizeChecklistMirrorText($ordem->{$mapping['ordem_column']} ?? null, true);

        return $fallbackValue ?? $mainValue;
    }

    private function extractChecklistMirrorValue(array $checklistJson, array $mapping): ?string
    {
        foreach ($checklistJson as $sectionValues) {
            if (! is_array($sectionValues)) {
                continue;
            }

            foreach ($sectionValues as $itemKey => $rawValue) {
                if (! $this->matchesChecklistMirrorMapping($itemKey, null, null, $mapping)) {
                    continue;
                }

                $value = is_array($rawValue) ? ($rawValue['v'] ?? null) : $rawValue;

                return $this->normalizeChecklistMirrorText($value, true);
            }
        }

        return null;
    }

    private function resolveChecklistMirrorMapping($itemId, $itemCode, $itemTitle): ?array
    {
        foreach ($this->getChecklistMirrorMappings() as $mapping) {
            if ($this->matchesChecklistMirrorMapping($itemId, $itemCode, $itemTitle, $mapping)) {
                return $mapping;
            }
        }

        return null;
    }

    private function matchesChecklistMirrorMapping($itemId, $itemCode, $itemTitle, array $mapping): bool
    {
        $normalize = fn ($value) => mb_strtolower(trim((string) $value));
        $normalizedId = $normalize($itemId);
        $normalizedCode = $normalize($itemCode);
        $normalizedTitle = $normalize($itemTitle);
        $mappingIds = array_map($normalize, $mapping['item_ids']);
        $mappingCodes = array_map($normalize, $mapping['item_codes']);
        $mappingTitles = array_map($normalize, $mapping['item_titles']);

        if ($normalizedId !== '' && in_array($normalizedId, $mappingIds, true)) {
            return true;
        }

        if ($normalizedCode !== '' && in_array($normalizedCode, $mappingCodes, true)) {
            return true;
        }

        return $normalizedTitle !== '' && in_array($normalizedTitle, $mappingTitles, true);
    }

    private function normalizeChecklistMirrorText($value, bool $allowNull = false): ?string
    {
        if ($value === null) {
            return $allowNull ? null : '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return $allowNull ? null : '';
    }

    private function getChecklistMirrorMappings(): array
    {
        return [
            [
                'payload_key' => 'Compl solução orça',
                'ordem_column' => 'Compl solução orça',
                'item_ids' => ['25'],
                'item_codes' => ['4.01', '4.1'],
                'item_titles' => ['Descrição do serviço executado', 'Compl solução orça'],
            ],
            [
                'payload_key' => 'Complemento solucao',
                'ordem_column' => 'Complemento solucao',
                'item_ids' => ['26'],
                'item_codes' => ['4.02', '4.2'],
                'item_titles' => ['Recomendação técnica ou comercial', 'Complemento solucao'],
            ],
            [
                'payload_key' => 'Condições pagto',
                'ordem_column' => 'Condições pagto',
                'item_ids' => [],
                'item_codes' => [],
                'item_titles' => [
                    'Condição de pagamento',
                    'Condicao de pagamento',
                    'Condições de pagamento',
                    'Condicoes de pagamento',
                ],
            ],
        ];
    }

    public function uploadSignature(Request $request)
    {
        try {
            $request->validate([
                'os_id' => 'required',
                'item_code' => 'required',
                'image' => 'required|file|mimes:jpg,jpeg,png', // blob comes as file
            ]);

            $osId = $request->input('os_id');
            // Extract numeric ID only
            $numericId = preg_replace('/[^0-9]/', '', $osId);

            $itemCode = $request->input('item_code');
            $file = $request->file('image');

            // Determine role & Section ID robustly
            $signatureRole = null;
            $sectionId = 7; // Default Fallback
            $resolvedItemId = $itemCode; // Default to input
            $signatureItemMetaByAlias = [];
            $signatureItemTargetsByTopLevelKey = [
                'signature' => null,
                'techSignature' => null,
            ];

            // Try to find item by ID (primary strategy) or Code (legacy)
            $checklistItem = \App\Models\ChecklistItem::find($itemCode);
            if (! $checklistItem) {
                // Try legacy code lookup
                $checklistItem = \App\Models\ChecklistItem::where('code', $itemCode)->first();
            }

            if ($checklistItem) {
                $sectionId = $checklistItem->checklist_section_id;
                $resolvedItemId = $checklistItem->id; // Enforce DB ID Key
                $signatureRole = $this->inferSignatureRole(
                    (string) $checklistItem->id,
                    (string) $checklistItem->code,
                    (string) $checklistItem->title
                );
            } else {
                $signatureRole = $this->inferSignatureRole((string) $itemCode, (string) $itemCode, null);
            }

            $basePath = $numericId;
            $signatureRole = $signatureRole ?? 'cliente';
            $resolvedTargetMeta = $this->buildSignatureTargetMeta(
                (string) $sectionId,
                (string) $resolvedItemId,
                $checklistItem?->code ? (string) $checklistItem->code : (string) $itemCode,
                $checklistItem?->title ? (string) $checklistItem->title : null
            );
            if (is_array($resolvedTargetMeta)) {
                $resolvedTargetMeta['role'] = $signatureRole;
            }
            $topLevelKey = $signatureRole === 'tecnico' ? 'techSignature' : 'signature';
            $path = $this->canonicalSignaturePath($basePath, $resolvedTargetMeta, $topLevelKey);

            $signatureItems = \App\Models\ChecklistItem::where('input_type', 'signature')
                ->select('id', 'code', 'title', 'checklist_section_id')
                ->get();

            foreach ($signatureItems as $signatureItem) {
                $role = $this->inferSignatureRole(
                    (string) $signatureItem->id,
                    (string) $signatureItem->code,
                    (string) $signatureItem->title
                );
                if ($role === null) {
                    continue;
                }

                $meta = [
                    'id' => (string) $signatureItem->id,
                    'code' => (string) $signatureItem->code,
                    'title' => (string) $signatureItem->title,
                    'sectionId' => (string) $signatureItem->checklist_section_id,
                    'role' => $role,
                ];

                $signatureItemMetaByAlias[$meta['id']] = $meta;
                if ($meta['code'] !== '') {
                    $signatureItemMetaByAlias[$meta['code']] = $meta;
                }
                $mappedTopLevelKey = $role === 'tecnico' ? 'techSignature' : 'signature';
                if ($signatureItemTargetsByTopLevelKey[$mappedTopLevelKey] === null) {
                    $signatureItemTargetsByTopLevelKey[$mappedTopLevelKey] = $meta;
                }
            }

            // Ensure directory exists
            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists("{$basePath}/assinaturas")) {
                \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory("{$basePath}/assinaturas");
            }

            // Convert PNG/blob to JPG to standardize
            $imageContent = file_get_contents($file->getRealPath());
            $writeSucceeded = false;
            $image = @imagecreatefromstring($imageContent);
            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);
                $whiteImage = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($whiteImage, 255, 255, 255);
                imagefilledrectangle($whiteImage, 0, 0, $width, $height, $white);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                imagecopy($whiteImage, $image, 0, 0, 0, 0, $width, $height);
                ob_start();
                imagejpeg($whiteImage, null, 85);
                $jpgContent = ob_get_clean();
                imagedestroy($image);
                imagedestroy($whiteImage);
                $writeSucceeded = $jpgContent !== false && $this->replaceLocalImageContents($path, $jpgContent);
            } else {
                $writeSucceeded = $this->replaceLocalImageContents($path, $imageContent);
            }

            if (! $writeSucceeded) {
                \Illuminate\Support\Facades\Log::error("UploadSignature Error: failed to persist signature for OS {$numericId}", [
                    'path' => $path,
                    'item_code' => $itemCode,
                ]);

                return response()->json(['error' => 'Falha ao salvar assinatura no armazenamento'], 500);
            }

            // Update Database JSON with Lock
            $signatureKeepPaths = \Illuminate\Support\Facades\DB::transaction(function () use (
                $numericId,
                $path,
                $itemCode,
                $sectionId,
                $resolvedItemId,
                $signatureItemMetaByAlias,
                $signatureItemTargetsByTopLevelKey,
                $topLevelKey
            ) {
                $ordemApi = \App\Models\OrdemApi::where('numero_ordem', $numericId)->lockForUpdate()->first();
                if ($ordemApi) {
                    $checklist = $ordemApi->checklist_json ?? [];
                    if (! is_array($checklist)) {
                        $checklist = [];
                    }

                    // Save to Correct Section using supplied ID
                    if (! isset($checklist[$sectionId])) {
                        $checklist[$sectionId] = [];
                    }

                    \Illuminate\Support\Facades\Log::info("UploadSignature (Locked) Saving to Section $sectionId Item Resolved: $resolvedItemId (Input: $itemCode)");

                    // Cleanup Old Key if different (Data Normalization)
                    if ($resolvedItemId != $itemCode && isset($checklist[$sectionId][$itemCode])) {
                        unset($checklist[$sectionId][$itemCode]);
                    }

                    $checklist[$sectionId][$resolvedItemId] = $path;
                    $checklist[$topLevelKey] = $path;

                    $signatureSyncResult = $this->synchronizeChecklistSignatureReferences(
                        $checklist,
                        (string) $numericId,
                        $signatureItemMetaByAlias,
                        $signatureItemTargetsByTopLevelKey
                    );

                    $ordemApi->checklist_json = $signatureSyncResult['checklist'];
                    $ordemApi->save();

                    return $signatureSyncResult['paths'];
                }

                return [$path];
            });

            $this->deleteChecklistSignatureFilesExcept($basePath, $signatureKeepPaths);

            return response()->json(['success' => true, 'path' => $path]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('UploadSignature Error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function registrarTarefa(Request $request)
    {
        try {
            $validated = $request->validate([
                'os_id' => 'required',
                'tarefa' => 'nullable|string',
                'tarefa_id' => 'nullable|integer',
                'tecnico' => 'required|string',
            ]);

            $osId = $validated['os_id'];
            $tarefaNome = $validated['tarefa'] ?? null;
            $tarefaId = $validated['tarefa_id'] ?? null;
            $tecnico = $validated['tecnico'];

            // 1. Find Tarefa ID
            if ($tarefaId) {
                $tarefa = \App\Models\Tarefa::find($tarefaId);
            } else {
                $tarefa = \App\Models\Tarefa::where('Descrição tarefa', $tarefaNome)
                    ->orWhere('Descrição tarefa', 'like', '%'.$tarefaNome.'%')
                    ->first();
            }

            if (! $tarefa) {
                return response()->json(['message' => 'Tarefa não encontrada'], 404);
            }

            if ($tarefa->{'Código'} == 12) {
                // Se for Digitação, garante que Orçamento (1) existe antes
                $this->ensureTaskExists($osId, 1, $tecnico);
            }

            $this->ensureTaskExists($osId, $tarefa->{'Código'}, $tecnico);

            return response()->json(['message' => 'Tarefa registrada com sucesso']);

        } catch (\Exception $e) {
            \Log::error('Erro ao registrar tarefa: '.$e->getMessage());

            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
    /**
     * OPTIMIZATION (Round 10): Pre-warmed Image Map.
     * Scans the 'produtos' folder ONCE and builds a SKU→URL map.
     * Cached for 24 hours. Avoids per-request disk I/O.
     *
     * Uses the same logic as Pecas::encontrarImagemProduto:
     * - Extracts ALL consecutive numeric segments from the filename
     * - e.g., "1-arruela-encosto-hidromar-28-e-30-mm-bh6100 (1).jpeg" → SKU "1"
     * - e.g., "29178-c-pia-gaxeta-descarga..." → SKU "29178"
     */
    private function getImageMap(): array
    {
        return Cache::remember('produto_image_map_v2', 86400, function () {
            $map = [];
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $files = $disk->allFiles('produtos');
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowedExt)) {
                    continue;
                }

                $filename = pathinfo($file, PATHINFO_FILENAME);
                // Split by -, _, or space and extract leading numeric parts
                $parts = preg_split('/[-_ ]/', strtolower($filename));
                $numericPrefixes = [];
                foreach ($parts as $part) {
                    // Strip parentheses like "(1)" from the last part
                    $clean = preg_replace('/[^0-9]/', '', $part);
                    if ($clean !== '' && preg_match('/^[0-9]+$/', $clean)) {
                        $numericPrefixes[] = $clean;
                    } else {
                        break; // Stop at first non-numeric segment
                    }
                }

                $url = asset('storage/'.$file);
                foreach ($numericPrefixes as $sku) {
                    // Only store first match per SKU
                    if (! isset($map[$sku])) {
                        $map[$sku] = $url;
                    }
                }
            }

            return $map;
        });
    }

    private function syncMobileSessionTasks(
        int $numeroOrdem,
        $previousChecklist,
        $currentChecklist,
        ?string $status,
        string $tecnico
    ): void {
        $previousSessions = $this->extractMobileWorkSessions($previousChecklist);
        $currentSessions = $this->extractMobileWorkSessions($currentChecklist);

        $previousSessionsByStart = [];
        foreach ($previousSessions as $session) {
            $start = $session['start'] ?? null;
            if ($start) {
                $previousSessionsByStart[$start] = $session;
            }
        }

        $normalizedStatus = strtoupper(trim((string) $status));
        $finalCheckoutIndex = $this->resolveFinalCheckoutSessionIndex($currentChecklist, $currentSessions, $normalizedStatus);
        $checkoutTaskCreated = false;
        $assistenciaCarroTaskCode = $this->resolveAssistenciaCarTaskCode($numeroOrdem);

        foreach ($currentSessions as $index => $session) {
            $start = $session['start'] ?? null;
            $end = $session['end'] ?? null;
            $previousSession = $start ? ($previousSessionsByStart[$start] ?? null) : null;
            $previousEnd = $previousSession['end'] ?? null;

            if ($start && ! $previousSession) {
                $this->createMobileSessionTaskEvent($numeroOrdem, 203, $tecnico, $start);

                if ($assistenciaCarroTaskCode !== null) {
                    $this->createMobileSessionTaskEvent($numeroOrdem, $assistenciaCarroTaskCode, $tecnico, $start);
                }
            }

            if ($end && $end !== $previousEnd) {
                $taskCode = ($normalizedStatus === 'COMPLETED' && $index === $finalCheckoutIndex) ? 204 : 205;
                $this->createMobileSessionTaskEvent($numeroOrdem, $taskCode, $tecnico, $end);
                $checkoutTaskCreated = $checkoutTaskCreated || $taskCode === 204;
            }
        }

        if ($normalizedStatus === 'COMPLETED' && ! $checkoutTaskCreated) {
            $previousChecklistArray = $this->normalizeChecklistPayloadArray($previousChecklist);
            $currentChecklistArray = $this->normalizeChecklistPayloadArray($currentChecklist);
            $previousCheckOut = $this->normalizeMobileEventTimestamp($previousChecklistArray['times']['checkOut'] ?? null);
            $currentCheckOut = $this->normalizeMobileEventTimestamp($currentChecklistArray['times']['checkOut'] ?? null);

            if ($currentCheckOut && $currentCheckOut !== $previousCheckOut) {
                $this->createMobileSessionTaskEvent($numeroOrdem, 204, $tecnico, $currentCheckOut);
            }
        }
    }

    private function extractMobileWorkSessions($checklist): array
    {
        $checklistArray = $this->normalizeChecklistPayloadArray($checklist);
        $times = isset($checklistArray['times']) && is_array($checklistArray['times'])
            ? $checklistArray['times']
            : [];

        $workSessions = isset($times['workSessions']) && is_array($times['workSessions'])
            ? $times['workSessions']
            : [];

        if (empty($workSessions) && ! empty($times['checkIn'])) {
            $workSessions[] = [
                'start' => $times['checkIn'],
                'end' => $times['checkOut'] ?? null,
            ];
        }

        $normalizedSessions = [];

        foreach ($workSessions as $session) {
            if (! is_array($session)) {
                continue;
            }

            $start = $this->normalizeMobileEventTimestamp($session['start'] ?? null);
            if (! $start) {
                continue;
            }

            $normalizedSessions[] = [
                'start' => $start,
                'end' => $this->normalizeMobileEventTimestamp($session['end'] ?? null),
            ];
        }

        return $normalizedSessions;
    }

    private function resolveFinalCheckoutSessionIndex($checklist, array $sessions, string $status): ?int
    {
        if ($status !== 'COMPLETED' || empty($sessions)) {
            return null;
        }

        $checklistArray = $this->normalizeChecklistPayloadArray($checklist);
        $times = isset($checklistArray['times']) && is_array($checklistArray['times'])
            ? $checklistArray['times']
            : [];

        $finalCheckout = $this->normalizeMobileEventTimestamp($times['checkOut'] ?? null);

        if ($finalCheckout) {
            for ($index = count($sessions) - 1; $index >= 0; $index--) {
                if (($sessions[$index]['end'] ?? null) === $finalCheckout) {
                    return $index;
                }
            }
        }

        for ($index = count($sessions) - 1; $index >= 0; $index--) {
            if (! empty($sessions[$index]['end'])) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeChecklistPayloadArray($checklist): array
    {
        if (is_array($checklist)) {
            return $checklist;
        }

        if (is_string($checklist)) {
            $decoded = json_decode($checklist, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeMobileEventTimestamp($timestamp): ?string
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($timestamp)->utc()->toIso8601String();
        } catch (\Throwable $e) {
            return trim($timestamp);
        }
    }

    private function resolveAssistenciaCarTaskCode(int $numeroOrdem): ?int
    {
        try {
            $setor = \Illuminate\Support\Str::lower(
                \Illuminate\Support\Str::ascii(
                    trim((string) Ordem::where('Numero ordem', $numeroOrdem)->value('Setor'))
                )
            );

            if ($setor !== 'assistencia') {
                return null;
            }

            $carroId = \App\Models\OrdemApi::where('numero_ordem', $numeroOrdem)->value('carro_id');
            if (! $carroId) {
                \Log::info("ℹ️ [AppSync] Check-in da OS {$numeroOrdem} sem carro vinculado. Tarefa de assistência por carro ignorada.");

                return null;
            }

            $carro = Carro::find($carroId);
            if (! $carro) {
                \Log::warning("⚠️ [AppSync] Carro {$carroId} não encontrado para OS {$numeroOrdem}.");

                return null;
            }

            $carLabels = collect([
                $carro->apelido ?? null,
                $carro->placa ?? null,
                $carro->modelo ?? null,
            ])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($carLabels === []) {
                return null;
            }

            $task = $this->findAssistenciaTaskByCarLabels($carLabels);
            if (! $task) {
                \Log::warning("⚠️ [AppSync] Nenhuma tarefa de assistência por carro encontrada para OS {$numeroOrdem}.", [
                    'carro_id' => $carroId,
                    'car_labels' => $carLabels,
                ]);

                return null;
            }

            return (int) $task['codigo'];
        } catch (\Throwable $e) {
            \Log::error("💥 [AppSync] Falha ao resolver tarefa de assistência por carro da OS {$numeroOrdem}", [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function findAssistenciaTaskByCarLabels(array $carLabels): ?array
    {
        static $assistenciaTasks = null;

        if ($assistenciaTasks === null) {
            $assistenciaTasks = DB::table('Tarefas')
                ->select('Código', 'Descrição tarefa')
                ->get()
                ->map(function ($task) {
                    $descricao = trim((string) ($task->{'Descrição tarefa'} ?? ''));

                    return [
                        'codigo' => (int) ($task->{'Código'} ?? 0),
                        'descricao' => $descricao,
                        'normalized' => $this->normalizeAssistenciaTaskLookup($descricao),
                        'compact' => $this->normalizeAssistenciaTaskLookup($descricao, true),
                    ];
                })
                ->filter(fn ($task) => $task['codigo'] > 0 && str_contains($task['normalized'], 'assistencia'))
                ->values()
                ->all();
        }

        $bestMatch = null;
        $bestScore = -1;

        foreach ($carLabels as $label) {
            $normalizedLabel = $this->normalizeAssistenciaTaskLookup($label);
            $compactLabel = $this->normalizeAssistenciaTaskLookup($label, true);

            if ($normalizedLabel === '' || $compactLabel === '') {
                continue;
            }

            foreach ($assistenciaTasks as $task) {
                $score = 0;

                if ($task['normalized'] === "assistencia {$normalizedLabel}") {
                    $score = 500;
                } elseif (str_starts_with($task['normalized'], "assistencia {$normalizedLabel}")) {
                    $score = 400;
                } elseif (str_contains($task['normalized'], $normalizedLabel)) {
                    $score = 300;
                } elseif (str_contains($task['compact'], 'assistencia'.$compactLabel)) {
                    $score = 250;
                } elseif (str_contains($task['compact'], $compactLabel)) {
                    $score = 200;
                }

                if ($score === 0) {
                    continue;
                }

                $score += min(strlen($compactLabel), 50);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $task;
                }
            }
        }

        return $bestMatch;
    }

    private function normalizeAssistenciaTaskLookup($value, bool $compact = false): string
    {
        $normalized = \Illuminate\Support\Str::of((string) ($value ?? ''))
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', $compact ? '' : ' ')
            ->trim();

        if ($compact) {
            return $normalized->value();
        }

        return preg_replace('/\s+/', ' ', $normalized->value()) ?: '';
    }

    private function createMobileSessionTaskEvent(
        int $numeroOrdem,
        int $tarefaCodigo,
        string $tecnico,
        string $timestamp
    ): bool {
        try {
            return DB::transaction(function () use ($numeroOrdem, $tarefaCodigo, $tecnico, $timestamp) {
                $eventAt = \Illuminate\Support\Carbon::parse($timestamp)->setTimezone(config('app.timezone'));
                $descricao = $eventAt->format('d/m/Y H:i');

                // Prevent duplicate insertions (SQL Server PK Violation protection)
                $exists = DB::table('Ordens técnicos')
                    ->where('Numero ordem', $numeroOrdem)
                    ->where('Tarefa', $tarefaCodigo)
                    ->where('Data início conserto', $eventAt->toDateString())
                    ->where('Hora inicio conserto', $eventAt->format('H:i:s'))
                    ->exists();

                if ($exists) {
                    \Log::info("🧩 [AppSync] Evento mobile {$tarefaCodigo} para OS {$numeroOrdem} já registrado, ignorando duplicata.", [
                        'timestamp' => $timestamp
                    ]);
                    return true;
                }

                $taskLine = (int) DB::table('Ordens técnicos')
                    ->where('Numero ordem', $numeroOrdem)
                    ->count() + 1;
                $num = ((int) DB::table('Ordens técnicos')
                    ->where('Numero ordem', $numeroOrdem)
                    ->max('Num')) + 1;

                DB::table('Ordens técnicos')->insert([
                    'Numero ordem' => $numeroOrdem,
                    'Num' => $num,
                    'Técnico' => $tecnico,
                    'Tarefa' => $tarefaCodigo,
                    'Pontuação' => 0,
                    'Pontuação informada' => 0,
                    'Valor comissão' => 0.00,
                    'Data início conserto' => $eventAt->toDateString(),
                    'Hora inicio conserto' => $eventAt->format('H:i:s'),
                    'Data fim conserto' => $eventAt->toDateString(),
                    'Hora fim conserto' => $eventAt->format('H:i:s'),
                    'Data emissão' => $eventAt->toDateString(),
                    'Situacao' => 'ANDAMENTO',
                ]);

                $this->upsertSysSequencial('Ordens técnicos', 'Num', (string) $numeroOrdem, $num);

                $sequenciaAtendimento = ((int) DB::table('Ordens atendimento')
                    ->where('Numero ordem', $numeroOrdem)
                    ->max('Sequencia')) + 1;

                DB::table('Ordens atendimento')->insert([
                    'Numero ordem' => $numeroOrdem,
                    'Sequencia' => $sequenciaAtendimento,
                    'Data emissão' => $eventAt->toDateTimeString(),
                    'Comentários' => sprintf('(%d)%s', $taskLine, $descricao),
                    'Tecnico' => $tecnico,
                    'Operador' => $tecnico,
                ]);

                $this->upsertSysSequencial('Ordens atendimento', 'Sequencia', (string) $numeroOrdem, $sequenciaAtendimento);

                \Log::info("🧩 [AppSync] Evento mobile {$tarefaCodigo} registrado para OS {$numeroOrdem}", [
                    'descricao' => $descricao,
                    'tecnico' => $tecnico,
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            // Se o erro for de duplicidade (PK Violation / Error 23000 no SQL Server)
            // consideraremos sucesso pois o evento já está registrado.
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), '23000')) {
                \Log::info("♻️ [AppSync] Evento mobile {$tarefaCodigo} já estava registrado para OS {$numeroOrdem}. Ignorando duplicata.");
                return true;
            }

            \Log::error("💥 [AppSync] Falha ao registrar evento mobile {$tarefaCodigo} para OS {$numeroOrdem}", [
                'timestamp' => $timestamp,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        }
    }

    /**
     * Garante que uma tarefa específica existe para a ordem.
     */
    private function ensureTaskExists($no_ordem, $tarefaCodigo, $tecnico = 'API')
    {
        try {
            return DB::transaction(function () use ($no_ordem, $tarefaCodigo, $tecnico) {
                // 1. Verifica se já existe
                $exists = DB::table('Ordens técnicos')
                    ->where('Numero ordem', $no_ordem)
                    ->where('Tarefa', $tarefaCodigo)
                    ->exists();

                if ($exists) {
                    return false;
                }

                // 2. Não existe -> Cria
                $now = now();
                $num = (DB::table('Ordens técnicos')->where('Numero ordem', $no_ordem)->max('Num') ?? 0) + 1;

                DB::table('Ordens técnicos')->insert([
                    'Numero ordem' => $no_ordem,
                    'Num' => $num,
                    'Técnico' => $tecnico, // Using the $tecnico parameter passed to the function
                    'Tarefa' => $tarefaCodigo,
                    'Pontuação' => 0,
                    'Pontuação informada' => 0,
                    'Valor comissão' => 0.00,
                    'Data início conserto' => $now->toDateString(),
                    'Hora inicio conserto' => $now->format('H:i:s'),
                    'Data fim conserto' => $now->toDateString(),
                    'Hora fim conserto' => $now->format('H:i:s'),
                    'Data emissão' => $now->toDateString(),
                    'Situacao' => 'ANDAMENTO',
                ]);

                // Atualiza sequencial
                DB::table('SYS~Sequencial')->updateOrInsert(
                    ['SYS~Tabela' => 'Ordens técnicos', 'SYS~Campo' => 'Num', 'SYS~Chave' => (string) $no_ordem],
                    ['SYS~Valor' => $num, 'SYS~BD' => 'DADOSPGI']
                );

                \Log::info("🧩 [AppSync] Tarefa {$tarefaCodigo} criada para OS {$no_ordem}.");

                return true;
            });
        } catch (\Exception $e) {
            \Log::error('💀 [AppSync] Erro ao garantir tarefa.', [
                'no_ordem' => $no_ordem,
                'tarefa' => $tarefaCodigo,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function buscaPecaMobile(Request $request)
    {
        $osId = $request->input('os_id');

        // Generate a cache key based on the parameters
        $cacheParams = $request->only(['sku', 'descricao', 'cod', 'obs', 'busca_mobile', 'exact', 'limit', 'page']);
        ksort($cacheParams);
        $cacheKey = 'busca_peca_full_v3_'.md5(json_encode($cacheParams));

        // Cache for 30 minutes
        $data = Cache::remember($cacheKey, 1800, function () use ($request) {
            // OPTIMIZED QUERY directly in AppSync to leverage specific indexes
            // instead of the generic logic in OrcamentoController
            $query = DB::table('Pecas')
                ->select(
                    DB::raw('[Codigo peca] as codigo'),
                    DB::raw('[Descricao peca] as descricao'),
                    DB::raw('[Peça ativa] as ativa'),
                    DB::raw('[Código fabricante] as cod_fabricante'),
                    DB::raw('[Localizacao] as localizacao'),
                    DB::raw('[Obs] as obs'),
                    DB::raw('[Qtde] as qtde'),
                    DB::raw('[Custo unitário] as custo_unitario'),
                    DB::raw('[Preço unitário1] as preco1'),
                    DB::raw('[Preço unitário2] as preco2'),
                    DB::raw('[Peso bruto] as peso_bruto'),
                    DB::raw('[Peso liquido] as peso_liquido'),
                    DB::raw('[Situação tributária] as cst'),
                    DB::raw('[Icms] as icms'),
                    DB::raw('[Reducao base] as reducao_base')
                );

            $buscaMobile = trim($request->input('busca_mobile'));

            if ($buscaMobile) {
                // Remove special chars that might break LIKE or be irrelevant
                // except spaces and alphanumeric
                $cleanSearch = preg_replace('/[^a-zA-Z0-9\s]/', '', $buscaMobile);
                $terms = array_values(array_filter(explode(' ', $cleanSearch)));

                // Smart Search Logic:
                // 1. If it looks like a SKU (pure number or specific format), prioritize specific columns
                // 2. Otherwise, assume description search

                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        if (empty($term)) {
                            continue;
                        }

                        $q->where(function ($sub) use ($term) {
                            $contains = '%'.$term.'%';

                            // Improved Logic (Round 14): Check ONLY SKU and Description
                            // Modified by user to include Manufacturer Code and Obs
                            $sub->orWhere('Descricao peca', 'like', $contains)
                                ->orWhere('Codigo peca', 'like', $contains)
                                ->orWhere('Código fabricante', 'like', $contains)
                                ->orWhere('Obs', 'like', $contains);
                        });
                    }
                });
            } else {
                // Fallback for legacy parameters if busca_mobile is empty (unlikely in this flow)
                $sku = $request->input('sku');
                if ($sku) {
                    $skuNormalizado = \App\Models\Pecas::normalizeCodigoPeca($sku);

                    if ($skuNormalizado === null) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('Codigo peca', $skuNormalizado);
                    }
                }
            }

            // ORDER BY PRIORITY (Round 17):
            // Clear any default ordering
            $query->reorder();

            // 1. Exact SKU Match
            // 2. SKU Starts With Term
            // 3. Others (Description matches)
            if (! empty($terms)) {
                $priorityTerm = $terms[0]; // Use first term for priority (e.g. "20")

                // CRITICAL FIX: Only apply numeric SKU sort if the term is actually numeric.
                // Otherwise, SQL Server throws "Conversion failed" when comparing text to int column.
                if (ctype_digit($priorityTerm)) {
                    $query->orderByRaw('
                        CASE 
                            WHEN [Codigo peca] = ? THEN 1 
                            WHEN [Codigo peca] LIKE ? THEN 2 
                            ELSE 3 
                        END
                    ', [$priorityTerm, "{$priorityTerm}%"]);
                } else {
                    // Default sort for text searches
                    $query->orderBy('Descricao peca', 'asc');
                }
            } else {
                $query->orderBy('Descricao peca', 'asc');
            }

            $limit = $request->input('limit', 100);
            $page = $request->input('page', 1);

            // Fetch generic result object
            // Use simplePaginate to avoid SELECT count(*) which is very slow on large LIKE queries
            $data = $query->simplePaginate($limit, ['*'], 'page', $page)->items();

            // Convert to array of objects (if not already) for consistency with previous map logic
            // (paginate returns generic objects which is fine)

            // --- INJECT IMAGE URLS (OPTIMIZED - Round 10: Pre-warmed map) ---
            $imageMap = $this->getImageMap();

            return collect($data)->map(function ($item) use ($imageMap) {
                $sku = $item->codigo ?? $item->cod_fabricante ?? null;
                $item->image_url = null;

                if ($sku) {
                    $skuStr = (string) $sku;
                    $item->image_url = $imageMap[$skuStr] ?? null;
                }

                return $item;
            });
        });

        // Se encontrou algo e temos o OS ID, garante a tarefa 1 (ORÇAMENTO) depois 12 (DIGITAÇÃO)
        if ($osId && isset($data) && count($data) > 0) {
            $user = Auth::user();
            $funcionario = \App\Models\Funcionarios::where('Email', $user->email)->first();
            $tecnico = $funcionario ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'}) : 'API';

            // Ordem correta: 1 depois 12 (as per user requirement)
            $this->ensureTaskExists($osId, 1, $tecnico);
            $this->ensureTaskExists($osId, 12, $tecnico);
        }

        return response()->json($data);
    }

    public function buscaServicoMobile(Request $request)
    {
        $buscaMobile = trim($request->input('busca_mobile'));

        // Generate a cache key based on the parameters
        $cacheParams = $request->only(['busca_mobile', 'limit', 'page']);
        ksort($cacheParams);
        $cacheKey = 'busca_servico_mobile_'.md5(json_encode($cacheParams));

        $data = Cache::remember($cacheKey, 1800, function () use ($request, $buscaMobile) {
            $query = DB::table('DADOSPGI.dbo.Custos')
                ->select(
                    DB::raw('[Código] as codigo'),
                    DB::raw('[Descrição custos] as descricao'),
                    DB::raw('[Valor] as preco'),
                    DB::raw('[Iss] as iss') // Added 'Iss'
                );

            if ($buscaMobile) {
                // Remove special chars that might break LIKE or be irrelevant
                $cleanSearch = preg_replace('/[^a-zA-Z0-9\s]/', '', $buscaMobile);
                $terms = array_values(array_filter(explode(' ', $cleanSearch)));

                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        if (empty($term)) {
                            continue;
                        }

                        $q->where(function ($sub) use ($term) {
                            $contains = '%'.$term.'%';
                            $sub->orWhere('Descrição custos', 'like', $contains)
                                ->orWhere('Código', 'like', $contains);
                        });
                    }
                });
            }

            $query->orderBy('Descrição custos', 'asc');

            $limit = $request->input('limit', 100);
            $page = $request->input('page', 1);

            return $query->simplePaginate($limit, ['*'], 'page', $page)->items();
        });

        return response()->json($data);
    }

    public function syncParts(Request $request, $id)
    {
        // Using "Numero ordem" from ID (handling potential "OS-" prefix if passed)
        $numeroOrdem = preg_replace('/[^0-9]/', '', $id);
        $pecasFront = $request->input('pecas', []);

        \Illuminate\Support\Facades\Log::info('🔍 [APPSYNC SYNC] Requisição recebida', [
            'ordem' => $numeroOrdem,
            'payload_count' => count($pecasFront),
        ]);

        try {
            DB::beginTransaction();

            $ordem = \App\Models\Ordem::where('Numero ordem', $numeroOrdem)->firstOrFail();
            $cliente = \App\Models\Cliente::findOrFail($ordem->{'Codigo cliente'});
            $ufCliente = $cliente->Uf;

            // --- Log Histórico Parte 1: Obter peças atuais ---
            $pecasAntigasRows = \App\Models\OrdemPeca::where('Numero ordem', $numeroOrdem)
                ->get();
            $pecasAntigas = $pecasAntigasRows
                ->keyBy('Código fabricante');

            // Remove all current parts
            \App\Models\OrdemPeca::where('Numero ordem', $numeroOrdem)->delete();

            $insertedCount = 0;
            $pecasNovasLog = [];
            foreach ($pecasFront as $index => $p) {
                // Front sends 'codigo' which is now the ID (Int)
                $codigoId = trim($p['codigo'] ?? '');
                $qtde = $p['qtde'] ?? 0;

                if (empty($codigoId) || empty($qtde)) {
                    continue;
                }

                // 1. Try to find by SKU (Codigo peca) - Try exact match first
                $peca = \App\Models\Pecas::where('Codigo peca', $codigoId)->first();

                // 2. Fallback: Try by Manufacturer Code if numerical lookup failed or not found
                if (! $peca) {
                    $peca = \App\Models\Pecas::where('Código fabricante', $codigoId)->first();
                }

                if (! $peca) {
                    \Illuminate\Support\Facades\Log::warning("⚠️ [APPSYNC] Peça não encontrada pelo SKU/Code '{$codigoId}' para OS {$numeroOrdem}");

                    continue; // Skip invalid parts
                }

                \Illuminate\Support\Facades\Log::info("✅ [APPSYNC] Sincronizando peça: {$peca->{'Descricao peca'}} (SKU: {$codigoId}, Qtd: {$qtde})");

                $pecasNovasLog[$peca->{'Código fabricante'}] = trim($peca->{'Descricao peca'});
                $pecaAntiga = $pecasAntigas->get($peca->{'Código fabricante'});
                $qtdUtilizadaAnterior = $pecaAntiga ? (float) ($pecaAntiga->{'Qtde utilizada'} ?? 0) : 0;
                $dataEntregaAnterior = $pecaAntiga->{'Data entrega'} ?? null;
                $qtdeFinal = max((float) $qtde, $qtdUtilizadaAnterior);

                // Calculate Data (Prices, Taxes)
                $data = $this->calculatePartData($peca, $qtdeFinal, $ufCliente);

                $novaPeca = new \App\Models\OrdemPeca;
                $novaPeca->{'Numero ordem'} = $numeroOrdem;
                $novaPeca->Item = $insertedCount + 1;
                $novaPeca->{'Peça'} = $peca->{'Descricao peca'};
                $novaPeca->{'Código fabricante'} = $peca->{'Código fabricante'}; // Salva o string correto do banco
                $novaPeca->Qtde = $data['quantidade'];
                $novaPeca->{'Valor tabela'} = $data['preco_unitario'];
                $novaPeca->{'Valor informado'} = 0; // Explicitly zeroed as requested
                $novaPeca->{'Valor item'} = $data['valor_total'];
                $novaPeca->{'Preço custo'} = $data['preco_custo'];
                $novaPeca->{'Codigo fornecedor'} = $peca->Fornecedor ?? 0;
                $novaPeca->{'Tipo preco'} = 1;
                $novaPeca->{'Cst icms'} = $data['cst'];
                $novaPeca->Icms1 = $data['icms_percent'];
                $novaPeca->{'Reducao base'} = $data['reducao_base'];
                $novaPeca->{'Valor icms'} = $data['valor_icms'];
                $novaPeca->{'Qtde utilizada'} = $qtdUtilizadaAnterior;
                $novaPeca->{'Nao estoque'} = 0;
                $novaPeca->{'Não utilizada'} = 0;
                $novaPeca->{'Reserva'} = $qtdUtilizadaAnterior > 0 ? 'B' : '';
                $novaPeca->{'Data entrega'} = $dataEntregaAnterior;
                $novaPeca->save();

                $insertedCount++;
            }

            // --- Log Histórico Parte 2: Comparar e registrar ---
            $user = Auth::user();
            $funcionario = \App\Models\Funcionarios::where('Email', $user->email)->first();
            $vendedor = $funcionario ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'}) : 'Mobile App';

            // Peças adicionadas
            foreach ($pecasNovasLog as $sku => $nome) {
                if (! $pecasAntigas->has($sku)) {
                    OrdensHistorico::create([
                        'cod_ordem' => $numeroOrdem,
                        'vendedor' => $vendedor,
                        'acao' => 'ADIÇÃO',
                        'detalhes' => "[Mobile] Adicionou produto #{$sku}",
                    ]);
                }
            }

            // Peças removidas
            foreach ($pecasAntigas as $sku => $p) {
                if (! isset($pecasNovasLog[$sku])) {
                    app(OrdemSeparacaoSelectionService::class)->returnSeparatedStockForRows(
                        ordemNumero: $numeroOrdem,
                        rows: [$p],
                        responsavel: $vendedor
                    );

                    OrdensHistorico::create([
                        'cod_ordem' => $numeroOrdem,
                        'vendedor' => $vendedor,
                        'acao' => 'EXCLUSÃO',
                        'detalhes' => "[Mobile] Removeu produto #{$sku}",
                    ]);
                }
            }

            // Recalculate OS Totals (Essential for Consistency)
            $this->recalcularTotaisOs($numeroOrdem);

            // Ensure OS progresses to 'ORCADA' and then 'DIGITAÇÃO'
            // 1. Set budget date if not present
            if (!$ordem->{'Data orçamento'}) {
                DB::table('Ordens')
                    ->where('Numero ordem', $numeroOrdem)
                    ->update(['Data orçamento' => now()->format('Y-m-d')]);
            }

            $tecnico = $funcionario ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'}) : 'API';

            // 2. Ensure Task 1 (PRÉ ORÇAMENTO) happens BEFORE Task 12 (DIGITAÇÃO)
            $this->ensureTaskExists($numeroOrdem, 1, $tecnico);
            $this->ensureTaskExists($numeroOrdem, 12, $tecnico);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Peças salvas com sucesso! ({$insertedCount} itens)",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erro ao sincronizar peças (AppSync):', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncServices(Request $request, $id)
    {
        $numeroOrdem = preg_replace('/[^0-9]/', '', $id);
        $servicosFront = $request->input('servicos', []);

        \Illuminate\Support\Facades\Log::info('🔍 [APPSYNC SYNC SERVICES] Requisição recebida', [
            'ordem' => $numeroOrdem,
            'payload_count' => count($servicosFront),
        ]);

        try {
            DB::beginTransaction();

            $ordem = \App\Models\Ordem::where('Numero ordem', $numeroOrdem)->firstOrFail();

            // --- Log Histórico Parte 1: Obter serviços atuais ---
            $servicosAntigos = \App\Models\OrdemMaodeObra::where('Numero ordem', $numeroOrdem)
                ->get(['Descrição serviços'])
                ->pluck('Descrição serviços')
                ->toArray();

            // Remove all current services
            \App\Models\OrdemMaodeObra::where('Numero ordem', $numeroOrdem)->delete();

            $insertedCount = 0;
            $servicosNovosLog = [];
            foreach ($servicosFront as $index => $s) {
                $descricao = $s['descricao'] ?? '';
                $qtde = $s['qtde'] ?? 0;
                $preco = $s['preco'] ?? 0;

                if (empty($descricao) || empty($qtde)) {
                    continue;
                }

                $novoSv = new \App\Models\OrdemMaodeObra;
                $novoSv->{'Numero ordem'} = $numeroOrdem;
                $novoSv->Item = $insertedCount + 1;
                $novoSv->{'Descrição serviços'} = $descricao;
                $novoSv->Qtde = $qtde;
                $novoSv->{'Valor unitario'} = $preco;
                $valorInformado = (float) ($s['valor_informado'] ?? 0);
                $usarValorInformado = filter_var($s['usar_valor_informado'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $precoEfetivo = $usarValorInformado ? $valorInformado : $preco;
                $novoSv->{'Valor total'} = $qtde * $precoEfetivo;
                $novoSv->Iss = $s['iss'] ?? 0;
                $novoSv->{'Valor informado'} = $usarValorInformado ? $valorInformado : 0;
                $novoSv->{'No os cliente'} = '';
                $novoSv->Feito = 0;
                $novoSv->save();

                $servicosNovosLog[] = $descricao;
                $insertedCount++;
            }

            // --- Log Histórico Parte 2: Registrar mudanças ---
            $user = Auth::user();
            $funcionario = \App\Models\Funcionarios::where('Email', $user->email)->first();
            $vendedor = $funcionario ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'}) : 'Mobile App';

            // Serviços adicionados
            foreach ($servicosNovosLog as $nome) {
                if (! in_array($nome, $servicosAntigos)) {
                    OrdensHistorico::create([
                        'cod_ordem' => $numeroOrdem,
                        'vendedor' => $vendedor,
                        'acao' => 'ADIÇÃO',
                        'detalhes' => "[Mobile] Adicionou serviço: {$nome}",
                    ]);
                }
            }

            // Serviços removidos
            foreach ($servicosAntigos as $nome) {
                if (! in_array($nome, $servicosNovosLog)) {
                    OrdensHistorico::create([
                        'cod_ordem' => $numeroOrdem,
                        'vendedor' => $vendedor,
                        'acao' => 'EXCLUSÃO',
                        'detalhes' => "[Mobile] Removeu serviço: {$nome}",
                    ]);
                }
            }

            // Recalculate OS Totals
            $this->recalcularTotaisOs($numeroOrdem);

            // Ensure OS progresses to 'ORCADA' and then 'DIGITAÇÃO'
            // 1. Set budget date if not present
            if (!$ordem->{'Data orçamento'}) {
                DB::table('Ordens')
                    ->where('Numero ordem', $numeroOrdem)
                    ->update(['Data orçamento' => now()->format('Y-m-d')]);
            }

            $tecnico = $funcionario ? ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'}) : 'API';

            // 2. Ensure Task 1 (PRÉ ORÇAMENTO) happens BEFORE Task 12 (DIGITAÇÃO)
            $this->ensureTaskExists($numeroOrdem, 1, $tecnico);
            $this->ensureTaskExists($numeroOrdem, 12, $tecnico);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Serviços salvos com sucesso! ({$insertedCount} itens)",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erro ao sincronizar serviços (AppSync):', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncPartsSeparation(Request $request, $id)
    {
        $numeroOrdem = preg_replace('/[^0-9]/', '', (string) $id);

        \Illuminate\Support\Facades\Log::info('[MOBILE][SEPARACAO][OS] Recebendo solicitação', [
            'ordem' => $numeroOrdem,
            'origem' => $request->input('origem'),
            'itens_count' => is_array($request->input('itens')) ? count($request->input('itens')) : null,
        ]);

        $validated = $request->validate([
            'origem' => 'nullable|in:all,selected',
            'itens' => 'required|array|min:1',
            'itens.*.item' => 'nullable',
            'itens.*.sku' => 'nullable|string',
            'itens.*.quantidade_os' => 'nullable|numeric',
            'itens.*.quantidade_utilizada' => 'nullable|numeric',
            'itens.*.quantidade_separar' => 'required|numeric|min:0',
            'prioridade' => 'nullable|boolean',
        ]);

        Ordem::where('Numero ordem', $numeroOrdem)->firstOrFail();

        $responsavel = Funcionarios::where('Email', auth()->user()->email)->value('Nome conhecido')
            ?: (auth()->user()->name ?? 'Mobile App');

        $tarefaSolicitacao = DB::table('DADOSPGI.dbo.Tarefas')
            ->where('Descrição tarefa', 'like', '%SOLICITAÇÃO DE PEÇAS%')
            ->first();
        $tarefaSeparacaoId = $tarefaSolicitacao ? $tarefaSolicitacao->{'Código'} : 91;

        $now = now();
        $nextNum = (DB::table('Ordens técnicos')->where('Numero ordem', $numeroOrdem)->max('Num') ?? 0) + 1;

        DB::table('Ordens técnicos')->insert([
            'Numero ordem' => $numeroOrdem,
            'Num' => $nextNum,
            'Técnico' => $responsavel,
            'Tarefa' => $tarefaSeparacaoId,
            'Pontuação' => 0.00,
            'Pontuação informada' => 0.00,
            'Valor comissão' => 0.00,
            'Data início conserto' => $now->toDateString(),
            'Hora inicio conserto' => $now->format('H:i:s'),
            'Data fim conserto' => $now->toDateString(),
            'Hora fim conserto' => $now->format('H:i:s'),
            'Data emissão' => $now->toDateString(),
            'Situacao' => 'ANDAMENTO',
        ]);

        DB::table('SYS~Sequencial')->updateOrInsert(
            [
                'SYS~BD' => 'DADOSPGI',
                'SYS~Tabela' => 'Ordens técnicos',
                'SYS~Campo' => 'Num',
                'SYS~Chave' => (string) $numeroOrdem,
            ],
            [
                'PW~Projeto' => '',
                'SYS~Valor' => $nextNum,
                'SYS~ValorAnterior' => $nextNum - 1,
                'SYS~Estacao' => 'INTRANET',
                'SYS~Identificacao' => '',
                'SYS~Pendentes' => 1,
            ]
        );

        $itensAtualizados = app(OrdemSeparacaoSelectionService::class)->saveSelection(
            ordemNumero: $numeroOrdem,
            items: $validated['itens'],
            origem: $validated['origem'] ?? 'selected',
            responsavel: $responsavel
        );

        $prioridade = (bool) ($validated['prioridade'] ?? false);
        if ($prioridade) {
            DB::table('Ordens atendimento')->insert([
                'Numero ordem' => $numeroOrdem,
                'Sequencia' => $nextNum,
                'Data emissão' => $now->toDateString(),
                'Tecnico' => $responsavel,
                'Comentários' => 'PRIORIDADE',
                'Operador' => $responsavel,
            ]);

            DB::table('Ordens')
                ->where('Numero ordem', $numeroOrdem)
                ->update(['Prioridade' => 1]);
        }

        $itensLog = collect($validated['itens'])
            ->map(fn ($item) => trim((string) ($item['sku'] ?? 'Item')).': '.(float) ($item['quantidade_separar'] ?? 0).' un')
            ->values()
            ->all();

        OrdensHistorico::create([
            'cod_ordem' => $numeroOrdem,
            'vendedor' => $responsavel,
            'acao' => 'SEPARAÇÃO',
            'detalhes' => 'Pedido de separação finalizado || { "changes": '.json_encode($itensLog).' }',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Separação registrada com sucesso.',
            'ordem_numero' => $numeroOrdem,
            'itens_atualizados' => $itensAtualizados,
        ]);
    }

    private function normalizeChecklistArray($checklist): array
    {
        if (is_array($checklist)) {
            return $checklist;
        }

        if (is_string($checklist)) {
            $decoded = json_decode($checklist, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildChecklistHistoryChanges(
        array $oldChecklist,
        array $newChecklist,
        array $itemTitleMap = [],
        ?string $oldStatus = null,
        ?string $newStatus = null,
        array $checklistAliases = []
    ): array {
        $sectionAliases = is_array($checklistAliases['sections'] ?? null) ? $checklistAliases['sections'] : [];
        $itemAliases = is_array($checklistAliases['items'] ?? null) ? $checklistAliases['items'] : [];

        $oldChecklist = $this->normalizeChecklistCanonicalKeys($oldChecklist, $sectionAliases, $itemAliases);
        $newChecklist = $this->normalizeChecklistCanonicalKeys($newChecklist, $sectionAliases, $itemAliases);

        $changes = [];

        if (($oldStatus ?? '') !== ($newStatus ?? '') && ($oldStatus || $newStatus)) {
            $changes[] = "Status alterado de \"{$this->formatHistoryValue($oldStatus)}\" para \"{$this->formatHistoryValue($newStatus)}\"";
        }

        if ($this->valueChanged($oldChecklist['report'] ?? null, $newChecklist['report'] ?? null)) {
            $changes[] = 'Relatório técnico alterado';
        }

        $this->appendArrayDiffSummary($changes, $oldChecklist['photos'] ?? [], $newChecklist['photos'] ?? [], 'Fotos gerais');
        $this->appendSignatureHistoryChange($changes, 'Assinatura do cliente', $oldChecklist['signature'] ?? null, $newChecklist['signature'] ?? null);
        $this->appendSignatureHistoryChange($changes, 'Assinatura do técnico', $oldChecklist['techSignature'] ?? null, $newChecklist['techSignature'] ?? null);

        $metaKeys = ['times', 'report', 'photos', 'signature', 'techSignature', 'clientCpf', 'clientName', 'participantName', 'participantCpf'];
        $sectionKeys = array_values(array_unique(array_merge(array_keys($oldChecklist), array_keys($newChecklist))));

        foreach ($sectionKeys as $sectionKey) {
            if (in_array((string) $sectionKey, $metaKeys, true)) {
                continue;
            }

            $oldSection = is_array($oldChecklist[$sectionKey] ?? null) ? $oldChecklist[$sectionKey] : [];
            $newSection = is_array($newChecklist[$sectionKey] ?? null) ? $newChecklist[$sectionKey] : [];
            $itemKeys = array_values(array_unique(array_merge(array_keys($oldSection), array_keys($newSection))));

            foreach ($itemKeys as $itemKey) {
                $itemId = (string) $itemKey;
                $itemLabel = $this->checklistItemLabel($itemId, $itemTitleMap);
                $oldItem = $this->normalizeChecklistItemState($oldSection[$itemKey] ?? null);
                $newItem = $this->normalizeChecklistItemState($newSection[$itemKey] ?? null);

                if ($this->valueChanged($oldItem['v'], $newItem['v'])) {
                    if ($this->looksLikeSignatureValue($oldItem['v']) || $this->looksLikeSignatureValue($newItem['v'])) {
                        $changes[] = $this->buildSignatureHistoryText("{$itemLabel}: assinatura", $oldItem['v'], $newItem['v']);
                    } else {
                        $changes[] = "{$itemLabel}: valor alterado";
                    }
                }

                if ($this->valueChanged($oldItem['o'], $newItem['o'])) {
                    $changes[] = "{$itemLabel}: observação alterada";
                }

                $fieldKeys = array_values(array_unique(array_merge(array_keys($oldItem['f']), array_keys($newItem['f']))));
                foreach ($fieldKeys as $fieldKey) {
                    $oldField = $oldItem['f'][$fieldKey] ?? null;
                    $newField = $newItem['f'][$fieldKey] ?? null;
                    $oldFieldState = $this->normalizeChecklistItemState($oldField);
                    $newFieldState = $this->normalizeChecklistItemState($newField);

                    if ($this->valueChanged($oldFieldState['v'], $newFieldState['v'])) {
                        $changes[] = "{$itemLabel} (campo {$fieldKey}): valor alterado";
                    }
                    if ($this->valueChanged($oldFieldState['o'], $newFieldState['o'])) {
                        $changes[] = "{$itemLabel} (campo {$fieldKey}): observação alterada";
                    }
                    $this->appendArrayDiffSummary($changes, $oldFieldState['p'], $newFieldState['p'], "{$itemLabel} (campo {$fieldKey}): fotos");
                }

                $this->appendArrayDiffSummary($changes, $oldItem['p'], $newItem['p'], "{$itemLabel}: fotos");
            }
        }

        $changes = array_values(array_unique(array_filter(array_map('trim', $changes), fn ($line) => $line !== '')));
        $maxChanges = 150;
        if (count($changes) > $maxChanges) {
            $rest = count($changes) - $maxChanges;
            $changes = array_slice($changes, 0, $maxChanges);
            $changes[] = "... e mais {$rest} alterações.";
        }

        return $changes;
    }

    private function normalizeChecklistCanonicalKeys(array $checklist, array $sectionAliases, array $itemAliases): array
    {
        $normalized = [];

        foreach ($checklist as $sectionKey => $sectionValue) {
            $sectionCanonical = $this->resolveCanonicalKey($sectionKey, $sectionAliases);

            if (! is_array($sectionValue)) {
                $normalized[$sectionCanonical] = $sectionValue;

                continue;
            }

            if (! isset($normalized[$sectionCanonical]) || ! is_array($normalized[$sectionCanonical])) {
                $normalized[$sectionCanonical] = [];
            }

            foreach ($sectionValue as $itemKey => $itemValue) {
                $itemCanonical = $this->resolveCanonicalKey($itemKey, $itemAliases);
                $normalized[$sectionCanonical][$itemCanonical] = $itemValue;
            }
        }

        return $normalized;
    }

    private function resolveCanonicalKey($key, array $aliases): string
    {
        $normalized = trim((string) $key);
        if ($normalized === '') {
            return $normalized;
        }

        return array_key_exists($normalized, $aliases)
            ? (string) $aliases[$normalized]
            : $normalized;
    }

    private function appendSignatureHistoryChange(array &$changes, string $label, $oldValue, $newValue): void
    {
        if (! $this->valueChanged($oldValue, $newValue)) {
            return;
        }

        $changes[] = $this->buildSignatureHistoryText($label, $oldValue, $newValue);
    }

    private function buildSignatureHistoryText(string $label, $oldValue, $newValue): string
    {
        $oldHas = ! empty($this->normalizeHistoryScalar($oldValue));
        $newHas = ! empty($this->normalizeHistoryScalar($newValue));

        if (! $oldHas && $newHas) {
            return "{$label}: adicionada";
        }
        if ($oldHas && ! $newHas) {
            return "{$label}: removida";
        }

        return "{$label}: atualizada";
    }

    private function appendArrayDiffSummary(array &$changes, $oldValues, $newValues, string $label): void
    {
        $old = $this->normalizeStringList($oldValues);
        $new = $this->normalizeStringList($newValues);

        $added = array_values(array_diff($new, $old));
        $removed = array_values(array_diff($old, $new));

        if (count($added) === 0 && count($removed) === 0) {
            return;
        }

        $parts = [];
        if (count($added) > 0) {
            $parts[] = '+'.count($added);
        }
        if (count($removed) > 0) {
            $parts[] = '-'.count($removed);
        }

        $changes[] = "{$label}: ".implode(' / ', $parts);
    }

    private function normalizeStringList($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    private function normalizeChecklistItemState($raw): array
    {
        $state = ['v' => null, 'o' => null, 'f' => [], 'p' => []];

        if (
            is_array($raw) && (
                array_key_exists('v', $raw) ||
                array_key_exists('o', $raw) ||
                array_key_exists('f', $raw) ||
                array_key_exists('p', $raw)
            )
        ) {
            $state['v'] = $raw['v'] ?? null;
            $state['o'] = $raw['o'] ?? null;

            if (is_array($raw['f'] ?? null)) {
                foreach ($raw['f'] as $fieldKey => $fieldValue) {
                    $state['f'][(string) $fieldKey] = $fieldValue;
                }
            }

            $state['p'] = $this->normalizeStringList($raw['p'] ?? []);

            return $state;
        }

        if ($raw !== null) {
            $state['v'] = $raw;
        }

        return $state;
    }

    private function checklistItemLabel(string $itemId, array $itemTitleMap): string
    {
        if (isset($itemTitleMap[$itemId]) && trim((string) $itemTitleMap[$itemId]) !== '') {
            return trim((string) $itemTitleMap[$itemId])." (#{$itemId})";
        }

        if (str_starts_with($itemId, 'PECA-')) {
            return "Peça {$itemId}";
        }

        return "Item {$itemId}";
    }

    private function normalizeHistoryScalar($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            return $this->normalizeHistoryNumeric((string) $value);
        }
        if (is_string($value)) {
            $normalized = trim($value);
            if ($normalized === '') {
                return '';
            }

            $lower = mb_strtolower($normalized);
            if (in_array($lower, ['true', 'sim', 'yes', 'on'], true)) {
                return '1';
            }
            if (in_array($lower, ['false', 'nao', 'não', 'no', 'off'], true)) {
                return '0';
            }

            if (is_numeric(str_replace(',', '.', $normalized))) {
                return $this->normalizeHistoryNumeric($normalized);
            }

            return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        }
        if (is_array($value)) {
            return trim((string) json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return trim((string) $value);
    }

    private function normalizeHistoryNumeric(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(',', '.', $value);
        if (! is_numeric($value)) {
            return $value;
        }

        $normalized = rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
        if ($normalized === '' || $normalized === '-0') {
            return '0';
        }

        return $normalized;
    }

    private function formatHistoryValue($value): string
    {
        $normalized = $this->normalizeHistoryScalar($value);
        if ($normalized === '') {
            return 'vazio';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        if (mb_strlen($normalized) > 90) {
            return mb_substr($normalized, 0, 87).'...';
        }

        return $normalized;
    }

    private function valueChanged($oldValue, $newValue): bool
    {
        return $this->normalizeHistoryScalar($oldValue) !== $this->normalizeHistoryScalar($newValue);
    }

    private function looksLikeSignatureValue($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return str_contains($value, 'assinaturas/') || str_starts_with($value, 'data:image');
    }

    private function upsertChecklistHistoryEntry(int $numeroOrdem, string $vendedor, string $summary, array $changes): void
    {
        $changes = array_values(array_unique(array_filter(array_map('trim', $changes), fn ($line) => $line !== '')));
        if (empty($changes)) {
            return;
        }

        $recent = OrdensHistorico::where('cod_ordem', $numeroOrdem)
            ->where('acao', 'ALTERAÇÃO')
            ->where('vendedor', $vendedor)
            ->where('detalhes', 'like', '%Alteração no checklist || %')
            ->orderByDesc('id')
            ->first();

        $summaryToPersist = $summary;
        $existingEntries = [];
        if ($recent) {
            $existingEntries = $this->extractChecklistChangesFromHistoryDetails($recent->detalhes);
        }

        $now = now()->toIso8601String();
        $newEntries = array_map(fn ($line) => ['at' => $now, 'message' => $line], $changes);
        $mergedEntries = array_merge($existingEntries, $newEntries);
        $mergedEntries = $this->uniqueChecklistHistoryEntries($mergedEntries);

        $payload = json_encode(['changes' => $mergedEntries], JSON_UNESCAPED_UNICODE);
        $detalhes = $summaryToPersist.' || '.$payload;

        if ($recent) {
            $recent->detalhes = $detalhes;
            $recent->save();

            return;
        }

        OrdensHistorico::create([
            'cod_ordem' => $numeroOrdem,
            'vendedor' => $vendedor ?: 'Mobile App',
            'acao' => 'ALTERAÇÃO',
            'detalhes' => $detalhes,
        ]);
    }

    private function extractChecklistChangesFromHistoryDetails(?string $details): array
    {
        if (! is_string($details) || ! str_contains($details, '||')) {
            return [];
        }

        $parts = explode('||', $details, 2);
        $jsonPart = trim($parts[1] ?? '');
        if ($jsonPart === '') {
            return [];
        }

        $decoded = json_decode($jsonPart, true);
        if (! is_array($decoded) || ! isset($decoded['changes']) || ! is_array($decoded['changes'])) {
            return [];
        }

        $entries = [];
        foreach ($decoded['changes'] as $change) {
            if (is_array($change)) {
                $message = trim((string) ($change['message'] ?? ''));
                if ($message === '') {
                    continue;
                }
                $entries[] = [
                    'at' => isset($change['at']) ? trim((string) $change['at']) : null,
                    'message' => $message,
                ];

                continue;
            }

            $message = trim((string) $change);
            if ($message === '') {
                continue;
            }
            $entries[] = ['at' => null, 'message' => $message];
        }

        return $entries;
    }

    private function uniqueChecklistHistoryEntries(array $entries): array
    {
        $unique = [];
        $seen = [];

        foreach ($entries as $entry) {
            $message = trim((string) ($entry['message'] ?? ''));
            if ($message === '') {
                continue;
            }

            $at = isset($entry['at']) ? trim((string) $entry['at']) : '';
            $key = $at.'|'.$message;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = [
                'at' => $at !== '' ? $at : null,
                'message' => $message,
            ];
        }

        $max = 500;
        if (count($unique) > $max) {
            $unique = array_slice($unique, -$max);
        }

        return $unique;
    }

    private function extractHistorySummary(?string $details): ?string
    {
        if (! is_string($details) || ! str_contains($details, '||')) {
            return null;
        }

        $parts = explode('||', $details, 2);
        $summary = trim((string) ($parts[0] ?? ''));

        return $summary !== '' ? $summary : null;
    }

    /**
     * Helper to centralize price and tax calculations.
     * Copied from MobileOsPecasController for consistency.
     */
    private function calculatePartData($peca, $quantidade, $ufCliente)
    {
        $quantidade = (float) $quantidade;

        // REQUISITO: Sempre utilizar preço um (custo unitário) como base de cálculo
        $precoUnitario = (float) ($peca->{'Custo unitário'} ?? 0);
        $precoCusto = (float) ($peca->{'Custo unitário'} ?? 0);
        $valorTotal = $quantidade * $precoUnitario;

        // Lógica de ICMS
        $icmsTabela = \App\Models\Icms::where('Uf', 'SP')
            ->where('Uf destino', $ufCliente)
            ->first();

        $icmsPercent = floatval($icmsTabela?->Icms ?? 0);
        $reducaoBase = floatval($icmsTabela?->{'Reducao base'} ?? 0);

        if (floatval($peca->Icms ?? 0) > 0) {
            $icmsPercent = floatval($peca->Icms);
        }
        if (floatval($peca->{'Reducao base'} ?? 0) > 0) {
            $reducaoBase = floatval($peca->{'Reducao base'});
        }

        $cst = $peca->{'Situação tributária'} ?? '000';
        preg_match('/^.(\d{2})$/', $cst, $m);
        $cstReduzido = $m[1] ?? null;

        $valorIcms = 0;
        if ($cstReduzido === '00') {
            $valorIcms = ($icmsPercent / 100) * $valorTotal;
        } elseif ($cstReduzido === '20') {
            $base = $valorTotal;
            $valorIcms = ($icmsPercent / 100) * $base * (1 - $reducaoBase / 100);
        } else {
            $icmsPercent = 0;
            $reducaoBase = 0;
            $valorIcms = 0;
        }

        return [
            'quantidade' => $quantidade,
            'preco_unitario' => $precoUnitario,
            'preco_custo' => $precoCusto,
            'valor_total' => $valorTotal,
            'cst' => $cst,
            'icms_percent' => $icmsPercent,
            'reducao_base' => $reducaoBase,
            'valor_icms' => $valorIcms,
        ];
    }

    /**
     * Recalcula os totais da OS e atualiza sequencial legado.
     */
    private function recalcularTotaisOs($id)
    {
        try {
            // Soma todas as peças
            $totalPecas = DB::table('Ordens pecas')
                ->where('Numero ordem', $id)
                ->sum('Valor item');

            // Soma ICMS
            $totalIcms = DB::table('Ordens pecas')
                ->where('Numero ordem', $id)
                ->sum('Valor icms');

            // Soma Serviços (Mão de Obra)
            $totalServicos = DB::table('Ordens maodeobra')
                ->where('Numero ordem', $id)
                ->sum('Valor total');

            // Valor Total = Peças + Serviços
            $valorTotal = $totalPecas + $totalServicos;

            // Atualiza os campos na OS
            DB::table('Ordens')
                ->where('Numero ordem', $id)
                ->update([
                    'Total peças' => $totalPecas,
                    'Valor serviços' => $totalServicos,
                    'Valor icms' => $totalIcms,
                    'Valor total' => $valorTotal,
                ]);

            // Atualiza Legacy Sequence para Peças
            $maxItem = DB::table('Ordens pecas')
                ->where('Numero ordem', $id)
                ->max('Item') ?? 0;

            $this->upsertSysSequencial('Ordens pecas', 'Item', (string) $id, $maxItem);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('💥 Erro ao recalcular totais AppSync', [
                'ordem' => $id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza a tabela de sequenciais do sistema (Legado)
     */
    private function upsertSysSequencial(string $tabela, string $campo, string $chave, $valor)
    {
        try {
            DB::table('SYS~Sequencial')->updateOrInsert(
                [
                    'SYS~BD' => 'DADOSPGI',
                    'SYS~Tabela' => $tabela,
                    'SYS~Campo' => $campo,
                    'SYS~Chave' => $chave,
                ],
                [
                    'PW~Projeto' => '',
                    'SYS~Valor' => $valor,
                    'SYS~ValorAnterior' => 0,
                    'SYS~Estacao' => 'APP_SYNC',
                    'SYS~Identificacao' => 'APP_SYNC',
                    'SYS~Pendentes' => 1,
                ]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("⚠️ Falha ao atualizar SYS~Sequencial [{$tabela}]: ".$e->getMessage());
        }
    }
}

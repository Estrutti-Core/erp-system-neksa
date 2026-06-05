<?php

namespace App\Http\Controllers;

use App\Models\ChecklistSection;
use App\Models\Funcionarios;
use App\Models\Ordem;
use App\Models\OrdemMaodeObra;
use App\Models\OrdemPeca;
use App\Models\OrdensHistorico;
use App\Services\ChecklistMonetaryRecalculator;
use App\Services\MobileCommissionIndicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChecklistController extends Controller
{
    /**
     * Show the checklist for a given Ordem.
     * Returns a partial view with the checklist form.
     */
    public function show(Ordem $ordem)
    {
        $this->allowLongRunningChecklistRequest();

        try {
            $osNumber = $ordem->{'Numero ordem'};

            // 1. Fetch Compact Checklist JSON from API Table
            $ordemApi = \App\Models\OrdemApi::where('numero_ordem', $osNumber)->first();
            $checklistJson = $ordemApi ? $ordemApi->checklist_json : [];
            $savedServiceType = $this->resolveStoredChecklistServiceType($checklistJson);

            // 2. Fetch Real Parts for this OS
            $realPecas = OrdemPeca::where('Numero ordem', $osNumber)->get();
            $realServicos = OrdemMaodeObra::where('Numero ordem', $osNumber)
                ->orderBy('Item')
                ->get();

            // 3. Fetch Base Checklist Structure
            $checklistSections = ChecklistSection::with(['items.fields', 'items.services'])->get()->sortBy(function ($s) {
                return (float) $s->code;
            })->values();

            // 4. Hydrate Logic (Mirrors AppSyncController)
            $hydratedSections = $checklistSections->map(function ($section, $secIndex) use ($checklistJson, $realPecas, $ordem) {
                $secCode = ($secIndex + 1).'.0';

                $titleLower = mb_strtolower($section->title);
                $isPieceSection = (str_contains($titleLower, 'peça') || str_contains($titleLower, 'produto')) && !str_contains($titleLower, 'medi');

                // Inject Real Parts if this is the Pieces section
                if ($isPieceSection) {
                    $dynamicItems = $realPecas->map(function ($p, $pIndex) {
                        $itemCode = 'P-'.$p->Item;

                        return (object) [ // Mocking ChecklistItem structure loosely for view
                            'id' => 'PECA-'.$p->Item,
                            'code' => $itemCode,
                            'part_code' => trim($p->{'Código fabricante'}),
                            'title' => trim($p->{'Peça'}),
                            'input_type' => 'checkbox',
                            'value' => '', // Will be hydrated below
                            'services' => collect([]),
                            'fields' => collect([
                                (object) ['id' => 'f1', 'label' => 'Qtd Levada', 'type' => 'number', 'default_value' => (string) intval($p->Qtde ?? 0), 'value' => (string) intval($p->Qtde ?? 0)],
                                (object) ['id' => 'f2', 'label' => 'Qtd Util.', 'type' => 'number', 'default_value' => (string) floatval($p->{'Qtde utilizada'} ?? 0), 'value' => (string) floatval($p->{'Qtde utilizada'} ?? 0)],
                                (object) ['id' => 'f3', 'label' => 'Valor Unit.', 'type' => 'number', 'default_value' => number_format(floatval($p->{'Valor informado'} ?? 0) > 0 ? floatval($p->{'Valor informado'}) : floatval($p->{'Valor tabela'} ?? 0), 2, '.', ''), 'value' => number_format(floatval($p->{'Valor informado'} ?? 0) > 0 ? floatval($p->{'Valor informado'}) : floatval($p->{'Valor tabela'} ?? 0), 2, '.', '')],
                                (object) ['id' => 'f4', 'label' => 'Valor Total', 'type' => 'number', 'default_value' => number_format(floatval($p->{'Qtde utilizada'} ?? 0) * (floatval($p->{'Valor informado'} ?? 0) > 0 ? floatval($p->{'Valor informado'}) : floatval($p->{'Valor tabela'} ?? 0)), 2, '.', '')],
                            ]),
                        ];
                    });

                    // We need to merge existing items with dynamic items.
                    // Since $section->items is a Collection of Models, and $dynamicItems is a collection of objects,
                    // we'll convert everything to a standard array or collection for the view.
                    // Ideally, the View should handle "Items" as a verified list.

                    // Let's keep distinct collections to avoid hydration issues if we can,
                    // OR we standardize.
                    // For the view, let's just push these dynamic items into the items collection as loose objects.
                    foreach ($dynamicItems as $dItem) {
                        $section->items->push($dItem);
                    }
                }

                // Determine Section Values (Robust lookup: ID > Code)
                $vId = $checklistJson[$section->id] ?? null;
                $vCode = $checklistJson[$section->code] ?? null;
                $sectionValues = [];
                // Use Union (+) to preserve numeric keys
                if (is_array($vCode)) {
                    $sectionValues = $vCode + $sectionValues;
                }
                if (is_array($vId)) {
                    $sectionValues = $vId + $sectionValues;
                }

                // Acceptance Merge (Section 7)
                if ($section->id === 7) {
                    $v7 = $checklistJson[7] ?? [];
                    $v70 = $checklistJson['7.0'] ?? [];

                    // Use Union (+) instead of array_merge to preserve numeric keys (like '63')
                    // Order matters: Right side operands are ignored if key exists in left side.
                    // We want: priority to $v7 (ID), then $v70 (Code), then $sectionValues (already populated)

                    if (is_array($v70)) {
                        $sectionValues = $v70 + $sectionValues;
                    }
                    if (is_array($v7)) {
                        $sectionValues = $v7 + $sectionValues;
                    }
                }

                // Hydrate items
                $titleLower = mb_strtolower($section->title);
                $isPecaSection = (str_contains($titleLower, 'peça') || str_contains($titleLower, 'produto')) && !str_contains($titleLower, 'medi');
                $section->items = $section->items->map(function ($item) use ($sectionValues, $isPecaSection, $ordem) {
                    $idKey = (string) $item->id;
                    $codeKey = (string) $item->code;
                    $storedValue = $sectionValues[$idKey] ?? $sectionValues[$codeKey] ?? null;

                    $mainValue = null;
                    $fieldsData = [];
                    $observation = '';
                    $photos = [];
                    $storedPhotos = [];

                    if ($storedValue !== null) {
                        if (is_array($storedValue)) {
                            $mainValue = $storedValue['v'] ?? null;
                            $fieldsData = $storedValue['f'] ?? [];
                            $observation = $storedValue['o'] ?? '';
                            if (isset($storedValue['p']) && is_array($storedValue['p'])) {
                                foreach ($storedValue['p'] as $p) {
                                    $storedPhotoReference = $this->normalizePrivateImagePath((string) $p)
                                        ?? $this->normalizePublicImagePath((string) $p)
                                        ?? (is_string($p) ? trim($p) : null);

                                    if ($storedPhotoReference !== null && $storedPhotoReference !== '') {
                                        $storedPhotos[] = $storedPhotoReference;
                                    }

                                    $photoSource = $this->pathToBase64($storedPhotoReference ?? $p);
                                    if ($photoSource) {
                                        $photos[] = $photoSource;
                                    }
                                }
                            }
                        } else {
                            $mainValue = $storedValue;
                        }
                    }

                    $mainValue = $this->resolveMirroredChecklistValue($item, $mainValue, $ordem);

                    // Default from JSON if it's a dynamic button config
                    if ($mainValue === null && ($item->input_type === 'select' || $item->input_type === 'button')) {
                        if (is_string($item->value) && str_starts_with($item->value, '{')) {
                            $config = json_decode($item->value, true);
                            if (is_array($config) && isset($config['selected']) && is_scalar($config['selected'])) {
                                $mainValue = (string) $config['selected'];
                            }
                        }
                    }

                    // Handle Signatures (Convert Path to Base64 for display)
                    if ($item->input_type === 'signature') {
                        Log::debug("Checklist Item {$item->id} is signature. Value: ".($mainValue ?: 'NULL'));
                    }

                    if ($item->input_type === 'signature' && ! empty($mainValue)) {
                        $b64 = $this->pathToBase64($mainValue, true);
                        if ($b64) {
                            $mainValue = $b64;
                            Log::debug("Signature {$item->id} hydrated successfully. Length: ".strlen($b64));
                        } else {
                            Log::warning("Signature {$item->id} hydration FAILED for path: $mainValue");
                        }
                    }

                    // Assign to a friendly structure for View
                    $item->hydrated_value = $mainValue;
                    $item->hydrated_observation = $observation;
                    $item->hydrated_photos = $photos;
                    $item->stored_photos = $storedPhotos;

                    // Fields Hydration
                    if (isset($item->fields) && count($item->fields) > 0) {
                        foreach ($item->fields as $field) {
                            $fVal = null;
                            if (isset($fieldsData[$field->id])) {
                                $fVal = $fieldsData[$field->id];
                            } elseif (isset($fieldsData[$field->label])) {
                                $fVal = $fieldsData[$field->label];
                            }

                            // For Peças section: read-only fields always use DB default_value.
                            // Only Qtd Util. (f2) may use the cached checklist_json value.
                            if ($isPecaSection && $field->label !== 'Qtd Util.') {
                                $field->hydrated_value = $field->default_value ?? null;
                            } else {
                                $field->hydrated_value = is_array($fVal)
                                    ? (string) ($fVal['v'] ?? $field->value ?? '')
                                    : ($fVal ?? $field->value ?? null);
                            }
                        }
                    }

                    return $item;
                });

                return $section;
            });

            Log::info('Checklist hydrated for OS: '.$osNumber);

            // 5. Global Photos
            $globalPhotos = [];
            $storedGlobalPhotos = [];
            if (isset($checklistJson['photos']) && is_array($checklistJson['photos'])) {
                foreach ($checklistJson['photos'] as $p) {
                    $storedPhotoReference = $this->normalizePrivateImagePath((string) $p)
                        ?? $this->normalizePublicImagePath((string) $p)
                        ?? (is_string($p) ? trim($p) : null);

                    if ($storedPhotoReference !== null && $storedPhotoReference !== '') {
                        $storedGlobalPhotos[] = $storedPhotoReference;
                    }

                    $photoSource = $this->pathToBase64($storedPhotoReference ?? $p);
                    if ($photoSource) {
                        $globalPhotos[] = $photoSource;
                    }
                }
            }

            return view('components.ordens.checklist-tab', [
                'ordem' => $ordem,
                'sections' => $hydratedSections,
                'ordemApi' => $ordemApi,
                'savedServiceType' => $savedServiceType,
                'globalPhotos' => $globalPhotos,
                'storedGlobalPhotos' => $storedGlobalPhotos,
                'servicesData' => $realServicos->map(function ($service) {
                    $qtd = (float) ($service->{'Qtde'} ?? 1);
                    $valorUnit = (float) ($service->{'Valor unitario'} ?? 0);
                    $valorInf = (float) ($service->{'Valor informado'} ?? 0);
                    $valorTotal = (float) ($service->{'Valor total'} ?? 0);
                    $usarValorInformado = $valorInf > 0
                        || (abs($valorInf) < 0.00001 && $qtd > 0 && $valorUnit > 0 && abs($valorTotal) < 0.00001);

                    return [
                        'codigo' => (string) ($service->{'Descrição serviços'} ?? ''),
                        'descricao' => (string) ($service->{'Descrição serviços'} ?? ''),
                        'qtd' => $qtd,
                        'valorUnit' => $valorUnit,
                        'valorInf' => $valorInf,
                        'valorTotal' => $valorTotal,
                        'usarValorInformado' => $usarValorInformado,
                        'iss' => (float) ($service->Iss ?? 0),
                    ];
                })->values(),
            ]);

        } catch (\Throwable $e) {
            Log::error('ChecklistController Show Error: '.$e->getMessage(), [
                'exception' => $e,
                'os' => $ordem->getKey(),
            ]);

            return response("<div class='p-4 text-red-500'>Erro interno ao carregar checklist: ".e($e->getMessage()).'</div>', 500);
        }
    }

    private function pathToBase64($path, bool $preferInline = false)
    {
        if (! $path || is_array($path)) {
            return null;
        }
        if (strpos($path, 'data:image') === 0) {
            return $path;
        }

        // If it's already an absolute URL, return it
        $normalizedPrivatePath = $this->normalizePrivateImagePath($path);
        if ($normalizedPrivatePath) {
            if (! $preferInline && ! $this->isSignaturePath($normalizedPrivatePath)) {
                return $this->privateImageUrl($normalizedPrivatePath);
            }

            $dataUri = $this->storagePathToDataUri('local', $normalizedPrivatePath);

            return $dataUri ?: $this->privateImageUrl($normalizedPrivatePath);
        }

        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        // Special case: relative paths from upload-temp (e.g. "ordem-fotos/xxx.jpg")
        // We prefer returning a public URL for the browser instead of Base64
        $normalizedPublicPath = $this->normalizePublicImagePath($path);
        if ($normalizedPublicPath) {
            // If it's an absolute URL, extract only the relative part starting with 'ordem-fotos'
            return asset('storage/'.ltrim($normalizedPublicPath, '/'));
        }

        $disks = ['local', 'public'];
        foreach ($disks as $disk) {
            $prefixes = ['', 'private/', 'public/', 'app/'];
            foreach ($prefixes as $prefix) {
                $checkPath = $prefix.$path;
                if (Storage::disk($disk)->exists($checkPath)) {
                    if ($disk === 'local' && ! $preferInline && ! $this->isSignaturePath($checkPath)) {
                        return $this->privateImageUrl($checkPath);
                    }

                    // For other files, if on public disk or in ordre-fotos, return URL
                    if ($disk === 'public' || str_contains($checkPath, 'ordem-fotos')) {
                        return asset('storage/'.ltrim(str_replace('public/', '', $checkPath), '/'));
                    }

                    // Fallback for inline rendering when needed.
                    $dataUri = $this->storagePathToDataUri($disk, $checkPath);
                    if ($dataUri) {
                        return $dataUri;
                    }
                }
            }
        }

        // If nothing found but it looks like a relative path, try returning it as a public storage link as last resort
        if (! str_contains($path, '/') && ! empty($path)) {
            return asset('storage/'.$path);
        }

        Log::warning('pathToBase64 failed for: '.$path);

        return null;
    }

    private function normalizePrivateImagePath(string $path): ?string
    {
        $path = trim($path);
        $path = $this->extractPrivateImagePathFromUrl($path) ?? $path;

        $normalizedPath = ltrim(str_replace('\\', '/', trim($path)), '/');
        $normalizedPath = preg_replace('#^private/#', '', $normalizedPath);

        if (preg_match('#^\d+/(fotos-adicionais|fotos-gerais|assinaturas)/[A-Za-z0-9._-]+\.(jpg|jpeg|png|gif|webp)$#i', $normalizedPath)) {
            return $normalizedPath;
        }

        return null;
    }

    private function extractPrivateImagePathFromUrl(string $path): ?string
    {
        $query = parse_url($path, PHP_URL_QUERY);
        if (! is_string($query) || trim($query) === '') {
            return null;
        }

        parse_str($query, $params);
        $imagePath = $params['path'] ?? null;

        return is_string($imagePath) && trim($imagePath) !== ''
            ? urldecode($imagePath)
            : null;
    }

    private function normalizePublicImagePath(string $path): ?string
    {
        if (preg_match('#(ordem-fotos/[A-Za-z0-9._/-]+\.(jpg|jpeg|png|gif|webp))#i', $path, $matches)) {
            return ltrim($matches[1], '/');
        }

        return null;
    }

    private function isSignaturePath(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), 'assinaturas/');
    }

    private function privateImageUrl(string $path): string
    {
        return route('ordens.private-foto', ['path' => ltrim($path, '/')], false);
    }

    private function storagePathToDataUri(string $disk, string $path): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $content = Storage::disk($disk)->get($path);
        if ($content === false || $content === null) {
            return null;
        }

        $mimeType = $this->detectImageMimeType($content, $path);

        return 'data:'.$mimeType.';base64,'.base64_encode($content);
    }

    private function detectImageMimeType(string $content, string $path): string
    {
        if (function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $content);
                finfo_close($finfo);

                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    return $detected;
                }
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }

    private function collectChecklistPrivatePhotoReferences(array $checklist, string $basePath): array
    {
        $references = [];

        $pushReference = function ($value) use (&$references, $basePath) {
            if (! is_string($value)) {
                return;
            }

            $normalizedPath = $this->normalizePrivateImagePath($value);
            if (! $normalizedPath || $this->isSignaturePath($normalizedPath)) {
                return;
            }

            if (! str_starts_with($normalizedPath, trim($basePath, '/').'/')) {
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

            $normalizedPath = $this->normalizePrivateImagePath($photoReference);
            if (! $normalizedPath || $this->isSignaturePath($normalizedPath)) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($normalizedPath)) {
                    Storage::disk('local')->delete($normalizedPath);
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao excluir foto removida do checklist web.', [
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

    private function putLocalImageContents(string $path, string $contents): bool
    {
        $written = Storage::disk('local')->put($path, $contents);

        return $written !== false && Storage::disk('local')->exists($path);
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
            Log::warning('Falha ao substituir assinatura local do checklist web.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
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

    private function saveCanonicalSignatureImage(
        $rawValue,
        string $basePath,
        ?array $targetMeta = null,
        ?string $topLevelKey = null,
        $fallbackValue = null
    )
    {
        $canonicalPath = $this->canonicalSignaturePath($basePath, $targetMeta, $topLevelKey);
        $normalizedFallback = $this->normalizePrivateImagePath(is_string($fallbackValue) ? $fallbackValue : '');
        $targetKey = $this->buildSignatureTargetKey($targetMeta, $topLevelKey);

        if (! is_string($rawValue)) {
            return $normalizedFallback;
        }

        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return $normalizedFallback;
        }

        if (! str_starts_with($rawValue, 'data:image')) {
            $normalizedPrivatePath = $this->normalizePrivateImagePath($rawValue);
            if ($normalizedPrivatePath && Storage::disk('local')->exists($normalizedPrivatePath)) {
                if ($normalizedPrivatePath === $canonicalPath) {
                    return $canonicalPath;
                }

                if ($this->isSignaturePath($normalizedPrivatePath) && $this->copyLocalImageContents($normalizedPrivatePath, $canonicalPath)) {
                    return $canonicalPath;
                }

                if ($normalizedFallback === $canonicalPath && Storage::disk('local')->exists($canonicalPath)) {
                    return $canonicalPath;
                }

                return $normalizedPrivatePath;
            }

            $normalizedPublicPath = $this->normalizePublicImagePath($rawValue);
            if ($normalizedPublicPath) {
                return $normalizedPublicPath;
            }

            return $normalizedFallback;
        }

        $imageParts = explode(',', $rawValue, 2);
        if (count($imageParts) !== 2) {
            return $rawValue;
        }

        $decoded = base64_decode($imageParts[1], true);
        if ($decoded === false) {
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
            $jpgContent = ob_get_clean();
            imagedestroy($image);
            imagedestroy($whiteImage);

            if ($jpgContent !== false) {
                $contentsToWrite = $jpgContent;
            }
        }

        if (! $this->replaceLocalImageContents($canonicalPath, $contentsToWrite)) {
            Log::error('Falha ao salvar assinatura canônica do checklist web.', [
                'path' => $canonicalPath,
                'target' => $targetKey,
            ]);

            return $rawValue;
        }

        return $canonicalPath;
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
                $normalizedPath = $this->normalizePrivateImagePath($savedPath);
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
                $itemMeta = $signatureItemMetaByAlias[$itemKeyString]
                    ?? $this->buildSignatureTargetMeta((string) $sectionKey, $itemKeyString, $itemKeyString, null);
                $currentValue = is_array($itemValue) ? ($itemValue['v'] ?? null) : $itemValue;

                if (! is_string($currentValue) || trim($currentValue) === '') {
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

                $normalizedPath = $this->normalizePrivateImagePath($savedPath);
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
            $normalizedPath = $this->normalizePrivateImagePath(is_string($keepPath) ? $keepPath : '');
            if (! $normalizedPath || ! $this->isSignaturePath($normalizedPath)) {
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
            Log::warning('Falha ao listar diretório de assinaturas do checklist web.', [
                'directory' => $signatureDir,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($signatureFiles as $signatureFile) {
            $normalizedPath = $this->normalizePrivateImagePath($signatureFile);
            if (! $normalizedPath || isset($keepSet[$normalizedPath])) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($normalizedPath)) {
                    Storage::disk('local')->delete($normalizedPath);
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao excluir assinatura excedente do checklist web.', [
                    'path' => $normalizedPath,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update the checklist data.
     */
    public function update(
        Request $request,
        Ordem $ordem,
        MobileCommissionIndicationService $commissionIndicationService,
        ChecklistMonetaryRecalculator $checklistMonetaryRecalculator
    ) {
        $this->allowLongRunningChecklistRequest();

        try {
            $data = $request->input('checklist_data', []);
            if (! is_array($data)) {
                $data = [];
            }
            $servicesData = $request->input('services_data', []);
            if (! is_array($servicesData)) {
                $servicesData = [];
            }
            $globalPhotos = $request->input('global_photos', null);
            if ($globalPhotos !== null && ! is_array($globalPhotos)) {
                $globalPhotos = [];
            }

            $numeroOrdem = $ordem->{'Numero ordem'};
            $basePath = (string) $numeroOrdem;
            $existingServicesSnapshot = $this->snapshotServices($numeroOrdem);

            DB::beginTransaction();

            // 1. Fetch Existing (or create empty)
            $ordemApi = \App\Models\OrdemApi::firstOrCreate(
                ['numero_ordem' => $numeroOrdem],
                ['checklist_json' => []]
            );
            $existingChecklistSnapshot = $this->normalizeChecklistArray($ordemApi->checklist_json ?? []);
            $existingPhotoReferences = $this->collectChecklistPrivatePhotoReferences($existingChecklistSnapshot, $basePath);

            // 2. Prepare for Merge
            $currentChecklist = $ordemApi->checklist_json ?? [];
            if (! is_array($currentChecklist)) {
                $currentChecklist = [];
            }

            // Helper to get item config (to check input_type)
            $allSections = ChecklistSection::with('items')->get();
            $itemTitleMap = $this->buildChecklistItemTitleMap($allSections);
            $checklistAliases = $this->buildChecklistAliases($allSections);
            $signatureItemMetaByAlias = [];
            $signatureItemTargetsByTopLevelKey = [
                'signature' => null,
                'techSignature' => null,
            ];
            foreach ($allSections as $signatureSection) {
                foreach ($signatureSection->items as $signatureItem) {
                    if (($signatureItem->input_type ?? null) !== 'signature') {
                        continue;
                    }

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
                        'sectionId' => (string) $signatureSection->id,
                        'role' => $role,
                    ];

                    $signatureItemMetaByAlias[$meta['id']] = $meta;
                    if ($meta['code'] !== '') {
                        $signatureItemMetaByAlias[$meta['code']] = $meta;
                    }
                    $topLevelKey = $role === 'tecnico' ? 'techSignature' : 'signature';
                    if ($signatureItemTargetsByTopLevelKey[$topLevelKey] === null) {
                        $signatureItemTargetsByTopLevelKey[$topLevelKey] = $meta;
                    }
                }
            }
            $ordemMirrorUpdates = [];
            $vendedor = $this->resolveHistoricoVendedor('Painel');

            // 3. Process and Compact Data
            foreach ($data as $secKey => $items) {
                if (! is_array($items)) {
                    continue;
                }

                $compactedSection = [];
                $sectionModel = is_numeric($secKey)
                    ? $allSections->find($secKey)
                    : $allSections->where('code', $secKey)->first();

                // Detect if this is the Peças Enviadas section
                $titleLower = $sectionModel ? mb_strtolower($sectionModel->title) : '';
                $isSectionPecas = $sectionModel
                    ? ((str_contains($titleLower, 'peça') || str_contains($titleLower, 'produto')) && !str_contains($titleLower, 'medi'))
                    : false;

                // To avoid duplication between ID and CODE keys, remove both if they exist
                if ($sectionModel) {
                    unset($currentChecklist[$sectionModel->id]);
                    unset($currentChecklist[$sectionModel->code]);
                    if ($sectionModel->id == 7) {
                        unset($currentChecklist['7.0']);
                    } // Special case for acceptance
                } else {
                    unset($currentChecklist[$secKey]);
                }

                foreach ($items as $itemKey => $itemData) {
                    if (! is_array($itemData)) {
                        continue;
                    }

                    $v = $itemData['v'] ?? null;
                    $existingValue = $existingChecklistSnapshot[$secKey][$itemKey] ?? null;
                    $o = trim($itemData['o'] ?? '');
                    $f = $itemData['f'] ?? [];
                    $p = $itemData['p'] ?? [];

                    // Identify item config for type-specific logic
                    $itemModel = $sectionModel
                        ? $sectionModel->items->where('id', $itemKey)->first() ?? $sectionModel->items->where('code', $itemKey)->first()
                        : null;

                    // Sync Qtd Util. to Ordens pecas table when saving Peças section
                    // Item keys for parts are like 'PECA-1', 'PECA-2', etc.
                    if ($isSectionPecas && is_string($itemKey) && str_starts_with($itemKey, 'PECA-')) {
                        $itemSeq = (int) str_replace('PECA-', '', $itemKey);
                        $qtdUtil = floatval($f['f2'] ?? $f['Qtd Util.'] ?? 0);
                        if ($itemSeq > 0) {
                            \App\Models\OrdemPeca::where('Numero ordem', $numeroOrdem)
                                ->where('Item', $itemSeq)
                                ->update(['Qtde utilizada' => $qtdUtil]);
                        }
                    }

                    // Handle Photos (Base64 -> File)
                    $savedPhotos = [];
                    if (! empty($p) && is_array($p)) {
                        foreach ($p as $index => $photoB64) {
                            $savedPath = $this->saveImage($photoB64, $basePath, 'fotos-adicionais', "item-{$itemKey}-photo-{$index}");
                            if ($savedPath) {
                                $savedPhotos[] = $savedPath;
                            }
                        }
                    }

                    // Handle Signature (Base64 -> File)
                    if ($itemModel && $itemModel->input_type === 'signature' && ! empty($v)) {
                        $signatureTargetMeta = $this->buildSignatureTargetMeta(
                            (string) ($secKey ?? ''),
                            (string) ($itemModel->id ?? $itemKey),
                            (string) ($itemModel->code ?? $itemKey),
                            (string) ($itemModel->title ?? '')
                        );
                        $v = $this->saveCanonicalSignatureImage(
                            $v,
                            $basePath,
                            $signatureTargetMeta,
                            null,
                            is_array($existingValue) ? ($existingValue['v'] ?? null) : $existingValue
                        );
                    }

                    $mirrorMapping = $this->resolveChecklistMirrorMapping(
                        $itemModel->id ?? $itemKey,
                        $itemModel->code ?? $itemKey,
                        $itemModel->title ?? null
                    );
                    if ($mirrorMapping) {
                        $ordemMirrorUpdates[$mirrorMapping['ordem_column']] = $this->normalizeChecklistMirrorText($v);
                    }

                    // Cleanup Fields (remove nulls)
                    if (is_array($f)) {
                        $f = array_filter($f, fn ($val) => ! is_null($val) && $val !== '');
                    }

                    // Compacting Logic
                    $hasObs = ! empty($o);
                    $hasFields = ! empty($f);
                    $hasPhotos = ! empty($savedPhotos);
                    $hasValue = ! is_null($v) && $v !== '';

                    if (! $hasObs && ! $hasFields && ! $hasPhotos) {
                        if ($hasValue) {
                            $compactedSection[$itemKey] = $v;
                        }
                    } else {
                        $itemObj = [];
                        $itemObj['v'] = $v;
                        if ($hasObs) {
                            $itemObj['o'] = $o;
                        }
                        if ($hasFields) {
                            $itemObj['f'] = $f;
                        }
                        if ($hasPhotos) {
                            $itemObj['p'] = $savedPhotos;
                        }
                        $compactedSection[$itemKey] = $itemObj;
                    }
                }

                if (! empty($compactedSection)) {
                    $currentChecklist[$secKey] = $compactedSection;
                } else {
                    unset($currentChecklist[$secKey]);
                }
            }

            if (is_array($globalPhotos)) {
                $savedGlobalPhotos = [];
                foreach ($globalPhotos as $index => $photoReference) {
                    $savedPath = $this->saveImage($photoReference, $basePath, 'fotos-gerais', "global-photo-{$index}");
                    if (is_string($savedPath) && trim($savedPath) !== '') {
                        $savedGlobalPhotos[] = $savedPath;
                    }
                }

                if (! empty($savedGlobalPhotos)) {
                    $currentChecklist['photos'] = array_values($savedGlobalPhotos);
                } else {
                    unset($currentChecklist['photos']);
                }
            }

            $serviceType = $this->normalizeChecklistServiceType(
                $request->input('service_type', $currentChecklist['serviceType'] ?? $currentChecklist['service_type'] ?? null)
            );
            if ($serviceType !== '') {
                $currentChecklist['serviceType'] = $serviceType;
            } else {
                unset($currentChecklist['serviceType'], $currentChecklist['service_type']);
            }

            $signatureSyncResult = $this->synchronizeChecklistSignatureReferences(
                $currentChecklist,
                $basePath,
                $signatureItemMetaByAlias,
                $signatureItemTargetsByTopLevelKey
            );
            $currentChecklist = $signatureSyncResult['checklist'];
            $signatureKeepPaths = $signatureSyncResult['paths'];

            $currentPhotoReferences = $this->collectChecklistPrivatePhotoReferences($currentChecklist, $basePath);
            $removedPhotoReferences = array_values(array_diff($existingPhotoReferences, $currentPhotoReferences));

            $recommendationText = $this->resolveCommissionRecommendationText($currentChecklist, $ordemMirrorUpdates);

            $this->syncServicesData($numeroOrdem, $servicesData);
            $currentChecklist = $checklistMonetaryRecalculator->recalculateForOrder($ordem, $currentChecklist);

            // 4. Save
            $ordemApi->checklist_json = $currentChecklist;
            $ordemApi->save();

            if (! empty($ordemMirrorUpdates)) {
                DB::table('Ordens')
                    ->where('Numero ordem', $numeroOrdem)
                    ->update($ordemMirrorUpdates);
            }

            /*
            $commissionIndicationService->ensureCommissionOrderForRecommendation(
                $ordemApi,
                $vendedor,
                $recommendationText
            );
            */

            $checklistChanges = $this->buildChecklistHistoryChanges(
                $existingChecklistSnapshot,
                $this->normalizeChecklistArray($currentChecklist),
                $itemTitleMap,
                null,
                null,
                $checklistAliases
            );

            if ($this->snapshotServices($numeroOrdem) !== $existingServicesSnapshot) {
                $checklistChanges[] = 'Serviços da OS alterados';
            }

            if (! empty($checklistChanges)) {
                $this->upsertChecklistHistoryEntry(
                    (int) $numeroOrdem,
                    $vendedor,
                    'Alteração no checklist',
                    $checklistChanges
                );
            }

            DB::commit();

            if (! empty($removedPhotoReferences)) {
                $this->deleteChecklistPrivatePhotos($removedPhotoReferences);
            }

            $this->deleteChecklistSignatureFilesExcept($basePath, $signatureKeepPaths ?? []);

            Log::info('Checklist (Web) saved for OS: '.$numeroOrdem);

            return response()->json(['success' => true, 'message' => 'Checklist salvo com sucesso!']);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ChecklistController Update Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Erro ao salvar checklist: '.$e->getMessage()], 500);
        }
    }

    private function allowLongRunningChecklistRequest(): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('default_socket_timeout', '3600');

        try {
            DB::statement('SET LOCK_TIMEOUT -1');
        } catch (\Throwable $e) {
            Log::debug('Nao foi possivel ajustar LOCK_TIMEOUT para checklist.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function snapshotServices($numeroOrdem): array
    {
        return OrdemMaodeObra::where('Numero ordem', $numeroOrdem)
            ->orderBy('Item')
            ->get()
            ->map(function ($service) {
                return [
                    'descricao' => (string) ($service->{'Descrição serviços'} ?? ''),
                    'qtd' => (float) ($service->{'Qtde'} ?? 0),
                    'valor_unit' => (float) ($service->{'Valor unitario'} ?? 0),
                    'valor_inf' => (float) ($service->{'Valor informado'} ?? 0),
                    'valor_total' => (float) ($service->{'Valor total'} ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function syncServicesData($numeroOrdem, array $servicesData): void
    {
        OrdemMaodeObra::where('Numero ordem', $numeroOrdem)->delete();

        $normalizedServices = collect($servicesData)
            ->map(function ($service) {
                if (! is_array($service)) {
                    return null;
                }

                $descricao = trim((string) ($service['descricao'] ?? ''));
                if ($descricao === '') {
                    return null;
                }

                $qtd = max(0, (float) ($service['qtd'] ?? 0));
                $valorUnit = (float) ($service['valorUnit'] ?? $service['valor_unitario'] ?? $service['preco'] ?? 0);
                $valorInf = (float) ($service['valorInf'] ?? $service['valor_inf'] ?? $service['valor_informado'] ?? 0);
                $usarValorInformado = filter_var(
                    $service['usarValorInformado'] ?? $service['usar_valor_informado'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                $precoEfetivo = $usarValorInformado ? $valorInf : $valorUnit;

                return [
                    'descricao' => $descricao,
                    'qtd' => $qtd,
                    'valor_unit' => $valorUnit,
                    'valor_inf' => $usarValorInformado ? $valorInf : 0,
                    'valor_total' => $qtd * $precoEfetivo,
                    'iss' => (float) ($service['iss'] ?? 0),
                ];
            })
            ->filter()
            ->values();

        foreach ($normalizedServices as $index => $service) {
            $novoServico = new OrdemMaodeObra;
            $novoServico->{'Numero ordem'} = $numeroOrdem;
            $novoServico->Item = $index + 1;
            $novoServico->{'Descrição serviços'} = $service['descricao'];
            $novoServico->Qtde = $service['qtd'];
            $novoServico->{'Valor unitario'} = $service['valor_unit'];
            $novoServico->{'Valor informado'} = $service['valor_inf'];
            $novoServico->{'Valor total'} = $service['valor_total'];
            $novoServico->Iss = $service['iss'];
            $novoServico->{'No os cliente'} = '';
            $novoServico->Feito = 0;
            $novoServico->save();
        }

        $this->recalcularTotaisOrdem((int) $numeroOrdem);
    }

    private function recalcularTotaisOrdem(int $numeroOrdem): void
    {
        $totalServicos = DB::table('Ordens maodeobra')
            ->where('Numero ordem', $numeroOrdem)
            ->sum('Valor total');

        $totalIcms = DB::table('Ordens pecas')
            ->where('Numero ordem', $numeroOrdem)
            ->sum('Valor icms');

        $totalPecas = DB::table('Ordens pecas')
            ->where('Numero ordem', $numeroOrdem)
            ->sum('Valor item');

        DB::table('Ordens')
            ->where('Numero ordem', $numeroOrdem)
            ->update([
                'Valor serviços' => $totalServicos,
                'Valor icms' => $totalIcms,
                'Valor total' => $totalPecas + $totalServicos,
            ]);
    }

    private function saveImage($base64, $basePath, $subfolder, $customFilename = null)
    {
        if (! is_string($base64)) {
            return $base64;
        }

        $normalizedPrivatePath = $this->normalizePrivateImagePath($base64);
        if ($normalizedPrivatePath) {
            return $normalizedPrivatePath;
        }

        $normalizedPublicPath = $this->normalizePublicImagePath($base64);
        if ($normalizedPublicPath) {
            return $normalizedPublicPath;
        }

        if (strpos($base64, 'data:image') !== 0) {
            return $base64; // Already a path or not an image
        }

        try {
            $imageParts = explode(',', $base64);
            if (count($imageParts) != 2) {
                return $base64;
            }

            $decoded = base64_decode($imageParts[1]);
            $hash = md5($decoded);
            $filename = $customFilename ? "{$customFilename}-{$hash}" : $hash;
            $path = "{$basePath}/{$subfolder}/{$filename}.jpg";

            // Ensure directory
            if (! Storage::disk('local')->exists("{$basePath}/{$subfolder}")) {
                Storage::disk('local')->makeDirectory("{$basePath}/{$subfolder}");
            }

            if (! Storage::disk('local')->exists($path)) {
                // Try to optimize/convert to JPG using GD if available
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
                    $jpgContent = ob_get_clean();
                    imagedestroy($image);
                    imagedestroy($whiteImage);
                    Storage::disk('local')->put($path, $jpgContent);
                } else {
                    Storage::disk('local')->put($path, $decoded);
                }
            }

            return $path;
        } catch (\Exception $e) {
            Log::error('saveImage Error: '.$e->getMessage());

            return $base64;
        }
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

    private function buildChecklistItemTitleMap($sections): array
    {
        $map = [];
        foreach ($sections as $section) {
            foreach ($section->items ?? [] as $item) {
                $title = trim((string) ($item->title ?? $item->label ?? ''));
                if ($title === '') {
                    continue;
                }

                if (isset($item->id)) {
                    $map[(string) $item->id] = $title;
                }
                if (isset($item->code)) {
                    $map[(string) $item->code] = $title;
                }
            }
        }

        return $map;
    }

    private function buildChecklistAliases($sections): array
    {
        $sectionAliases = [];
        $itemAliases = [];

        foreach ($sections as $section) {
            $sectionId = trim((string) ($section->id ?? ''));
            $sectionCode = trim((string) ($section->code ?? ''));

            if ($sectionId !== '') {
                $sectionAliases[$sectionId] = $sectionId;
            }
            if ($sectionCode !== '') {
                $sectionAliases[$sectionCode] = $sectionId !== '' ? $sectionId : $sectionCode;
            }

            foreach ($section->items ?? [] as $item) {
                $itemId = trim((string) ($item->id ?? ''));
                $itemCode = trim((string) ($item->code ?? ''));

                if ($itemId !== '') {
                    $itemAliases[$itemId] = $itemId;
                }
                if ($itemCode !== '') {
                    $itemAliases[$itemCode] = $itemId !== '' ? $itemId : $itemCode;
                }
            }
        }

        return [
            'sections' => $sectionAliases,
            'items' => $itemAliases,
        ];
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

    private function resolveChecklistMirrorMapping($itemId, $itemCode, $itemTitle): ?array
    {
        foreach ($this->getChecklistMirrorMappings() as $mapping) {
            if ($this->matchesChecklistMirrorMapping($itemId, $itemCode, $itemTitle, $mapping)) {
                return $mapping;
            }
        }

        return null;
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
        if (! is_array($checklistJson)) {
            return '';
        }

        return $this->normalizeChecklistServiceType(
            $checklistJson['serviceType'] ?? $checklistJson['service_type'] ?? ''
        );
    }

    private function resolveCommissionRecommendationText(array $checklistJson, array $ordemMirrorUpdates): string
    {
        if (array_key_exists('Complemento solucao', $ordemMirrorUpdates)) {
            return (string) $this->normalizeChecklistMirrorText($ordemMirrorUpdates['Complemento solucao']);
        }

        foreach ($this->getChecklistMirrorMappings() as $mapping) {
            if (($mapping['ordem_column'] ?? null) !== 'Complemento solucao') {
                continue;
            }

            return (string) ($this->extractChecklistMirrorValue($checklistJson, $mapping) ?? '');
        }

        return '';
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
                'ordem_column' => 'Compl solução orça',
                'item_ids' => ['25'],
                'item_codes' => ['4.01', '4.1'],
                'item_titles' => ['Descrição do serviço executado', 'Compl solução orça'],
            ],
            [
                'ordem_column' => 'Complemento solucao',
                'item_ids' => ['26'],
                'item_codes' => ['4.02', '4.2'],
                'item_titles' => ['Recomendação técnica ou comercial', 'Complemento solucao'],
            ],
            [
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
                    if ($this->valueChanged($oldField, $newField)) {
                        $changes[] = "{$itemLabel} (campo {$fieldKey}): valor alterado";
                    }
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

        if (is_array($raw) && (
            array_key_exists('v', $raw) ||
            array_key_exists('o', $raw) ||
            array_key_exists('f', $raw) ||
            array_key_exists('p', $raw)
        )) {
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

    private function resolveHistoricoVendedor(string $fallback = 'Painel'): string
    {
        $user = Auth::user();
        if (! $user) {
            return $fallback;
        }

        $funcionario = Funcionarios::where('Email', $user->email)->first();
        if ($funcionario) {
            $nome = trim((string) ($funcionario->{'Nome conhecido'} ?? $funcionario->{'Nome funcionario'} ?? ''));
            if ($nome !== '') {
                return $nome;
            }
        }

        $nomeUser = trim((string) ($user->name ?? ''));

        return $nomeUser !== '' ? $nomeUser : $fallback;
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
            'vendedor' => $vendedor ?: 'Painel',
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
}

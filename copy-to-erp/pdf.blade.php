<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Orçamento #{{ $orcamento->{'Número orçamento'} ?? '---' }}</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
        }

        @page {
            margin: 150px 30px 90px 30px; /* espaço maior para o cabeçalho */
        }

        header {
            position: fixed;
            top: -140px; /* cabeçalho mais alto */
            left: 0;
            right: 0;
            text-align: center;
        }

        /* Deixei o h1 e os parágrafos do header com menos margem para compactar */
        header h1 {
            margin: 0 0 2px 0;
            font-size: 12px;
            line-height: 1;
        }

        header p {
            margin: 1px 0;
            line-height: 1.05;
            font-size: 11px;
        }

        footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            color: #555;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        img.logo {
            width: 150px;
            display: block;
            margin: 5px auto;
        }

        /* --- AQUI: espaçamento do título do orçamento reduzido --- */
        .orcamento-title {
            text-align: center;
            background-color: #454545;
            color: #fff;
            padding: 4px 0;        /* menos padding vertical */
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .info,
        .items,
        .totais {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info td {
            padding: 2px 4px;
            font-size: 11px;
            line-height: 1.2;
            vertical-align: top;
        }

        .info strong {
            color: #555;
        }

        .two-columns {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .two-columns td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        .column-table {
            width: 100%;
            border-collapse: collapse;
        }

        .column-table td {
            padding: 3px 4px;
            font-size: 11px;
            border-bottom: 1px solid #f0f0f0;
        }

        .column-table tr:last-child td {
            border-bottom: none;
        }

        .items th {
            background-color: #454545;
            color: #fff;
            padding: 6px;
            font-size: 11px;
            text-align: center;
        }

        .items td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 11px;
            text-align: center;
        }

        .items td:nth-child(4),
        .items td:nth-child(5) {
            max-width: 300px;;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-all;
            white-space: normal;
        }

        /* --- Totais --- */
        .totais-wrapper {
            page-break-inside: avoid; /* não quebra no meio */
            margin-top: 10px;
        }

        .totais td {
            padding: 4px;
            font-size: 11px;
        }

        .totais tr.bold td {
            font-weight: bold;
            font-size: 12px;
            border-top: 2px solid #333;
        }

        .totais .left {
            text-align: left;
        }

        .totais .right {
            text-align: right;
        }

        p.obs {
            font-size: 11px;
            margin: 10px 0;
            padding: 8px;
            background-color: #fff3cd;
            border-left: 4px solid #ffeeba;
            border-radius: 3px;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>

    @php
        $logoPath = public_path('logo.png');
        $logoBase64 = 'data:image/' . pathinfo($logoPath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($logoPath));

        $novidadesPath = public_path('novidades.png');

        // Pré-carrega todos os arquivos para evitar acesso repetido ao disco
        $allFiles = \Illuminate\Support\Facades\Storage::disk('public')->allFiles();

        if (!function_exists('getFolderForSku')) {
            function getFolderForSku($sku, $arquivos) {
                $pastas = [];
                foreach ($arquivos as $arquivo) {
                    // $arquivo é caminho relativo, ex: "produtos/123-item/foto.jpg"
                    $dir = dirname($arquivo);
                    $filename = basename($arquivo);
                    
                    $parts = explode('-', strtolower($filename));
                    $numericPrefixes = [];
                    foreach ($parts as $part) {
                        if (preg_match('/^[0-9]+$/', $part)) {
                            $numericPrefixes[] = $part;
                        } else {
                            break;
                        }
                    }

                    if (in_array((string)$sku, $numericPrefixes, true)) {
                        $pastas[] = $dir;
                    }
                }
                
                if (empty($pastas)) return 'produtos';
                $pastas = array_unique($pastas);
                if (count($pastas) === 1) return current($pastas);
                
                // Prioriza pastas que não sejam 'produtos'
                foreach ($pastas as $p) {
                    if ($p !== 'produtos' && $p !== '.') return $p;
                }
                return 'produtos';
            }
        }

        if (!function_exists('findProductImagePath')) {
            function findProductImagePath($sku, $allFiles) {
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                $folder = getFolderForSku($sku, $allFiles);
                // Folder no storage pode ser "." ou "produtos" ou "produtos/sub"
                // O path em $allFiles é relativo à raiz do disco public

                // Filtrar arquivos da pasta e do SKU
                $imagens = [];
                foreach ($allFiles as $file) {
                    // Verifica se está na pasta correta (ou subpasta se a lógica do controller for recursiva, mas lá parece ser flat no folder retornado)
                    // Controller: $prefix = $folder . '/'; ... str_starts_with($arquivo, $prefix) ...
                    // Vamos replicar
                    $prefix = $folder == '.' ? '' : $folder . '/';
                    
                    // Se folder for 'produtos', prefix é 'produtos/'
                    if ($prefix !== '' && !str_starts_with($file, $prefix)) {
                        continue;
                    }
                    
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt, true)) continue;

                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    $parts = preg_split('/[-_ ]/', strtolower($filename));
                    $initialNumbers = [];
                    foreach ($parts as $part) {
                        if (preg_match('/^[0-9]+$/', $part)) {
                            $initialNumbers[] = $part;
                        } else {
                            break;
                        }
                    }

                    if (in_array((string)$sku, $initialNumbers, true)) {
                        $imagens[] = $file;
                    }
                }
                
                if (empty($imagens)) return null;

                // Ordenar logicamente (1), (2)...
                usort($imagens, function($a, $b) {
                    $fa = basename($a);
                    $fb = basename($b);
                    $ma = 0; $mb = 0;
                    if (preg_match('/\((\d+)\)\.[a-z]+$/i', $fa, $matches)) $ma = (int)$matches[1];
                    if (preg_match('/\((\d+)\)\.[a-z]+$/i', $fb, $matches)) $mb = (int)$matches[1];
                    return $ma <=> $mb;
                });

                // Retorna o caminho absoluto para o primeiro arquivo
                return public_path('storage/' . $imagens[0]);
            }
        }

        if (!function_exists('imageToFlattenedBase64')) {
            function imageToFlattenedBase64($path, $quality = 90) {
                if (!$path || !file_exists($path) || !is_readable($path)) return null;

                $imageInfo = @getimagesize($path);
                if (!$imageInfo) return null;

                $originalWidth = (int) ($imageInfo[0] ?? 0);
                $originalHeight = (int) ($imageInfo[1] ?? 0);

                // Evita carregar imagens muito grandes no worker; para o PDF final,
                // a miniatura renderizada tem 45x45, então não vale consumir tanta memória.
                $maxSourcePixels = 12000000; // ~12 MP
                if (
                    $originalWidth <= 0 ||
                    $originalHeight <= 0 ||
                    ($originalWidth * $originalHeight) > $maxSourcePixels
                ) {
                    return null;
                }

                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                
                // Tenta criar imagem a partir do path
                $src = null;
                if ($ext === 'png' && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($path);
                elseif (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($path);
                elseif ($ext === 'gif' && function_exists('imagecreatefromgif')) $src = @imagecreatefromgif($path);
                elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($path);
                elseif ($ext === 'bmp' && function_exists('imagecreatefrombmp')) $src = @imagecreatefrombmp($path);

                if (!$src) {
                    // Tenta carregar por string (às vezes funciona se a lib suportar mas a função específica não estiver exposta ou por auto-detecção)
                    $content = @file_get_contents($path);
                    if ($content) {
                        $src = @imagecreatefromstring($content);
                    }
                }

                if (!$src && class_exists('Imagick')) {
                        try {
                        $im = new Imagick($path);
                        $im->setImageBackgroundColor('white');
                        $flattened = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                        $im->setImageFormat('jpeg');
                        $im->setImageCompressionQuality($quality);
                        return 'data:image/jpeg;base64,' . base64_encode($im->getImageBlob());
                    } catch (\Exception $e) { }
                }

                if (!$src) {
                    // Se chegamos aqui, falhamos em criar o recurso GD com as funções específicas e com imagecreatefromstring.
                    // Isso significa que o servidor provavelmente NÃO suporta o formato da imagem (ex: WebP sem gd-webp).
                    // Se retornarmos o data-uri raw para o DomPDF, ele tentará processar internamente e CRASHARÁ se faltar a lib.
                    
                    $content = @file_get_contents($path);
                    if (!$content) return null;

                    // Detecção agressiva de WebP para evitar crash
                    // O header WebP: bytes 0-3 "RIFF", bytes 8-11 "WEBP"
                    $isWebP = false;
                    if (strlen($content) > 12) {
                        if (strpos($content, 'WEBP') !== false && strpos($content, 'RIFF') !== false) {
                            $isWebP = true;
                        }
                    }
                    if ($ext === 'webp') $isWebP = true;

                    if ($isWebP) {
                        return null; // Abortar para segurança
                    }

                    return 'data:image/' . $ext . ';base64,' . base64_encode($content);
                }

                $w = imagesx($src);
                $h = imagesy($src);

                // O PDF exibe a foto em 45x45. Gerar um quadrado com o tamanho original
                // da imagem desperdiça memória e derruba o worker com fotos grandes.
                $thumbSize = 160;
                $scale = min($thumbSize / max($w, 1), $thumbSize / max($h, 1), 1);
                $resizedWidth = max(1, (int) round($w * $scale));
                $resizedHeight = max(1, (int) round($h * $scale));

                $bg = imagecreatetruecolor($thumbSize, $thumbSize);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);

                $dstX = (int) floor(($thumbSize - $resizedWidth) / 2);
                $dstY = (int) floor(($thumbSize - $resizedHeight) / 2);

                imagecopyresampled(
                    $bg,
                    $src,
                    $dstX,
                    $dstY,
                    0,
                    0,
                    $resizedWidth,
                    $resizedHeight,
                    $w,
                    $h
                );

                ob_start();
                imagejpeg($bg, null, $quality);
                $imageData = ob_get_clean();

                imagedestroy($src);
                imagedestroy($bg);

                return 'data:image/jpeg;base64,' . base64_encode($imageData);
            }
        }

        $novidadesBase64 = imageToFlattenedBase64($novidadesPath);
    @endphp

    <!-- HEADER -->
    <header>
        <table style="width: 100%; text-align: center;">
            <tr>
                <td style="width: 140px; vertical-align: middle; text-align: center;">
                    <a href="https://tecnoarcompressores.com.br/?utm_source=orcamento&utm_medium=pdf&utm_campaign=orcamento-pdf" target="_blank" style="display: block; width: fit-content; margin: 0 auto;">
                        <img src="{{ $logoBase64 }}" alt="Logo" style="width: 130px; display: block;">
                    </a>
                </td>

                <td style="vertical-align: middle; text-align: center; font-size: 12px; padding: 0 10px;">
                    <h1>
                        Tecnoar Técnica e Comércio de Compressores Ltda - EPP
                    </h1>
                    <p>
                        {{ $filial->{'Endereço'} . ', ' . $filial->{'Numero'} }} | CEP: {{ $filial->{'Cep'} }} |
                        {{ $filial->{'Bairro'} }} - {{ $filial->{'Cidade'} . ' / ' . $filial->{'Uf'} }}
                    </p>
                    <p>
                        CNPJ: {{ $filial->{'Cnpj'} }} | I.M.: {{ $filial->{'Inscrição municipal'} }} | I.E.: {{ $filial->{'Inscricao estadual'} }}
                    </p>
                    <p>
                        Site: www.tecnoarcompressores.com.br | Tel.: {{ $filial->{'Fone'} }}
                    </p>
                    <p style="font-weight: bold;">Empresa do Regime Normal</p>
                </td>

                <td style="width: 140px; vertical-align: middle; text-align: center;">
                    <a href="https://linktr.ee/tecnoarcompressores1" target="_blank" style="display: block; width: fit-content; margin: 0 auto;">
                        <img src="{{ $novidadesBase64 }}" alt="Novidades" style="width:130px; display:block;">
                    </a>
                </td>
            </tr>
        </table>
    </header>

    <!-- FOOTER -->
    <footer>
        <strong>Tecnoar Técnica e Comércio de Compressores Ltda - EPP</strong>
    </footer>

    <!-- CONTEÚDO -->
    <main>
        <div class="orcamento-title">
            ORÇAMENTO Nº {{ $orcamento->{'Número orçamento'} ?? '---' }} -
            {{ $orcamento->{'Data emissão'} ? \Carbon\Carbon::parse($orcamento->{'Data emissão'})->format('d/m/Y') : '---' }}
        </div>

        <table class="two-columns">
            <tr>
                <td>
                    <!-- Primeira Coluna: Informações do Cliente -->
                    <table class="column-table">
                        <tr><td><strong>Cliente:</strong> {{ $cliente->{'Nome cliente'} ?? $orcamento->{'Cliente'} }}</td></tr>
                        <tr><td><strong>{{ $tipoDocumento }}:</strong> {{ $documento ?? '' }}</td></tr>
                        <tr><td><strong>E-mail:</strong> {{ $cliente->{'E-mail'} ?? '' }}</td></tr>
                        <tr><td><strong>Endereço:</strong> {{ $cliente->Endereco . ', ' . $cliente->{'Numero'} }}</td></tr>
                        <tr><td><strong>Fones:</strong>
        @if($cliente->telefones->isNotEmpty())
            {{ $cliente->telefones->pluck('Fone')->implode(' / ') }}
        @else
            <em>Sem telefone</em>
        @endif
    </td></tr>
                        <tr><td><strong>A/C:</strong> {{ $orcamento->{'Destinatário'} ?? ''}}</td></tr>
                    </table>
                </td>
                <td>
                    <!-- Segunda Coluna: Informações do Orçamento -->
                    <table class="column-table">
                        <tr><td><strong>Vendedor:</strong> {{ $orcamento->{'Responsável'} ?? '' }}</td></tr>
                        <tr><td><strong>Garantia:</strong> {{ $orcamento->{'Garantia orcamento'} ?? '' }}</td></tr>
                        <tr><td><strong>Validade:</strong> {{ $orcamento->{'Validade'} }}</td></tr>
                        <tr><td><strong>Cond. Pagamento:</strong> {{ $orcamento->{'Condições pagto'} }}</td></tr>
                        <tr><td><strong>Prazo Entrega:</strong> {{ $orcamento->{'Prazo'} ?? '' }}</td></tr>
                        <tr><td><strong>Frete:</strong> {{ $orcamento->Frete ?? '' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="width: 50px;">Foto</th> <!-- Imagem -->
                    <th>Código</th>
                    <th>Descrição</th>
                    <th>NCM</th>
                    <th style="white-space: nowrap;">ICMS %</th>
                    <th>V.ICMS</th>
                    <th style="white-space: nowrap;">Red. %</th>
                    <th>Unit.</th>
                    <th>Qtd</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orcamentoItens as $i => $item)
                    @php
                        $valor = (float) $item->Qtde;
                        $qtd = strpos($valor, '.') === false ? (string) $valor : rtrim(rtrim(number_format($valor, 10, ',', '.'), '0'), ',');
                        if (fmod($valor, 1) == 0) {
                            $qtd = number_format($valor, 0, ',', '.');
                        }

                        $peca = $item->pecaByCodigo;
                        $preco = (!empty($item->{'Preço real'}) && $item->{'Preço real'} != 0)
                            ? $item->{'Preço real'}
                            : $item->{'Preço unitário'};
                        $totalProduto = $item->Qtde * $preco;

                        // Busca Imagem
                        $sku = $peca->{'Codigo peca'} ?? '';
                        $imgPath = findProductImagePath($sku, $allFiles);
                        $imgBase64 = $imgPath ? imageToFlattenedBase64($imgPath) : null;
                    @endphp
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td style="width: 50px; text-align: center; vertical-align: middle;">
                            @if($imgBase64)
                                <img src="{{ $imgBase64 }}" style="width: 45px; height: 45px; object-fit: contain; display: block; margin: 0 auto;">
                            @else
                                <div style="width: 45px; height: 45px; background-color: #fff; margin: 0 auto;"></div>
                            @endif
                        </td>
                        <td>{{ $peca->{'Codigo peca'} ?? '' }}</td>
                        <!-- Força quebra de linha na descrição -->
                        <td style="text-align:left; word-break: break-all; max-width: 210px;">{{ $item->{'Descrição peça'} }}</td>
                        <td style="text-align:left;">{{ $peca->{'Classificação fiscal'} ?? '' }}</td>
                        <td>{{ number_format($item->Icms1, 0) }}</td>
                        <td>{{ number_format($item->{'Valor icms'}, 2, ',', '.') }}</td>
                        <td>{{ number_format($item->{'Reducao base'}, 0) }}</td>
                        <td>{{ number_format($preco, 2, ',', '.') }}</td>
                        <td>{{ $qtd }}</td>
                        <td>{{ number_format($totalProduto, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totais -->
        <div class="totais-wrapper">
            <table class="totais">
                @php
                    $total = $orcamento->{'Total valor'} + $orcamento->{'Valor frete'} - $orcamento->{'Valor desconto'};
                @endphp
                <tr>
                    <td class="left" style="color:red;"><strong>Nosso estoque é rotativo, não fazemos reservas.</strong></td>
                    <td class="right"><strong>Crédito ICMS:</strong> R$ {{ number_format($orcamento->{'Valor icms'}, 2, ',', '.') }}</td>
                </tr>
                @if (!empty($observacoesFaturamentoFilial))
                    <tr>
                        <td class="left" style="color:red; max-width: 520px; white-space: pre-wrap;"><strong>{{ implode("\n", $observacoesFaturamentoFilial) }}</strong></td>
                        <td class="right"><strong>Subtotal:</strong> R$ {{ number_format($orcamento->{'Total valor'}, 2, ',', '.') }}</td>
                    </tr>
                @else
                    <tr>
                        <td colspan="2" class="right"><strong>Subtotal:</strong> R$ {{ number_format($orcamento->{'Total valor'}, 2, ',', '.') }}</td>
                    </tr>
                @endif
                @if ($orcamento->{'Valor frete'} != 0)
                    <tr>
                        <td colspan="2" class="right"><strong>Frete:</strong> R$ {{ number_format($orcamento->{'Valor frete'}, 2, ',', '.') }}</td>
                    </tr>
                @endif
                @if ($orcamento->{'Valor desconto'} != 0)
                    <tr>
                        <td colspan="2" class="right"><strong>Desconto:</strong> R$ {{ number_format($orcamento->{'Valor desconto'}, 2, ',', '.') }}</td>
                    </tr>
                @endif
                <tr class="bold">
                    <td colspan="2" class="right"><strong>TOTAL:</strong> R$ {{ number_format($total, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        @if ($orcamento->Obs != '')
            <p class="obs"><strong>OBS:</strong> {{$orcamento->Obs}}</p>
        @endif
    </main>
</body>
</html>

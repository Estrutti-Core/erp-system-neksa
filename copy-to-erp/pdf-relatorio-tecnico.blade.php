<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Ordem #{{ $ordem->{'Numero ordem'} ?? '---' }}</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
        }

        @page {
            margin: 150px 30px 90px 30px;
        }

        header {
            position: fixed;
            top: -140px;
            left: 0;
            right: 0;
            text-align: center;
        }

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

        img.logo, img.novidades {
            width: 150px;
            display: block;
            margin: 5px auto;
        }

        .orcamento-title {
            text-align: center;
            background-color: #454545;
            color: #fff;
            padding: 4px 0;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .info td {
            padding: 2px 4px;
            font-size: 11px;
            line-height: 1.2;
            vertical-align: top;
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

        .items, .info {
            width: 100%;
            border-collapse: collapse;
        }

        .section-title {
            width: 100%;
            text-align: center;
            border-bottom: 1px solid #000;
            line-height: 0.1em;
            margin: 8px 0 2px 0;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .items th {
            background-color: #454545;
            color: #fff;
            padding: 2px;
            font-size: 8.5px;
            text-align: center;
        }

        .items td {
            border: 1px solid #ddd;
            padding: 2px;
            font-size: 8.5px;
            text-align: center;
            white-space: nowrap;
        }

        .items td:nth-child(4) {
            white-space: normal;
            text-align: left;
        }

        /* --- Totais --- */
        .totais-wrapper {
            page-break-inside: avoid;
            /* não quebra no meio */
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


        .section-title {
            width: 100%;
            text-align: center;
            border-bottom: 1px solid #000;
            line-height: 0.1em;
            margin: 10px 0 4px 0;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }

        .section-title span {
            background: #fff;
            padding: 0 10px;
        }

        /* --- Estilos para campos manuscritos --- */
        .handwritten-section {
            width: 100%;
            margin-top: 8px;
            page-break-inside: avoid;
        }

        .approval-header {
            width: 100%;
            margin-bottom: 5px;
            font-size: 10px;
            font-weight: bold;
        }

        .line-box {
            border: 1px solid #000;
            width: 100%;
            margin-bottom: 5px;
        }

        .line-item {
            border-bottom: 1px solid #ddd;
            height: 16px;
            width: 100%;
        }

        .line-item:last-child {
            border-bottom: none;
        }

        .visto-grid {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }

        .visto-grid td {
            border: 1px solid #000;
            padding: 5px 3px;
            text-align: center;
            font-size: 10px;
            vertical-align: top;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin: 8px auto 3px auto;
            width: 80%;
            height: 1px;
        }

        .survey-box {
            text-align: left;
            padding: 3px;
        }

        .checkbox-item {
            display: inline-block;
            margin-right: 10px;
        }

        .checkbox {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000;
            margin-left: 3px;
            vertical-align: middle;
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

                // Criar quadrado branco ou manter ratio?
                // "independente das dimensões... preciso que, se houver mais de um produto... eles precisam vir todos quadrados"
                // Geralmente isso significa "thumbnail quadrada".
                // Vou redimensionar para um quadrado de 50x50 ou similar para economizar bytes e garantir formato?
                // O request diz: "ao lado esquerdo do Item... como fosse uma coluna sem titulo, tenha a imagem do produto... quadrados"
                // Vou criar uma imagem quadrada, centralizando o produto com fundo branco.
                
                $sqSize = max($w, $h);
                $bg = imagecreatetruecolor($sqSize, $sqSize);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                
                // Centralizar
                $dstX = ($sqSize - $w) / 2;
                $dstY = ($sqSize - $h) / 2;
                
                imagecopy($bg, $src, (int)$dstX, (int)$dstY, 0, 0, $w, $h);

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

    <header>
        <table style="width: 100%; text-align: center;">
            <tr>
                <td style="width: 140px; vertical-align: middle; text-align: center;">
                    <a href="https://tecnoarcompressores.com.br/?utm_source=orcamento&utm_medium=pdf&utm_campaign=orcamento-pdf"
                        target="_blank" style="display: block; width: fit-content; margin: 0 auto;">
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
                        CNPJ: {{ $filial->{'Cnpj'} }} | I.M.: {{ $filial->{'Inscrição municipal'} }} | I.E.:
                        {{ $filial->{'Inscricao estadual'} }}
                    </p>
                    <p>
                        Site: www.tecnoarcompressores.com.br | Tel.: {{ $filial->{'Fone'} }}
                    </p>
                    <p style="font-weight: bold;">Empresa do Regime Normal</p>
                </td>

                <td style="width: 140px; vertical-align: middle; text-align: center;">
                    <a href="https://linktr.ee/tecnoarcompressores1" target="_blank"
                        style="display: block; width: fit-content; margin: 0 auto;">
                        <img src="{{ $novidadesBase64 }}" alt="Novidades" style="width:130px; display:block;">
                    </a>
                </td>
            </tr>
        </table>
    </header>


    <!-- CONTEÚDO -->
    <main>
        <!-- TÍTULO (Estilo Orçamento) -->
        <div class="orcamento-title">
            ORDEM DE SERVIÇO Nº {{ $ordem->{'Numero ordem'} ?? '---' }} -
            {{ $ordem->{'Data emissao'} ? \Carbon\Carbon::parse($ordem->{'Data emissao'})->format('d/m/Y') : '---' }}
        </div>

        <!-- DADOS DO CLIENTE -->
        <table class="two-columns">
            <tr>
                <td>
                    <!-- Primeira Coluna: Informações do Cliente -->
                    <table class="column-table">
                        <tr>
                            <td><strong>Cliente:</strong> {{ $ordem->{'Cliente'} }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ $tipoDocumento }}:</strong> {{ $documento ?? '' }}</td>
                        </tr>
                        <tr>
                            <td><strong>E-mail:</strong> {{ $cliente->{'E-mail'} ?? '' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Endereço:</strong> {{ $cliente->Endereco . ', ' . $cliente->{'Numero'} }}</td>
                        </tr>
                        <tr>
                            <td><strong>Fones:</strong>
                                @if($cliente->telefones->isNotEmpty())
                                    {{ $cliente->telefones->pluck('Fone')->implode(' / ') }}
                                @else
                                    <em>Sem telefone</em>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td>
                    <!-- Segunda Coluna: Informações do Orçamento -->
                    <table class="column-table">
                        <tr>
                            <td><strong>A/C:</strong> {{ $ordem->{'Contato'} ?? ''}}</td>
                        </tr>
                        <tr>
                            <td><strong>Garantia:</strong> {{ $ordem->{'Prazo'} ?? '' }}</td>
                        </tr>
                        @if (isset($validadeProposta))
                        <tr>
                            <td><strong>Validade:</strong> {{ $validadeProposta }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td><strong>Cond. Pagamento:</strong> {{ $ordem->{'Condições pagto'} }}</td>
                        </tr>
                        <tr>
                            <td><strong>Prazo Entrega:</strong> {{ $ordem->{'Prazo entrega'} ?? '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

<!-- DADOS DO APARELHO -->
<div class="section-title"><span>DADOS DO APARELHO</span></div>

<table class="two-columns">
    <tr>
        <td>
            <table class="column-table">
                <tr>
                    <td><strong>Marca:</strong> {{ $ordem->Marca ?? '-' }}</td>
                </tr>
                <tr>
                    <td><strong>Modelo:</strong> {{ $ordem->{'Modelo tabela'} ?? '-' }}</td>
                </tr>
                <tr>
                    <td><strong>Série:</strong> {{ $ordem->{'No série'} ?? '-' }}</td>
                </tr>
                <tr>
                    <td>
                        <strong>Estado do Aparelho:</strong>
                        {{ $ordem->{'Complemento problema'} ?? '-' }}
                    </td>
                </tr>
            </table>
        </td>

        <td>
            <table class="column-table">
                <tr>
                    <td><strong>Descrição:</strong> {{ $ordem->Equipto ?? '-' }}</td>
                </tr>
                <tr>
                    <td>
                        <strong>NF de Compra:</strong>
                        {{ $ordem->{'Nf compra'} ?? '-' }}
                        ({{ $ordem->{'Data nf compra'} ? \Carbon\Carbon::parse($ordem->{'Data nf compra'})->format('d/m/Y') : '-' }})
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>NF de Remessa:</strong>
                        {{ $ordem->{'Nf remessa'} ?? '-' }}
                        ({{ $ordem->{'Data nf remessa'} ? \Carbon\Carbon::parse($ordem->{'Data nf remessa'})->format('d/m/Y') : '-' }})
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Acessórios:</strong>
                        {{ $ordem->{'Complemento equipamento'} ?? '-' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="column-table" style="margin-top: 5px;">
    <tr>
        <td>
            <strong>Defeito Reclamado:</strong>
            {{ $ordem->{'Descrição problema'} ?? 'Não informado.' }}
        </td>
    </tr>
</table>

        <!-- TABELA DE PEÇAS -->
        <!-- TABELA DE PEÇAS -->
        <div class="section-title"><span>PEÇAS / ITENS</span></div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 30px;">Qtde</th>
                    <th style="width: 40px;">Qtde. US</th>
                    <th style="width: 60px;">Código</th>
                    <th>Descrição</th>
                    <th style="width: 60px;">Unit</th>
                    <th style="width: 60px;">R$ sub</th>
                    <th style="width: 60px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ordem->pecas as $item)
                    @php
                        $peca = \App\Models\Pecas::where('Descricao peca', $item->Peça)->first();
                        $codigoPeca = $peca?->{'Codigo peca'} ?? '—';
                        $valor = (float) $item->Qtde;
                        $qtd = ($valor == floor($valor)) ? number_format($valor, 0, ',', '.') : rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
                        $preco = (!empty($item->{'Valor informado'}) && $item->{'Valor informado'} != 0) ? $item->{'Valor informado'} : $item->{'Valor tabela'};
                        $totalItem = (float) $valor * (float) $preco;
                    @endphp
                    <tr>
                        <td>{{ $qtd }}</td>
                        <td></td>
                        <td>{{ $codigoPeca }}</td>
                        <td style="text-align:left;">{{ $item->Peça }}</td>
                        <td>{{ number_format($preco, 2, ',', '.') }}</td>
                        <td></td>
                        <td>{{ number_format($totalItem, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- TABELA DE SERVIÇOS -->
        @if($ordem->servicos && $ordem->servicos->count() > 0)
            <div class="section-title"><span>SERVIÇOS</span></div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 30px;">Qtde</th>
                        <th style="width: 40px;">Qtde. US</th>
                        <th>Descrição</th>
                        <th style="width: 60px;">Unit</th>
                        <th style="width: 60px;">R$ sub</th>
                        <th style="width: 60px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ordem->servicos as $servico)
                        @php
                            $qtdServico = (float) ($servico->Qtde ?? 1);
                            $valorUnit = (float) ($servico->{'Valor unitario'} ?? 0);
                            $valorInf = (float) ($servico->{'Valor informado'} ?? 0);
                            
                            // Hierarquia: existe valor informado? exiba-o, se não, exiba o valor unitário
                            $valorExibir = ($valorInf > 0) ? $valorInf : $valorUnit;
                            $totalServico = $qtdServico * $valorExibir;
                        @endphp
                        <tr>
                            <td>{{ number_format($qtdServico, 0, ',', '.') }}</td>
                            <td></td>
                            <td style="text-align:left;">{{ $servico->{'Descrição serviços'} ?? '' }}</td>
                            <td>{{ number_format($valorExibir, 2, ',', '.') }}</td>
                            <td></td>
                            <td>{{ number_format($totalServico, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

@php
    $total = (float) ($ordem->{'Valor total'} ?? 0);
@endphp

        <!-- SEÇÕES MANUSCRITAS -->
        <div class="handwritten-section">
            <table class="approval-header">
                <tr>
                    <td style="width: 35%;">APROVADO POR: <span style="border-bottom: 1px solid #000; display: inline-block; width: 60%;">&nbsp;</span></td>
                    <td style="width: 20%;">DATA: <span style="border-bottom: 1px solid #000; display: inline-block; width: 50%;">&nbsp;</span></td>
                    <td style="width: 30%;">ASSINATURA: <span style="border-bottom: 1px solid #000; display: inline-block; width: 60%;">&nbsp;</span></td>
                    <td style="width: 15%; text-align: right;">TOTAL R$: <span style="font-size: 12px;">{{ number_format($total, 2, ',', '.') }}</span></td>
                </tr>
            </table>

            <div class="section-title"><span>SERVIÇOS EXECUTADOS</span></div>
            <div class="line-box">
                @for ($i = 0; $i < 4; $i++)
                    <div class="line-item"></div>
                @endfor
            </div>

            <div class="section-title"><span>PRÓXIMA MANUTENÇÃO</span></div>
            <div class="line-box">
                <div style="padding: 3px; font-weight: bold; font-size: 10px; border-bottom: 1px solid #ddd;">Material Necessário:</div>
                @for ($i = 0; $i < 3; $i++)
                    <div class="line-item"></div>
                @endfor
            </div>

            <div class="section-title"><span>VISTO</span></div>
            <table class="visto-grid">
                <tr>
                    <td style="width: 25%;">
                        <div class="signature-line"></div>
                        <div style="font-size: 10px;">Conferente Estoque</div>
                        <div style="margin-top: 15px;" class="signature-line"></div>
                        <div style="font-size: 10px;">Técnico executante</div>
                    </td>
                    <td style="width: 25%;">
                        <div class="signature-line"></div>
                        <div style="font-size: 10px;">Gerente de Serviços</div>
                        <div style="margin-top: 15px;" class="signature-line"></div>
                        <div style="font-size: 10px;">Gerente Geral</div>
                    </td>
                    <td style="width: 50%; text-align: left;">
                        <div class="survey-box">
                            <div style="margin-bottom: 5px; font-size: 10px;">Atendimento finalizado de forma satisfatório?</div>
                            <div class="checkbox-item">Sim <span class="checkbox"></span></div>
                            <div class="checkbox-item">Não <span class="checkbox"></span></div>
                            
                            <div style="margin-top: 10px; font-size: 10px;">
                                Data: _____ / _____ / _____
                            </div>

                            <div style="margin-top: 12px;">
                                <div style="border-bottom: 1px solid #000; width: 80%; margin-left: 0;"></div>
                                <div style="font-size: 9px; margin-top: 2px;">Carimbo/Assinatura Cliente</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </main>
</body>

</html>
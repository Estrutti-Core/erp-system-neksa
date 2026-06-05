<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de OS #{{ $ordem->{'Numero ordem'} }}</title>
    <style>
        @page {
            margin: 110px 25px 40px 25px;
        }

        @page :first {
            margin-top: 130px;
        }

        * {
            font-family: Arial, Helvetica, sans-serif !important;
        }

        body {
            font-size: 8px;
            color: #333;
            margin: 0;
            padding: 0;
            line-height: 1.1;
        }

        header {
            position: fixed;
            top: -105px;
            left: 0;
            right: 0;
            height: 100px;
            background: #fff;
        }

        footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 25px;
            text-align: center;
            border-top: 1px solid #7f7f7f;
            padding-top: 2px;
            font-size: 7px;
            color: #666;
        }

        .gray-bar {
            background-color: #f2f2f2;
            font-weight: bold;
            padding: 2px 6px;
            border: 1px solid #7f7f7f;
            font-size: 9px;
            text-align: center;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #7f7f7f;
            padding: 2px 4px;
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .label {
            font-size: 6px;
            font-weight: bold;
            color: #555;
            display: block;
            text-transform: uppercase;
            line-height: 1;
        }

        .value {
            font-size: 8px;
            font-weight: bold;
            color: #000;
        }

        .header-logo-box {
            text-align: center;
            border: 1px solid #7f7f7f;
            padding: 2px;
        }

        .section-header {
            background-color: #eee;
            font-weight: bold;
            padding: 2px 6px;
            border: 1px solid #7f7f7f;
            margin-top: 3px;
            text-transform: uppercase;
            font-size: 7.5px;
        }

        /* GRID EM 2 COLUNAS */
        .checklist-grid {
            width: 100%;
            border: 1px solid #7f7f7f;
            border-top: none;
            table-layout: fixed;
        }

        .checklist-grid td {
            width: 50%;
            border: 0.5px solid #ccc;
            padding: 2px 4px;
            font-size: 8px;
            vertical-align: top;
        }

        .item-wrapper {
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 0.1px solid #f0f0f0;
            page-break-inside: avoid;
        }

        .item-row {
            width: 100%;
            display: table;
        }

        .item-title {
            display: table-cell;
            width: 70%;
            vertical-align: top;
            padding-right: 2px;
            font-size: 8px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .item-main-text {
            font-weight: normal;
            color: #444;
        }

        .item-field-text {
            font-weight: normal !important;
            color: #444;
        }

        .item-value {
            font-weight: bold;
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: top;
            font-size: 8px;
            color: #000;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .item-obs {
            display: block;
            width: 100%;
            font-size: 6.5px;
            font-style: italic;
            color: #b31d1d;
            /* Destaque leve em vermelho para observação */
            margin-top: 1px;
            padding: 1px 0;
            word-wrap: break-word;
        }

        .item-full-text {
            display: block;
            width: 100%;
            font-size: 8px;
            margin-bottom: 2px;
            word-wrap: break-word;
        }

        .item-inline-response {
            display: inline;
            font-size: 8px;
            font-weight: bold;
            color: #000;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* GALERIA DE FOTOS */
        .section-gallery {
            padding: 3px;
            text-align: left;
            border: 1px solid #7f7f7f;
            border-top: none;
            background: #fff;
        }

        .gallery-table {
            width: 100%;
            border: none;
            margin: 0;
            table-layout: fixed;
        }

        .gallery-image-box {
            display: block;
            width: 100%;
            height: 180px;
            border: 0.5px solid #eee;
            border-radius: 12px;
            box-sizing: border-box;
            overflow: hidden;
            text-align: center;
            line-height: 180px;
        }

        .gallery-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .gallery-table td {
            width: 25%;
            border: none;
            padding: 3px;
            vertical-align: top;
            page-break-inside: avoid;
        }

        .signature-in-grid {
            max-height: 120px;
            max-width: 100%;
            display: block;
            margin: 0 auto;
        }

        .pagenum:before {
            content: counter(page);
        }

        .compact-table th,
        .compact-table td {
            padding: 1px 4px;
            font-size: 7.5px;
        }

        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>
    @php
        $logoPath = public_path('logo.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/' .
                pathinfo($logoPath, PATHINFO_EXTENSION) .
                ';base64,' .
                base64_encode(file_get_contents($logoPath))
            : null;
    @endphp

    <header>
        <div class="gray-bar">ORDEM DE SERVIÇO DE CAMPO</div>
        <table style="margin-bottom: 0;">
            <tr>
                <td style="width: 15%;">
                    <span class="label">OS Nº:</span>
                    <span class="value" style="font-size: 11px;">{{ $ordem->{'Numero ordem'} }}</span>
                </td>
                <td style="width: 60%;">
                    <span class="label">CÓD. CHECKLIST (SERVIÇO)</span>
                    <span class="value">Check {{ $ordem->Tipo ?? 'P/NR' }} -
                        {{ $ordem->{'Formulario servico'} ?? '---' }}</span>
                </td>
                <td rowspan="2" style="width: 25%;" class="header-logo-box">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" style="max-width: 60px; max-height: 35px;">
                    @endif
                    <div style="font-size: 5px; font-weight: bold; margin-top: 1px;">TECNOAR <br> COMPRESSORES</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">DATA/HORA ENVIO</span>
                    <span class="value">{{ $dataConclusao }}</span>
                    <span style="float: right; font-size: 7px; font-weight: bold; color: #555;">STATUS:
                        {{ strtoupper($ordem->Situacao ?? 'CONCLUÍDA') }}</span>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Página <span class="pagenum"></span>
    </footer>

    <main>
        {{-- SEÇÃO 1: Identificação do Ativo e Serviço --}}
        <div class="no-break">
            <div class="section-header">Identificação do Ativo e Serviço</div>
            <table class="compact-table">
                <tr>
                    <td style="width: 25%;"><span class="label">ATIVO</span><span
                            class="value">{{ $ordem->Equipto ?? '---' }}</span></td>
                    <td style="width: 25%;"><span class="label">SÉRIE</span><span
                            class="value">{{ $ordem->{'No série'} ?? '---' }}</span></td>
                    <td style="width: 25%;"><span class="label">MODELO</span><span
                            class="value">{{ $ordem->{'Modelo tabela'} ?? '---' }}</span></td>
                    <td style="width: 25%;"><span class="label">MARCA</span><span
                            class="value">{{ $ordem->Marca ?? '---' }}</span></td>
                </tr>
                <tr>
                    <td style="width: 35%;" colspan="1"><span class="label">Técnicos</span><span
                            class="value">{{ $ordem->Tecnico }}</span></td>
                    <td style="width: 50%;" colspan="2"><span class="label">DESCRIÇÃO DA ATIVIDADE</span><span
                            class="value">{{ $ordem->{'Descrição problema'} ?? 'MANUTENÇÃO' }}</span></td>
                    <td style="width: 15%;"><span class="label">TIPO</span><span
                            class="value">{{ $ordem->Tipo ?? 'S/R' }}</span></td>
                </tr>
            </table>
        </div>

        {{-- SEÇÃO 2: Cliente e Localidade --}}
        <div class="no-break">
            <div class="section-header">Cliente e Localidade</div>
            <table class="compact-table">
                <tr>
                    <td style="width: 50%;"><span class="label">Razão Social / CNPJ</span><span
                            class="value">{{ $ordem->Cliente }} / {{ $cliente->Cnpj ?? '---' }}</span></td>
                    <td style="width: 50%;"><span class="label">NOME / CÓDIGO DA LOCALIDADE</span><span
                            class="value">{{ $ordem->Cliente }} / {{ $ordem->{'Codigo cliente'} }}</span></td>
                </tr>
                <tr>
                    <td style="width: 40%;"><span class="label">Contato / Email</span><span
                            class="value">{{ $ordem->Contato ?? '---' }} | {{ $cliente->Email ?? '---' }}</span></td>
                    <td style="width: 60%;"><span class="label">ENDEREÇO</span><span
                            class="value">{{ $cliente->Endereco }}, {{ $cliente->Numero }} -
                            {{ $cliente->Cidade }}/{{ $cliente->Uf }}</span></td>
                </tr>
            </table>
        </div>

        <div class="no-break">
            <table style="border: none; margin-top: 2px;">
                <tr>
                    <td style="border: none; width: 63%; vertical-align: top; padding: 0 2px 0 0;">
                        <div class="section-header" style="margin-top: 0;">Eventos em Campo</div>
                        <table class="compact-table">
                            <tr style="background:#f2f2f2;">
                                <th style="width:25%">HORA</th>
                                <th style="width:45%">EVENTO</th>
                                <th style="width:30%">TIPO</th>
                            </tr>
                            @php $eventosSlice = array_slice($eventos, 0, 10); @endphp
                            @forelse($eventosSlice as $evento)
                                <tr>
                                    <td>{{ $evento['hora'] }}</td>
                                    <td>{{ $evento['evento'] }}</td>
                                    <td>{{ $evento['tipo'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">Sem registros</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                    <td style="border: none; width: 37%; vertical-align: top; padding: 0 0 0 2px;">
                        <div class="section-header" style="margin-top: 0;">Resumo de Horas</div>
                        <table class="compact-table">
                            <tr>
                                <td><span class="label">TRABALHO</span><span
                                        class="value">{{ $totais['trabalho'] }}</span></td>
                            </tr>
                            <tr>
                                <td><span class="label">DESLOCAMENTO</span><span
                                        class="value">{{ $totais['deslocamento'] }}</span></td>
                            </tr>
                            <tr>
                                <td style="background: #f9f9f9; border: 1px solid #333;"><span class="label">TOTAL
                                        GERAL HH</span><span class="value"
                                        style="font-size: 10px;">{{ $totais['hh'] }}</span></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        @php
            $serviceEntries = collect($servicesData ?? [])->filter(function($s) {
                return floatval($s['qtd'] ?? 0) > 0;
            })->all();
            $serviceTotals = array_merge(
                ['total_servicos' => 0, 'total_iss' => 0],
                is_array($servicesTotals ?? null) ? $servicesTotals : [],
            );
        @endphp
        @if (count($serviceEntries) > 0)
            <div class="no-break" style="margin-top: 4px;">
                <div class="section-header">Resumo de Custos</div>
                <table class="compact-table">
                    <tr style="background:#f2f2f2;">
                        <th style="width: 50%; text-align:left;">Serviço</th>
                        <th style="width: 15%; text-align:center;">Qtde</th>
                        <th style="width: 17%; text-align:right;">Valor Unit.</th>
                        <th style="width: 18%; text-align:right;">Total</th>
                    </tr>
                    @foreach ($serviceEntries as $service)
                        @php
                            $unitValue =
                                $service['usarValorInformado'] ?? false
                                    ? $service['valorInf'] ?? 0
                                    : ($service['valorUnit'] ?? 0 ?:
                                    $service['valorInf'] ?? 0);
                        @endphp
                        <tr>
                            <td style="word-break: break-word;">{{ $service['descricao'] }}</td>
                            <td style="text-align:center;">{{ number_format($service['qtd'] ?? 0, 2, ',', '.') }}</td>
                            <td style="text-align:right;">R$ {{ number_format($unitValue, 2, ',', '.') }}</td>
                            <td style="text-align:right;">R$
                                {{ number_format($service['valorTotal'] ?? 0, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:bold; background:#f9f9f9;">Total de
                            Serviços</td>
                        <td style="text-align:right; font-weight:bold; background:#f9f9f9;">R$
                            {{ number_format($serviceTotals['total_servicos'] ?? 0, 2, ',', '.') }}</td>
                    </tr>
                    @if (!empty($serviceTotals['total_iss']) && $serviceTotals['total_iss'] > 0)
                        <tr>
                            <td colspan="3" style="text-align:right; font-size:7.5px;">ISS aproximado</td>
                            <td style="text-align:right; font-size:7.5px;">R$
                                {{ number_format($serviceTotals['total_iss'], 2, ',', '.') }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        @endif

        @php
            $usedPecas = isset($ordem->pecas) ? $ordem->pecas->filter(function($p) { return floatval($p->{'Qtde utilizada'}) > 0; }) : collect([]);
            $hasUsedPecas = $usedPecas->count() > 0 && request()->query('com_pecas') !== '0';
        @endphp

        @foreach ($checklistData as $sectionTitle => $section)
            @php
                $items = $section['items'] ?? [];
                
                // FILTER answered items
                $answeredItems = [];
                foreach ($items as $it) {
                    $val = trim((string)($it['value'] ?? ''));
                    $hasValue = $val !== '' && $val !== '---';
                    $hasPhotos = !empty($it['photos']);
                    $hasObs = !empty($it['observation']) && trim((string)($it['observation'] ?? '')) !== '';
                    
                    if ($hasValue || $hasPhotos || $hasObs) {
                        $answeredItems[] = $it;
                    }
                }
                $items = $answeredItems;

                $sectionId = (int) ($section['id'] ?? 0);
                $sectionPhotosByItem = [];
                foreach ($items as $it) {
                    if (!empty($it['photos'])) {
                        // Exclude photos from dedicated "photos" type items to avoid duplication as they are shown inline
                        if (($it['input_type'] ?? '') !== 'photos') {
                            $title = trim($it['title'] ?? 'Fotos');
                            if (!isset($sectionPhotosByItem[$title])) {
                                $sectionPhotosByItem[$title] = [];
                            }
                            foreach ($it['photos'] as $ph) {
                                $sectionPhotosByItem[$title][] = $ph;
                            }
                        }
                    }
                }
                $isHorimetroSection = str_contains(strtoupper($sectionTitle), 'HORÍMETRO');
                $isAceitacaoSection = str_contains(strtoupper($sectionTitle), 'ACEITAÇÃO');
                $showSection = !empty($items) || ($sectionId === 3 && $hasUsedPecas);
            @endphp

            @if (
                $showSection &&
                    !in_array($sectionTitle, ['09. RELATÓRIO TÉCNICO GERAL', '10. FOTOS ADICIONAIS']))
                <div class="no-break">
                    <div class="section-header" style="background-color: #d9d9d9; margin-bottom: 0;">
                        {{ $sectionTitle }}
                    </div>

                    @if ($isHorimetroSection)
                        <table class="checklist-grid">
                            <tr>
                                <td style="width: 50%;">
                                    @foreach (array_slice($items, 0, 3) as $hItem)
                                        <div class="item-wrapper">
                                            <div class="item-full-text item-main-text">
                                                @if (!empty($hItem['code']))
                                                    <span style="color:#777;">{{ $hItem['code'] }}</span>
                                                @endif
                                                {{ $hItem['title'] }}
                                                @if (!empty($hItem['value']) && $hItem['value'] !== '---')
                                                    <span class="item-inline-response">: {{ $hItem['value'] }}</span>
                                                @endif
                                            </div>
                                            @if (isset($hItem['observation']) && trim($hItem['observation']) !== '')
                                                <div class="item-obs" style="margin-bottom: 2px;">Obs:
                                                    {{ $hItem['observation'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td style="width: 50%;">
                                    @foreach (array_slice($items, 3, 2) as $hItem)
                                        <div class="item-wrapper">
                                            <div class="item-full-text item-main-text">
                                                @if (!empty($hItem['code']))
                                                    <span style="color:#777;">{{ $hItem['code'] }}</span>
                                                @endif
                                                {{ $hItem['title'] }}
                                                @if (!empty($hItem['value']) && $hItem['value'] !== '---')
                                                    <span class="item-inline-response">: {{ $hItem['value'] }}</span>
                                                @endif
                                            </div>
                                            @if (isset($hItem['observation']) && trim($hItem['observation']) !== '')
                                                <div class="item-obs" style="margin-bottom: 2px;">Obs:
                                                    {{ $hItem['observation'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        </table>
                    @elseif($isAceitacaoSection && count($items) >= 3)
                        <table class="checklist-grid">
                            @php
                                // Tenta organizar em 2 colunas: Nome/Assinatura e Documento/Assinatura Técnica
                                // Se faltar um item (como o Documento), garante que as assinaturas ainda apareçam
                                $orderedItems = [
                                    $items[0] ?? null,
                                    $items[2] ?? ($items[1] ?? null), // Tenta pegar Assinatura Cliente se Documento faltar
                                    $items[1] ?? null,
                                    $items[3] ?? null,
                                ];

                                // Se só houver 3 itens e o segundo for o documento (7.2), reorganiza melhor
                                if (count($items) === 3 && !isset($items[3])) {
                                    $orderedItems = [$items[0], $items[1], $items[2], null];
                                }
                            @endphp
                            @foreach (array_chunk($orderedItems, 2) as $chunk)
                                <tr>
                                    @foreach ($chunk as $item)
                                        @if (!$item)
                                            <td></td>
                                            @continue
                                        @endif
                                        @php
                                            $isSignature =
                                                (($item['input_type'] ?? null) === 'signature' ||
                                                    str_starts_with($item['value'] ?? '', 'data:image')) &&
                                                !empty($item['value']) &&
                                                ($item['value'] ?? '') !== '---';
                                            $itemClass =
                                                $item['is_field'] ?? false ? 'item-field-text' : 'item-main-text';
                                        @endphp
                                        <td>
                                            <div class="item-wrapper">
                                                @if ($isSignature)
                                                    <div class="item-row" style="min-height: 50px;">
                                                        <div class="item-title {{ $itemClass }}">
                                                            @if (!empty($item['code']))
                                                                <span style="color:#777;">{{ $item['code'] }}</span>
                                                            @endif
                                                            {{ $item['title'] }}
                                                        </div>
                                                        <div class="item-value">
                                                            <img src="{{ $item['value'] }}"
                                                                class="signature-in-grid">
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="item-full-text {{ $itemClass }}">
                                                        @if (!empty($item['code']))
                                                            <span style="color:#777;">{{ $item['code'] }}</span>
                                                        @endif
                                                        {{ $item['title'] }}
                                                        @if (!empty($item['value']) && $item['value'] !== '---')
                                                            <span class="item-inline-response">:
                                                                {{ $item['value'] }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if (isset($item['observation']) && trim($item['observation']) !== '')
                                                    <div class="item-obs" style="margin-bottom: 2px;">Obs:
                                                        {{ $item['observation'] }}</div>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <table class="checklist-grid">
                            @php
                                $items = array_values($items); // Reset keys
                                $i = 0;
                                $count = count($items);
                            @endphp
                            @while ($i < $count)
                                @php
                                    $item = $items[$i];
                                    $isPhotoType = ($item['input_type'] ?? null) === 'photos';
                                @endphp

                                @if ($isPhotoType)
                                    @php
                                        $isSignature = false;
                                        $itemClass = $item['is_field'] ?? false ? 'item-field-text' : 'item-main-text';
                                    @endphp
                                    <tr>
                                        <td colspan="2" style="width: 100%; border: none;">
                                            <div class="item-wrapper">
                                                <div class="item-full-text {{ $itemClass }}">
                                                    @if (!empty($item['code']))
                                                        <span style="color:#777;">{{ $item['code'] }}</span>
                                                    @endif
                                                    <strong>{{ $item['title'] }}</strong>
                                                    @if (!empty($item['value']) && $item['value'] !== '---')
                                                        <span class="item-inline-response">:
                                                            {{ $item['value'] }}</span>
                                                    @endif
                                                </div>
                                                @if (!empty($item['photos']))
                                                    <div class="item-gallery" style="margin-top: 2px;">
                                                        <table class="gallery-table">
                                                            @foreach (array_chunk($item['photos'], 4) as $photoRow)
                                                                <tr>
                                                                    @foreach ($photoRow as $photo)
                                                                        <td>
                                                                            <div class="gallery-image-box">
                                                                                <img src="{{ $photo }}" class="gallery-image">
                                                                            </div>
                                                                        </td>
                                                                    @endforeach
                                                                    @for ($k = count($photoRow); $k < 4; $k++)
                                                                        <td></td>
                                                                    @endfor
                                                                </tr>
                                                            @endforeach
                                                        </table>
                                                    </div>
                                                @endif
                                                @if (isset($item['observation']) && trim($item['observation']) !== '')
                                                    <div class="item-obs" style="margin-bottom: 2px;">Obs:
                                                        {{ $item['observation'] }}</div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @php $i++; @endphp
                                @else
                                    @php
                                        // Find next non-photo item for side-by-side
                                        $nextIdx = $i + 1;
                                        $nextItem = null;
                                        if (
                                            $nextIdx < $count &&
                                            ($items[$nextIdx]['input_type'] ?? null) !== 'photos'
                                        ) {
                                            $nextItem = $items[$nextIdx];
                                        }

                                        $rowItems = [$item];
                                        if ($nextItem) {
                                            $rowItems[] = $nextItem;
                                        }
                                    @endphp
                                    <tr>
                                        @foreach ($rowItems as $indexInRow => $it)
                                            @php
                                                $isSignature =
                                                    ($it['input_type'] ?? null) === 'signature' &&
                                                    !empty($it['value']) &&
                                                    ($it['value'] ?? '') !== '---';
                                                $itemClass =
                                                    $it['is_field'] ?? false ? 'item-field-text' : 'item-main-text';
                                            @endphp
                                            <td style="width: 50%;">
                                                <div class="item-wrapper">
                                                    @if ($isSignature)
                                                        <div class="item-row">
                                                            <div class="item-title {{ $itemClass }}">
                                                                @if (!empty($it['code']))
                                                                    <span
                                                                        style="color:#777;">{{ $it['code'] }}</span>
                                                                @endif
                                                                {{ $it['title'] }}
                                                            </div>
                                                            <div class="item-value">
                                                                <img src="{{ $it['value'] }}"
                                                                    class="signature-in-grid">
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="item-full-text {{ $itemClass }}">
                                                            @if (!empty($it['code']))
                                                                <span style="color:#777;">{{ $it['code'] }}</span>
                                                            @endif
                                                            {{ $it['title'] }}
                                                            @if (!empty($it['value']) && $it['value'] !== '---')
                                                                <span class="item-inline-response">:
                                                                    {{ $it['value'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if (isset($it['observation']) && trim($it['observation']) !== '')
                                                        <div class="item-obs" style="margin-bottom: 2px;">Obs:
                                                            {{ $it['observation'] }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                        @if (count($rowItems) < 2)
                                            <td style="width: 50%;"></td>
                                        @endif
                                    </tr>
                                    @php $i += count($rowItems); @endphp
                                @endif
                            @endwhile
                        </table>
                    @endif

                    {{-- Peças Aplicadas --}}
                    @if ($sectionId == 3 && $hasUsedPecas)
                        <div
                            style="background-color: #f2f2f2; font-weight: bold; padding: 1px 6px; border: 1px solid #7f7f7f; border-top: none; font-size: 7px;">
                            PEÇAS E COMPONENTES APLICADOS</div>
                        <table class="compact-table">
                            <tr style="background-color: #eee;">
                                <th style="width: 12%;">CÓDIGO</th>
                                <th style="width: 60%;">DESCRIÇÃO</th>
                                <th style="width: 13%; text-align: center;">QTD UTIL.</th>
                                <th style="width: 15%; text-align: center;">VALOR UNIT.</th>
                            </tr>
                            @foreach ($usedPecas as $peca)
                                @php
                                    $unitPrice = (!empty($peca->{'Valor informado'}) && $peca->{'Valor informado'} != 0) 
                                        ? $peca->{'Valor informado'} 
                                        : ($peca->{'Valor tabela'} ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $peca->pecaByCodigo->{'Codigo peca'} ?? $peca->{'Código fabricante'} }}</td>
                                    <td>{{ $peca->pecaByCodigo->{'Descricao peca'} ?? $peca->{'Peça'} }}</td>
                                    <td style="text-align: center;">
                                        {{ number_format($peca->{'Qtde utilizada'}, 1, ',', '.') }}
                                    </td>
                                    <td style="text-align: center;">R$
                                        {{ number_format($unitPrice, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @if (!empty($sectionPhotosByItem))
                        <div class="section-gallery" style="border: none; padding: 0;">
                            @foreach ($sectionPhotosByItem as $itemTitle => $photos)
                                <div
                                    style="background-color: #f9f9f9; padding: 2px 6px; border: 1px solid #7f7f7f; border-bottom: none; font-size: 7px; font-weight: bold; margin-top: 2px;">
                                    FOTOS: {{ strtoupper($itemTitle) }}
                                </div>
                                <div
                                    style="border: 1px solid #7f7f7f; padding: 3px; background: #fff; margin-bottom: 4px;">
                                    <table class="gallery-table">
                                        @foreach (array_chunk($photos, 4) as $photoRow)
                                            <tr>
                                                @foreach ($photoRow as $photo)
                                                    <td>
                                                        <div class="gallery-image-box">
                                                            <img src="{{ $photo }}" class="gallery-image">
                                                        </div>
                                                    </td>
                                                @endforeach
                                                @for ($i = count($photoRow); $i < 4; $i++)
                                                    <td></td>
                                                @endfor
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endforeach

        <div class="section-header">RELATÓRIO TÉCNICO GERAL</div>
        @php $reportSec = $checklistData['09. RELATÓRIO TÉCNICO GERAL'] ?? null; @endphp
        <div style="padding: 4px; border: 1px solid #7f7f7f; min-height: 20px; background: #fff; font-size: 7.5px;">
            <strong>Comentários:</strong>
            {{ !empty($reportSec['global_report']) && trim($reportSec['global_report']) !== '' ? $reportSec['global_report'] : 'Nenhum comentário.' }}
        </div>
        </div>

        @php $photosSec = $checklistData['10. FOTOS ADICIONAIS'] ?? null; @endphp
        <div class="no-break">
            <div class="section-header" style="margin-top: 2px;">FOTOS ADICIONAIS / GERAIS</div>
            <div class="section-gallery" style="background: #fff; min-height: 40px;">
                @if ($photosSec && !empty($photosSec['global_photos']))
                    <table class="gallery-table">
                        @foreach (array_chunk($photosSec['global_photos'], 4) as $photoRow)
                            <tr>
                                @foreach ($photoRow as $photo)
                                    <td>
                                        <div class="gallery-image-box">
                                            <img src="{{ $photo }}" class="gallery-image">
                                        </div>
                                    </td>
                                @endforeach
                                @for ($i = count($photoRow); $i < 4; $i++)
                                    <td></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @else
                    <div style="font-size: 7px; color: #999; padding: 5px;">Nenhuma foto adicional anexada.</div>
                @endif
            </div>
        </div>
    </main>
</body>

</html>

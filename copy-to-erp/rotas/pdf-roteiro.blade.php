<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Roteiro Técnico #{{ $roteiro->{'Numero roteiro'} ?? '---' }}</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
            line-height: 1.4;
        }

        @page {
            margin: 15px 20px;
        }

        /* HEADER */
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #454545;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0 0 5px 0;
            font-size: 20px;
            color: #454545;
            font-weight: bold;
        }

        .header p {
            margin: 2px 0;
            font-size: 11px;
            color: #666;
        }

        /* GENERAL INFO - HORIZONTAL LAYOUT */
        .info-box {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info-box h2 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #454545;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .info-horizontal {
            display: table;
            width: 100%;
            text-align: center;
        }

        .info-item {
            display: table-cell;
            width: 33.33%;
            padding: 5px 10px;
            vertical-align: middle;
        }

        .info-item-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .info-item-value {
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }

        /* SERVICES TABLE */
        .services-title {
            font-size: 16px;
            font-weight: bold;
            color: #454545;
            margin-bottom: 10px;
            border-bottom: 2px solid #454545;
            padding-bottom: 5px;
        }

        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .services-table thead {
            background-color: #454545;
            color: white;
        }

        .services-table th {
            padding: 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid #333;
        }

        .services-table td {
            padding: 10px 8px;
            font-size: 11px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .services-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .services-table tbody tr:hover {
            background-color: #f0f0f0;
        }

        /* COLUMN STYLES */
        .seq-col {
            width: 80px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }

        .cliente-col {
            width: 20%;
        }

        .servico-col {
            width: 55%;
        }

        /* CLIENT INFO STYLING */
        .cliente-nome {
            font-weight: bold;
            font-size: 12px;
            color: #000;
            margin-bottom: 4px;
        }

        .cliente-detalhe {
            font-size: 10px;
            color: #555;
            margin: 2px 0;
        }

        /* SERVICE HIGHLIGHT */
        .servico-destaque {
            font-weight: bold;
            color: #000;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <div class="header">
        <h1>ROTEIRO TÉCNICO #{{ $roteiro->{'Numero roteiro'} ?? '---' }}</h1>
        <p>Emitido em:
            {{ $roteiro->{'Data emissao'} ? \Carbon\Carbon::parse($roteiro->{'Data emissao'})->format('d/m/Y') : '---' }}
        </p>
    </div>

    <!-- GENERAL INFORMATION - HORIZONTAL -->
    <div class="info-box">
        <h2>Informações Gerais</h2>
        <div class="info-horizontal">
            <div class="info-item">
                <div class="info-item-label">Data da Visita</div>
                <div class="info-item-value">{{ $dataVisita ?? 'N/A' }}</div>
            </div>
            <div class="info-item">
                @php
                    // Logic to get helpers from the first item
                    $firstItem = $itens->first();
                    $helpers = [];
                    if ($firstItem && $firstItem->ordem && $firstItem->ordem->ordem_api) {
                        $api = $firstItem->ordem->ordem_api;
                        if (!empty($api->ajudante1)) {
                            $helpers[] = $api->ajudante1;
                        }
                        if (!empty($api->ajudante2)) {
                            $helpers[] = $api->ajudante2;
                        }
                        if (!empty($api->ajudante3)) {
                            $helpers[] = $api->ajudante3;
                        }
                        if (!empty($api->ajudante4)) {
                            $helpers[] = $api->ajudante4;
                        }
                    }

                    $tecnicoLabel = !empty($helpers) ? 'Técnicos' : 'Técnico';
                    $tecnicoValue = $roteiro->Tecnico ?? 'N/A';

                    if (!empty($helpers)) {
                        $tecnicoValue .= ' / ' . implode(' / ', $helpers);
                    }
                @endphp
                <div class="info-item-label">{{ $tecnicoLabel }}</div>
                <div class="info-item-value">{{ $tecnicoValue }}</div>
            </div>
            <div class="info-item">
                <div class="info-item-label">Carro</div>
                <div class="info-item-value">{{ $carro ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    <!-- SERVICES LIST -->
    <div class="services-title">Pontos de Atendimento</div>

    @if ($itens->isNotEmpty())
        <table class="services-table">
            <thead>
                <tr>
                    <th class="seq-col">Sequência</th>
                    <th class="cliente-col">Cliente / Contato / Equipamento</th>
                    <th class="servico-col">Serviço a Executar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($itens as $item)
                    @php
                        // Clean client name - remove suffixes
                        $clienteNome = $item->ordem->Cliente ?? 'Cliente não informado';
                        $clienteNome = preg_replace('/\s*-\s*(CONSUMIDOR|REVENDA)\s*$/i', '', $clienteNome);
                        $clienteNome = trim($clienteNome);

                        // Get sequence ordinal
                        $sequencia = $item->{'Seq tecnico'} ?? 0;
                        $sequenciaMap = [
                            1 => '1º',
                            2 => '2º',
                            3 => '3º',
                            4 => '4º',
                            5 => '5º',
                            6 => '6º',
                            7 => '7º',
                        ];
                        $sequenciaOrdinal = $sequenciaMap[$sequencia] ?? $sequencia . 'º';

                        // Get OS number
                        $osNumber = $item->{'No os'} ?? '-';
                    @endphp
                    <tr>
                        <td class="seq-col">{{ $sequenciaOrdinal }} - {{ $osNumber }}</td>
                        <td class="cliente-col">
                            <div class="cliente-nome">{{ $clienteNome }}</div>
                            <div class="cliente-detalhe">{{ $item->ordem->clienteByCodigo->Endereco ?? '-' }},
                                {{ $item->ordem->clienteByCodigo->Bairro ?? '-' }} -
                                {{ $item->ordem->clienteByCodigo->Numero ?? '-' }}, CEP
                                {{ $item->ordem->clienteByCodigo->Cep ?? '-' }}</div>
                            <div class="cliente-detalhe">Contato: {{ $item->ordem->Contato ?? '-' }}</div>
                            <div class="cliente-detalhe">Equipto: {{ $item->ordem->Equipto ?? '-' }}</div>
                        </td>
                        <td class="servico-col servico-destaque">
                            {{ $item->ordem->{'Descrição problema'} ?? 'Serviço não especificado' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">
            Nenhum ponto de atendimento cadastrado neste roteiro.
        </div>
    @endif
</body>

</html>

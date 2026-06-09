<?php

namespace App\Services;

use App\Models\XmlImport;
use App\Models\XmlImportItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductSupplierCode;
use App\Models\User;
use App\Enums\StockMovementType;
use App\Enums\StockMovementSource;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use DOMDocument;
use DOMXPath;

class XmlImportService
{
    /**
     * Faz o parse do conteúdo do XML da NF-e.
     */
    public function parseXmlContent(string $xmlContent): array
    {
        $dom = new DOMDocument();
        // Evita warnings de XML malformado
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xmlContent)) {
            libxml_clear_errors();
            throw ValidationException::withMessages([
                'xml' => 'Arquivo XML inválido ou malformado.',
            ]);
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        // Registra o namespace da NF-e para busca direta
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        // Chave de acesso da nota
        // Geralmente está no atributo Id da tag infNFe: 'NFe35190600000000000000550010000000011000000001'
        $infNFeNode = $xpath->query('//nfe:infNFe')->item(0);
        if (!$infNFeNode) {
            // Tentativa sem namespace
            $infNFeNode = $xpath->query('//infNFe')->item(0);
        }

        $accessKey = '';
        if ($infNFeNode) {
            $idAttr = $infNFeNode->getAttribute('Id');
            $accessKey = preg_replace('/[^0-9]/', '', $idAttr);
        }

        if (empty($accessKey)) {
            // Busca na tag chNFe caso exista
            $chNFeNode = $xpath->query('//nfe:chNFe')->item(0) ?: $xpath->query('//chNFe')->item(0);
            if ($chNFeNode) {
                $accessKey = preg_replace('/[^0-9]/', '', $chNFeNode->nodeValue);
            }
        }

        if (empty($accessKey)) {
            throw ValidationException::withMessages([
                'xml' => 'Chave de acesso da NF-e não localizada no arquivo XML.',
            ]);
        }

        // Dados do emitente (Fornecedor)
        $emitNode = $xpath->query('//nfe:emit')->item(0) ?: $xpath->query('//emit')->item(0);
        if (!$emitNode) {
            throw ValidationException::withMessages([
                'xml' => 'Dados do emitente (fornecedor) não localizados no XML.',
            ]);
        }

        $cnpjNode = $xpath->query('nfe:CNPJ', $emitNode)->item(0) ?: $xpath->query('CNPJ', $emitNode)->item(0);
        $cnpj = $cnpjNode ? preg_replace('/[^0-9]/', '', $cnpjNode->nodeValue) : '';
        $xNomeNode = $xpath->query('nfe:xNome', $emitNode)->item(0) ?: $xpath->query('xNome', $emitNode)->item(0);
        $companyName = $xNomeNode ? trim($xNomeNode->nodeValue) : '';
        $ieNode = $xpath->query('nfe:IE', $emitNode)->item(0) ?: $xpath->query('IE', $emitNode)->item(0);
        $stateRegistration = $ieNode ? trim($ieNode->nodeValue) : '';

        // Endereço do emitente
        $enderEmitNode = $xpath->query('nfe:enderEmit', $emitNode)->item(0) ?: $xpath->query('enderEmit', $emitNode)->item(0);
        $street = '';
        $number = '';
        $neighborhood = '';
        $city = '';
        $state = '';
        $cep = '';
        $phone = '';

        if ($enderEmitNode) {
            $streetNode = $xpath->query('nfe:xLgr', $enderEmitNode)->item(0) ?: $xpath->query('xLgr', $enderEmitNode)->item(0);
            $street = $streetNode ? trim($streetNode->nodeValue) : '';
            $nroNode = $xpath->query('nfe:nro', $enderEmitNode)->item(0) ?: $xpath->query('nro', $enderEmitNode)->item(0);
            $number = $nroNode ? trim($nroNode->nodeValue) : '';
            $bairroNode = $xpath->query('nfe:xBairro', $enderEmitNode)->item(0) ?: $xpath->query('xBairro', $enderEmitNode)->item(0);
            $neighborhood = $bairroNode ? trim($bairroNode->nodeValue) : '';
            $munNode = $xpath->query('nfe:xMun', $enderEmitNode)->item(0) ?: $xpath->query('xMun', $enderEmitNode)->item(0);
            $city = $munNode ? trim($munNode->nodeValue) : '';
            $ufNode = $xpath->query('nfe:UF', $enderEmitNode)->item(0) ?: $xpath->query('UF', $enderEmitNode)->item(0);
            $state = $ufNode ? trim($ufNode->nodeValue) : '';
            $cepNode = $xpath->query('nfe:CEP', $enderEmitNode)->item(0) ?: $xpath->query('CEP', $enderEmitNode)->item(0);
            $cep = $cepNode ? preg_replace('/[^0-9]/', '', $cepNode->nodeValue) : '';
            $foneNode = $xpath->query('nfe:fone', $enderEmitNode)->item(0) ?: $xpath->query('fone', $enderEmitNode)->item(0);
            $phone = $foneNode ? preg_replace('/[^0-9]/', '', $foneNode->nodeValue) : '';
        }

        // Dados da Nota Fiscal (Ide)
        $ideNode = $xpath->query('//nfe:ide')->item(0) ?: $xpath->query('//ide')->item(0);
        $nfNumber = '';
        $nfSerie = '';
        $issueDate = null;
        if ($ideNode) {
            $nNFNode = $xpath->query('nfe:nNF', $ideNode)->item(0) ?: $xpath->query('nNF', $ideNode)->item(0);
            $nfNumber = $nNFNode ? trim($nNFNode->nodeValue) : '';
            $serieNode = $xpath->query('nfe:serie', $ideNode)->item(0) ?: $xpath->query('serie', $ideNode)->item(0);
            $nfSerie = $serieNode ? trim($serieNode->nodeValue) : '';
            $dhEmiNode = $xpath->query('nfe:dhEmi', $ideNode)->item(0) ?: $xpath->query('nfe:dEmi', $ideNode)->item(0) ?: $xpath->query('dhEmi', $ideNode)->item(0) ?: $xpath->query('dEmi', $ideNode)->item(0);
            $issueDate = $dhEmiNode ? substr(trim($dhEmiNode->nodeValue), 0, 10) : date('Y-m-d');
        }

        // Valor total da nota
        $totalNode = $xpath->query('//nfe:total/nfe:ICMSTot')->item(0) ?: $xpath->query('//total/ICMSTot')->item(0);
        $totalAmount = 0.00;
        if ($totalNode) {
            $vNFNode = $xpath->query('nfe:vNF', $totalNode)->item(0) ?: $xpath->query('vNF', $totalNode)->item(0);
            $totalAmount = $vNFNode ? (float) $vNFNode->nodeValue : 0.00;
        }

        // Itens da Nota
        $detNodes = $xpath->query('//nfe:det') ?: $xpath->query('//det');
        $items = [];
        if ($detNodes) {
            foreach ($detNodes as $det) {
                $prodNode = $xpath->query('nfe:prod', $det)->item(0) ?: $xpath->query('prod', $det)->item(0);
                if ($prodNode) {
                    $cProdNode = $xpath->query('nfe:cProd', $prodNode)->item(0) ?: $xpath->query('cProd', $prodNode)->item(0);
                    $xProdNode = $xpath->query('nfe:xProd', $prodNode)->item(0) ?: $xpath->query('xProd', $prodNode)->item(0);
                    $qComNode = $xpath->query('nfe:qCom', $prodNode)->item(0) ?: $xpath->query('qCom', $prodNode)->item(0);
                    $vUnComNode = $xpath->query('nfe:vUnCom', $prodNode)->item(0) ?: $xpath->query('vUnCom', $prodNode)->item(0);
                    $vProdNode = $xpath->query('nfe:vProd', $prodNode)->item(0) ?: $xpath->query('vProd', $prodNode)->item(0);
                    $cfopNode = $xpath->query('nfe:CFOP', $prodNode)->item(0) ?: $xpath->query('CFOP', $prodNode)->item(0);
                    $ncmNode = $xpath->query('nfe:NCM', $prodNode)->item(0) ?: $xpath->query('NCM', $prodNode)->item(0);

                    $items[] = [
                        'supplier_product_code' => $cProdNode ? trim($cProdNode->nodeValue) : '',
                        'supplier_product_name' => $xProdNode ? trim($xProdNode->nodeValue) : '',
                        'quantity' => $qComNode ? (float) $qComNode->nodeValue : 0.0000,
                        'unit_price' => $vUnComNode ? (float) $vUnComNode->nodeValue : 0.0000,
                        'total_price' => $vProdNode ? (float) $vProdNode->nodeValue : 0.00,
                        'cfop' => $cfopNode ? trim($cfopNode->nodeValue) : '',
                        'ncm' => $ncmNode ? trim($ncmNode->nodeValue) : '',
                    ];
                }
            }
        }

        // Duplicatas/Parcelas
        $dupNodes = $xpath->query('//nfe:cobr/nfe:dup') ?: $xpath->query('//cobr/dup');
        $duplicatas = [];
        if ($dupNodes && $dupNodes->length > 0) {
            foreach ($dupNodes as $dup) {
                $nDupNode = $xpath->query('nfe:nDup', $dup)->item(0) ?: $xpath->query('nDup', $dup)->item(0);
                $dVencNode = $xpath->query('nfe:dVenc', $dup)->item(0) ?: $xpath->query('dVenc', $dup)->item(0);
                $vDupNode = $xpath->query('nfe:vDup', $dup)->item(0) ?: $xpath->query('vDup', $dup)->item(0);

                $duplicatas[] = [
                    'number' => $nDupNode ? trim($nDupNode->nodeValue) : '',
                    'due_date' => $dVencNode ? trim($dVencNode->nodeValue) : '',
                    'amount' => $vDupNode ? (float) $vDupNode->nodeValue : 0.00,
                ];
            }
        }

        return [
            'access_key' => $accessKey,
            'nf_number' => $nfNumber,
            'nf_serie' => $nfSerie,
            'issue_date' => $issueDate,
            'total_amount' => $totalAmount,
            'supplier' => [
                'cnpj' => $cnpj,
                'name' => $companyName,
                'state_registration' => $stateRegistration,
                'address' => [
                    'street' => $street,
                    'number' => $number,
                    'neighborhood' => $neighborhood,
                    'city' => $city,
                    'state' => $state,
                    'cep' => $cep,
                    'phone' => $phone,
                ]
            ],
            'items' => $items,
            'duplicatas' => $duplicatas,
        ];
    }

    /**
     * Inicia a importação de um XML da NF-e a partir do seu conteúdo.
     */
    public function importXml(string $filename, string $xmlContent): XmlImport
    {
        $parsed = $this->parseXmlContent($xmlContent);

        // Verifica se a chave de acesso já foi importada
        $existing = XmlImport::where('access_key', $parsed['access_key'])->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'xml' => "Esta NF-e com chave {$parsed['access_key']} já foi importada sob o ID {$existing->id}.",
            ]);
        }

        return DB::transaction(function () use ($filename, $parsed) {
            // Tenta encontrar o fornecedor pelo CNPJ
            $supplier = Supplier::where('cnpj', $parsed['supplier']['cnpj'])->first();

            $xmlImport = XmlImport::create([
                'filename' => $filename,
                'access_key' => $parsed['access_key'],
                'supplier_id' => $supplier?->id ?? null,
                'total_amount' => $parsed['total_amount'],
                'status' => 'pending',
                'imported_at' => null,
            ]);

            foreach ($parsed['items'] as $item) {
                // Tenta mapear o produto pelo histórico de associação
                $productId = null;
                if ($supplier) {
                    $map = ProductSupplierCode::where('supplier_id', $supplier->id)
                        ->where('supplier_code', $item['supplier_product_code'])
                        ->first();
                    $productId = $map?->product_id;
                }

                // Se não mapeado, tenta pelo código de barras / SKU do produto interno direto (opcional)
                if (!$productId) {
                    $prod = Product::where('sku', $item['supplier_product_code'])->first();
                    $productId = $prod?->id;
                }

                XmlImportItem::create([
                    'xml_import_id' => $xmlImport->id,
                    'product_id' => $productId,
                    'supplier_product_code' => $item['supplier_product_code'],
                    'supplier_product_name' => $item['supplier_product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'cfop' => $item['cfop'],
                    'ncm' => $item['ncm'],
                    'resolved' => !is_null($productId),
                ]);
            }

            return $xmlImport;
        });
    }

    /**
     * Associa um item da importação XML a um produto interno do ERP.
     */
    public function resolveItem(int $xmlImportItemId, int $productId): void
    {
        $item = XmlImportItem::findOrFail($xmlImportItemId);
        $item->product_id = $productId;
        $item->resolved = true;
        $item->save();
    }

    /**
     * Efetiva e confirma a importação do XML dando entrada física e gerando contas a pagar.
     */
    public function confirmImport(int $xmlImportId, string $xmlContent, User $user): XmlImport
    {
        $xmlImport = XmlImport::findOrFail($xmlImportId);

        if ($xmlImport->status === 'confirmed') {
            throw ValidationException::withMessages([
                'status' => 'Esta importação já foi confirmada anteriormente.',
            ]);
        }

        // Verifica se todos os itens estão resolvidos/associados a produtos internos
        $unresolvedCount = $xmlImport->items()->where('resolved', false)->count();
        if ($unresolvedCount > 0) {
            throw ValidationException::withMessages([
                'items' => "Ainda restam {$unresolvedCount} itens sem produto interno correspondente associado.",
            ]);
        }

        $parsed = $this->parseXmlContent($xmlContent);

        return DB::transaction(function () use ($xmlImport, $parsed, $user) {
            // 1. Garante que o fornecedor existe no ERP
            $supplier = Supplier::where('cnpj', $parsed['supplier']['cnpj'])->first();
            if (!$supplier) {
                $supplier = Supplier::create([
                    'cnpj' => $parsed['supplier']['cnpj'],
                    'name' => $parsed['supplier']['name'],
                    'state_registration' => $parsed['supplier']['state_registration'],
                    'email' => 'financeiro@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $parsed['supplier']['name'])) . '.com.br',
                    'phone' => $parsed['supplier']['address']['phone'] ?: '11999999999',
                    'street' => $parsed['supplier']['address']['street'],
                    'number' => $parsed['supplier']['address']['number'],
                    'neighborhood' => $parsed['supplier']['address']['neighborhood'],
                    'city' => $parsed['supplier']['address']['city'],
                    'state' => $parsed['supplier']['address']['state'],
                    'zip_code' => $parsed['supplier']['address']['cep'],
                ]);
            }

            $xmlImport->supplier_id = $supplier->id;

            // 2. Processa movimentações de estoque e mapeamento
            $stockService = app(StockMovementService::class);
            foreach ($xmlImport->items as $item) {
                $product = Product::findOrFail($item->product_id);

                // Grava a relação do código do fornecedor para reusar futuramente
                ProductSupplierCode::updateOrCreate([
                    'supplier_id' => $supplier->id,
                    'supplier_code' => $item['supplier_product_code'],
                ], [
                    'product_id' => $product->id,
                ]);

                // Atualiza o custo do produto
                $product->cost_price = $item['unit_price'];
                $product->save();

                // Entrada de estoque
                $stockService->move(
                    $product,
                    (float) $item['quantity'],
                    StockMovementType::Input,
                    StockMovementSource::InventoryConference, // Mapeado como origem de movimentação de compra/entrada física
                    $xmlImport->id,
                    $user,
                    "Entrada automática via importação da NF-e {$parsed['nf_number']} Série {$parsed['nf_serie']}",
                    (float) $item['unit_price']
                );
            }

            // 3. Geração do Contas a Pagar
            $company = \App\Models\Company::first();
            if (!$company) {
                $company = \App\Models\Company::create([
                    'id' => 1,
                    'name' => 'Neksa ERP',
                ]);
            }

            $payableInstallments = [];
            if (!empty($parsed['duplicatas'])) {
                foreach ($parsed['duplicatas'] as $index => $dup) {
                    $payableInstallments[] = [
                        'installment_number' => $index + 1,
                        'due_date' => $dup['due_date'],
                        'amount' => $dup['amount'],
                    ];
                }
            } else {
                // Caso não tenha duplicatas listadas no XML, gera parcela única vencendo hoje
                $payableInstallments[] = [
                    'installment_number' => 1,
                    'due_date' => $parsed['issue_date'],
                    'amount' => (float) $xmlImport->total_amount,
                ];
            }

            $snapshot = [
                'document_number' => "NFE-{$parsed['nf_number']}-{$parsed['nf_serie']}",
                'supplier_name' => $supplier->name,
                'total_amount' => (float) $xmlImport->total_amount,
            ];

            app(FinancialService::class)->createPayable([
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'source_type' => get_class($xmlImport),
                'source_id' => $xmlImport->id,
                'source_snapshot' => $snapshot,
                'competence_date' => $parsed['issue_date'],
                'description' => "Contas a pagar gerado pela importação da NF-e {$parsed['nf_number']}",
                'total_amount' => (float) $xmlImport->total_amount,
            ], $payableInstallments, $user);

            // 4. Conclui o status do XML
            $xmlImport->status = 'confirmed';
            $xmlImport->imported_at = now();
            $xmlImport->save();

            return $xmlImport;
        });
    }
}

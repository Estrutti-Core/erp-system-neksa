<?php

namespace App\Http\Controllers;

use App\Models\XmlImport;
use App\Models\XmlImportItem;
use App\Models\Product;
use App\Services\XmlImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Exception;

class XmlImportController extends Controller
{
    public function __construct(
        private readonly XmlImportService $xmlImportService
    ) {}

    public function index(): View
    {
        $imports = XmlImport::with('supplier')
            ->latest()
            ->paginate(15);

        return view('xml_imports.index', compact('imports'));
    }

    public function create(): View
    {
        return view('xml_imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'xml_file' => 'required|file|mimes:xml',
        ], [
            'xml_file.required' => 'O arquivo XML é obrigatório.',
            'xml_file.mimes'    => 'O arquivo deve ser do tipo XML.',
        ]);

        try {
            $file = $request->file('xml_file');
            $xmlContent = file_get_contents($file->getRealPath());
            $filename = $file->getClientOriginalName();

            // Executa o parse e inicia a importação pendente
            $xmlImport = $this->xmlImportService->importXml($filename, $xmlContent);

            // Persiste o arquivo XML localmente para processamento posterior na confirmação
            $directory = storage_path('app/xml_imports');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory . '/' . $xmlImport->access_key . '.xml', $xmlContent);

            return redirect()->route('xml-imports.show', $xmlImport)
                ->with('success', 'XML importado com sucesso! Agora associe os itens aos produtos internos.');

        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Erro ao processar arquivo XML: ' . $e->getMessage());
        }
    }

    public function show(XmlImport $xmlImport): View
    {
        $xmlImport->load(['items.product', 'supplier']);
        $products = Product::active()->orderBy('name')->get();

        return view('xml_imports.show', compact('xmlImport', 'products'));
    }

    public function resolveItem(Request $request, XmlImportItem $xmlImportItem): RedirectResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $this->xmlImportService->resolveItem($xmlImportItem->id, $request->product_id);

        return redirect()->back()
            ->with('success', 'Produto associado com sucesso!');
    }

    public function confirm(XmlImport $xmlImport): RedirectResponse
    {
        try {
            $filePath = storage_path('app/xml_imports/' . $xmlImport->access_key . '.xml');
            if (!file_exists($filePath)) {
                throw new Exception('Arquivo XML original não localizado para efetivação.');
            }

            $xmlContent = file_get_contents($filePath);

            $this->xmlImportService->confirmImport($xmlImport->id, $xmlContent, auth()->user());

            return redirect()->route('xml-imports.index')
                ->with('success', 'Importação XML confirmada! Estoque atualizado e Contas a Pagar gerado com sucesso.');

        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao confirmar importação: ' . $e->getMessage());
        }
    }
}

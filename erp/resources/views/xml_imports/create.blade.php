@extends('layouts.app')
@section('title', 'Importar XML da NF-e')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    @if(session('error'))
        <div class="alert alert-danger mb-4" style="background-color:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;font-size:14px">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('xml-imports.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group mb-6">
            <label for="xml_file" class="form-label" style="font-weight: 600;">Selecione o arquivo XML da Nota Fiscal (NF-e) *</label>
            <p class="text-xs text-muted mb-3">O sistema fará a leitura automática dos itens da nota, do fornecedor e das parcelas de cobrança para geração de contas a pagar.</p>
            <input type="file" name="xml_file" id="xml_file" class="form-control @error('xml_file') is-invalid @enderror" accept=".xml" required style="padding: 10px;">
            @error('xml_file')
                <div class="invalid-feedback mt-2">{{ $message }}</div>
            @enderror
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Fazer Ingestão do XML</button>
            <a href="{{ route('xml-imports.index') }}" class="btn btn-secondary" style="padding: 10px 20px;">Voltar</a>
        </div>
    </form>
</div>
@endsection

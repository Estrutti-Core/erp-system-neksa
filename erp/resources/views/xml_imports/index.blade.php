@extends('layouts.app')
@section('title', 'Importações de XML (NF-e)')
@section('topbar-actions')
    <a href="{{ route('xml-imports.create') }}" class="btn btn-primary btn-sm">+ Importar Nova NF-e</a>
@endsection

@section('content')
<div class="card">
    @if($imports->isEmpty())
        <div style="text-align:center;padding:48px;color:#94a3b8">
            <div class="flex justify-center" style="margin-bottom:12px"><x-heroicon-o-document-arrow-up class="w-10 h-10 text-gray-300"/></div>
            <div style="font-size:16px;font-weight:600;margin-bottom:6px">Nenhum XML de NF-e importado</div>
            <a href="{{ route('xml-imports.create') }}" class="btn btn-primary mt-3">+ Importar XML</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Chave de Acesso</th>
                        <th>Fornecedor</th>
                        <th>Valor Total</th>
                        <th>Status</th>
                        <th>Data Importação</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($imports as $import)
                    <tr>
                        <td>
                            <div class="font-mono text-sm">{{ $import->access_key }}</div>
                            <div class="text-xs text-muted">{{ $import->filename }}</div>
                        </td>
                        <td>
                            @if($import->supplier)
                                <div class="font-semibold">{{ $import->supplier->name }}</div>
                                <div class="text-xs font-mono">{{ $import->supplier->cnpj }}</div>
                            @else
                                <span class="text-muted italic">Ainda não cadastrado / Associado</span>
                            @endif
                        </td>
                        <td>
                            <span class="font-semibold text-sm">
                                R$ {{ number_format($import->total_amount, 2, ',', '.') }}
                            </span>
                        </td>
                        <td>
                            @if($import->status === 'confirmed')
                                <span class="badge badge-success text-xs">Confirmado</span>
                            @else
                                <span class="badge badge-warning text-xs">Pendente Mapeamento</span>
                            @endif
                        </td>
                        <td>
                            {{ $import->imported_at ? $import->imported_at->format('d/H:i') : $import->created_at->format('d/H:i') }}
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('xml-imports.show', $import) }}" class="btn btn-secondary btn-sm">Mapear / Ver</a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $imports->links() }}</div>
    @endif
</div>
@endsection

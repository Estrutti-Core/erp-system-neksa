@extends('layouts.app')

@section('title', 'Serviços')

@section('topbar-actions')
    @can('create', App\Models\Service::class)
        <a href="{{ route('services.create') }}" class="btn btn-primary shadow-sm"
            style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
            <x-heroicon-o-plus class="w-4 h-4" /> Novo Serviço
        </a>
    @endcan
@endsection

@section('content')
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Filtros -->
        <div class="card mb-4 shadow-sm" style="border-radius: 12px; padding: 16px;">
            <form method="GET" action="{{ route('services.index') }}"
                class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap" style="flex: 1;">
                    <div style="position: relative; min-width: 280px; flex: 1;">
                        <span
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Buscar por nome ou SKU..." style="padding-left: 36px; border-radius: 8px;">
                    </div>

                    <div>
                        <select name="status" class="form-control" style="border-radius: 8px; min-width: 140px;"
                            onchange="this.form.submit()">
                            <option value="active" {{ request('status') === 'active' || !request('status') ? 'selected' : '' }}>Ativos</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inativos</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px;">Filtrar</button>
                    @if(request()->anyFilled(['search', 'status']))
                        <a href="{{ route('services.index') }}" class="btn btn-secondary btn-sm"
                            style="border-radius: 8px; color: #ef4444;">Limpar</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Tabela de Serviços -->
        <div class="card shadow-sm" style="border-radius: 12px; padding: 0; overflow: hidden; border: 1px solid #e2e8f0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="font-weight: 600; padding: 14px 20px;">Serviço</th>
                            <th>SKU</th>
                            <th>Preço</th>
                            <th>Alíquota ISS</th>
                            <th>Retenções Fonte</th>
                            <th>Código Municipal</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 20px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr>
                                <td style="padding: 14px 20px;">
                                    <div class="flex items-center gap-3">
                                        <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; 
                                                background: #ede9fe; color: #6d28d9;">
                                            S
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{{ $service->name }}</div>
                                            <div style="font-size: 12px; color: #64748b; max-width: 250px;" class="truncate">
                                                {{ $service->description ?: 'Sem descrição' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-family: monospace; font-size: 13px; color: #334155;">{{ $service->sku }}</td>
                                <td style="font-weight: 600; color: #0f172a;">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                <td>
                                    <span style="font-weight: 500; color: #475569;">
                                        {{ number_format($service->iss_rate, 2, ',', '.') }}%
                                    </span>
                                    @if($service->iss_withheld)
                                        <span class="badge badge-red" style="font-size: 10px; margin-left: 4px;">Retido</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-size: 11px; color: #64748b;">
                                        <div>PIS: {{ number_format($service->pis_retention_rate, 2, ',', '.') }}%</div>
                                        <div>COFINS: {{ number_format($service->cofins_retention_rate, 2, ',', '.') }}%</div>
                                        <div>CSLL: {{ number_format($service->csll_retention_rate, 2, ',', '.') }}%</div>
                                        <div>INSS: {{ number_format($service->inss_retention_rate, 2, ',', '.') }}%</div>
                                    </div>
                                </td>
                                <td style="font-family: monospace; font-size: 13px; color: #475569;">{{ $service->municipal_service_code ?: '—' }}</td>
                                <td>
                                    <span class="badge badge-{{ $service->is_active ? 'green' : 'red' }}">
                                        {{ $service->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <div class="flex gap-2 justify-end">
                                        <a href="{{ route('services.show', $service) }}" class="btn btn-secondary btn-sm"
                                            style="padding: 6px; border-radius: 6px;" title="Ver detalhes">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        @can('update', $service)
                                            <a href="{{ route('services.edit', $service) }}" class="btn btn-secondary btn-sm"
                                                style="padding: 6px; border-radius: 6px;" title="Editar">
                                                <x-heroicon-o-pencil class="w-4 h-4" />
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px 20px; color: #64748b;">
                                    <x-heroicon-o-wrench class="w-10 h-10" style="margin: 0 auto 12px; color: #cbd5e1;" />
                                    <p style="font-weight: 500;">Nenhum serviço encontrado</p>
                                    <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Cadastre seu primeiro serviço para iniciar.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($services->hasPages())
                <div style="border-top: 1px solid #e2e8f0; padding: 8px 16px;">
                    {{ $services->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

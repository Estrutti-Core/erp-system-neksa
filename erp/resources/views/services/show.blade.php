@extends('layouts.app')

@section('title', 'Detalhes do Serviço')

@section('content')
<div style="max-width: 900px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Cabeçalho de Ações -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('services.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>

        @can('update', $service)
            <a href="{{ route('services.edit', $service) }}" class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                <x-heroicon-o-pencil class="w-4 h-4"/> Editar Cadastro
            </a>
        @endcan
    </div>

    <!-- Layout Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Card de Informações Principais -->
        <div>
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <div class="flex items-center gap-3 mb-4">
                    <div style="width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px;
                        background: #ede9fe; color: #6d28d9;">
                        S
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-violet">Serviço</span>
                            <span class="badge badge-{{ $service->is_active ? 'green' : 'red' }}">{{ $service->is_active ? 'Ativo' : 'Inativo' }}</span>
                        </div>
                        <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">{{ $service->name }}</h2>
                    </div>
                </div>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <h4 style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Descrição / Escopo do Serviço</h4>
                    <p style="font-size: 14px; color: #334155; line-height: 1.6;">{!! nl2br(e($service->description ?: 'Sem descrição técnica fornecida.')) !!}</p>
                </div>
            </div>

            <!-- Dados Fiscais -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 16px;">Estrutura Fiscal & Tributária</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">CFOP (Operações de Serviço)</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $service->cfop ?: 'Não informado' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">CST / CSOSN</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $service->cst ?: 'Não informado' }}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Alíquota ISS</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">
                            {{ number_format($service->iss_rate, 2, ',', '.') }}%
                            @if($service->iss_withheld)
                                <span class="badge badge-red" style="font-size: 10px; margin-left: 4px;">Retido na Fonte</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Cód. Serviço Municipal (LC 116)</div>
                        <div style="font-family: monospace; font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $service->municipal_service_code ?: 'Não informado' }}</div>
                    </div>
                </div>
            </div>

            <!-- Retenções Federais -->
            <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 16px;">Retenções na Fonte (Federais)</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Alíquota PIS</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ number_format($service->pis_retention_rate, 2, ',', '.') }}%</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Alíquota COFINS</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ number_format($service->cofins_retention_rate, 2, ',', '.') }}%</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Alíquota CSLL</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ number_format($service->csll_retention_rate, 2, ',', '.') }}%</div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b;">Alíquota INSS</div>
                        <div style="font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ number_format($service->inss_retention_rate, 2, ',', '.') }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar do Item -->
        <div>
            <!-- Valores Comerciais -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background: linear-gradient(to bottom, #ffffff, #f8fafc);">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px;">Preço</h3>

                <div class="mb-1">
                    <div style="font-size: 11px; color: #64748b;">VALOR DO SERVIÇO</div>
                    <div style="font-size: 24px; font-weight: 800; color: #6366f1; margin-top: 2px;">R$ {{ number_format($service->price, 2, ',', '.') }}</div>
                </div>
            </div>

            <!-- Dados Operacionais -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Dados do Cadastro</h3>

                <div class="mb-3">
                    <div style="font-size: 11px; color: #64748b;">SKU / Código Interno</div>
                    <div style="font-family: monospace; font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px;">{{ $service->sku }}</div>
                </div>

                <div class="mb-3">
                    <div style="font-size: 11px; color: #64748b;">Tipo do Cadastro</div>
                    <div style="font-size: 13px; font-weight: 600; color: #1e293b; margin-top: 2px;">
                        <span class="badge badge-violet">Serviço Técnico</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

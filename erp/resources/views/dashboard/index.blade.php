@extends('layouts.app')
@section('title', 'Dashboard')

@section('topbar-actions')
    <div class="flex gap-2">
        @if(!auth()->user()->isTechnician())
            <a href="{{ route('quotes.create') }}" class="btn btn-secondary btn-sm" style="border-radius: 8px;">+ Novo Orçamento</a>
        @endif
        <a href="{{ route('service-orders.create') }}" class="btn btn-primary btn-sm" style="border-radius: 8px;">+ Nova OS</a>
    </div>
@endsection

@section('content')
    {{-- Grid de KPIs (Dinâmico com base no papel do usuário) --}}
    @if(auth()->user()->isTechnician())
        <!-- Grid Técnico: Foco 100% Operacional -->
        <div class="grid-4 mb-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede9fe"><x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-violet-600" /></div>
                <div>
                    <div class="stat-value">{{ $openOrders }}</div>
                    <div class="stat-label">Minhas OS Abertas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7"><x-heroicon-o-truck class="w-6 h-6 text-amber-600" /></div>
                <div>
                    <div class="stat-value">{{ $inService }}</div>
                    <div class="stat-label">Em Rota/Serviço</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7"><x-heroicon-o-check-circle class="w-6 h-6 text-green-600" /></div>
                <div>
                    <div class="stat-value">{{ $todayCompleted }}</div>
                    <div class="stat-label">Finalizadas Hoje</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe"><x-heroicon-o-user-circle class="w-6 h-6 text-blue-600" /></div>
                <div>
                    <div class="stat-value">Técnico</div>
                    <div class="stat-label">Perfil Logado</div>
                </div>
            </div>
        </div>
    @else
        <!-- Grid Operador/Admin: Visão Comercial e Operacional Completa -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <!-- OS Abertas -->
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede9fe"><x-heroicon-o-wrench-screwdriver class="w-6 h-6 text-violet-600" /></div>
                <div>
                    <div class="stat-value">{{ $openOrders }}</div>
                    <div class="stat-label">OS em Aberto</div>
                </div>
            </div>
            <!-- Em Atendimento -->
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7"><x-heroicon-o-truck class="w-6 h-6 text-amber-600" /></div>
                <div>
                    <div class="stat-value">{{ $inService }}</div>
                    <div class="stat-label">Em Execução</div>
                </div>
            </div>
            <!-- Orçamentos Pendentes -->
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff"><x-heroicon-o-document-magnifying-glass class="w-6 h-6 text-blue-600" /></div>
                <div>
                    <div class="stat-value">{{ $pendingQuotesCount }}</div>
                    <div class="stat-label">Orçamentos Ativos</div>
                </div>
            </div>
            <!-- Faturamento de Vendas -->
            <div class="stat-card">
                <div class="stat-icon" style="background:#ecfdf5"><x-heroicon-o-currency-dollar class="w-6 h-6 text-emerald-600" /></div>
                <div>
                    <div class="stat-value" style="font-size: 16px; font-weight: 800; color: #047857; font-family: monospace;">R$ {{ number_format($totalSalesValue, 2, ',', '.') }}</div>
                    <div class="stat-label">Faturamento de Vendas</div>
                </div>
            </div>
        </div>
    @endif

    {{-- Layout Principal de Cards --}}
    @if(auth()->user()->isTechnician())
        <!-- Layout Técnico: Duas colunas lado a lado -->
        <div class="grid-2">
            {{-- Próximas Visitas --}}
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-calendar-days class="w-5 h-5 text-gray-500" /> Próximas Visitas</h2>
                    <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">Ver todas</a>
                </div>

                @forelse($upcomingOrders as $os)
                    <a href="{{ route('service-orders.show', $os) }}" style="text-decoration:none;color:inherit">
                        <div style="padding:12px;border-radius:10px;border:1px solid #f1f5f9;margin-bottom:8px;transition:background .15s"
                            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                            <div class="flex justify-between items-center">
                                <span style="font-size:12px;font-weight:700;color:#64748b">{{ $os->code }}</span>
                                <span class="badge badge-{{ $os->status->color() }}">{{ $os->status->label() }}</span>
                            </div>
                            <div style="font-size:14px;font-weight:600;margin-top:4px">{{ $os->client->name }}</div>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-sm text-muted">{{ $os->clientAddress?->city ?? '—' }}</span>
                                @if($os->scheduled_at)
                                    <span class="text-sm text-muted flex items-center gap-1"><x-heroicon-o-clock class="w-4 h-4" />
                                        {{ $os->scheduled_at->format('d/m H:i') }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div style="text-align:center;padding:32px;color:#94a3b8">
                        <div style="margin-bottom:8px; display: flex; justify-content: center;"><x-heroicon-o-inbox class="w-10 h-10 text-gray-300" /></div>
                        <div>Nenhuma OS agendada</div>
                    </div>
                @endforelse
            </div>

            {{-- Atividade Recente --}}
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-clock class="w-5 h-5 text-gray-500" /> Atividade Recente</h2>
                    <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">Ver todas</a>
                </div>

                @forelse($recentOrders as $os)
                    <a href="{{ route('service-orders.show', $os) }}" style="text-decoration:none;color:inherit">
                        <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9">
                            <div style="width:40px;height:40px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-gray-500" /></div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    {{ $os->client->name }}</div>
                                <div class="text-xs text-muted mt-1">{{ $os->code }} · {{ $os->created_at->diffForHumans() }}</div>
                            </div>
                            <span class="badge badge-{{ $os->status->color() }}">{{ $os->status->label() }}</span>
                        </div>
                    </a>
                @empty
                    <div style="text-align:center;padding:32px;color:#94a3b8">
                        <div style="margin-bottom:8px; display: flex; justify-content: center;">
                            <x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-300" /></div>
                        <div>Sem atividade recente</div>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <!-- Layout Operador/Admin: Lado a Lado (Operacional vs Comercial) -->
        <div class="grid-2">
            {{-- Coluna 1: Lado Operacional (OSs) --}}
            <div style="display: flex; flex-direction: column; gap: 20px;">
                {{-- Próximas OS --}}
                <div class="card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-calendar-days class="w-5 h-5 text-gray-500" /> Próximas Visitas</h2>
                        <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">Ver todas</a>
                    </div>

                    @forelse($upcomingOrders as $os)
                        <a href="{{ route('service-orders.show', $os) }}" style="text-decoration:none;color:inherit">
                            <div style="padding:12px;border-radius:10px;border:1px solid #f1f5f9;margin-bottom:8px;transition:background .15s"
                                onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                <div class="flex justify-between items-center">
                                    <span style="font-size:12px;font-weight:700;color:#64748b">{{ $os->code }}</span>
                                    <span class="badge badge-{{ $os->status->color() }}">{{ $os->status->label() }}</span>
                                </div>
                                <div style="font-size:14px;font-weight:600;margin-top:4px">{{ $os->client->name }}</div>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-sm text-muted">{{ $os->clientAddress?->city ?? '—' }}</span>
                                    @if($os->scheduled_at)
                                        <span class="text-sm text-muted flex items-center gap-1"><x-heroicon-o-clock class="w-4 h-4" />
                                            {{ $os->scheduled_at->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div style="text-align:center;padding:32px;color:#94a3b8">
                            <div style="margin-bottom:8px; display: flex; justify-content: center;"><x-heroicon-o-inbox class="w-10 h-10 text-gray-300" /></div>
                            <div>Nenhuma OS agendada</div>
                        </div>
                    @endforelse
                </div>

                {{-- OS Recentes --}}
                <div class="card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-clock class="w-5 h-5 text-gray-500" /> Atividade Recente (OS)</h2>
                        <a href="{{ route('service-orders.index') }}" class="btn btn-secondary btn-sm">Ver todas</a>
                    </div>

                    @forelse($recentOrders as $os)
                        <a href="{{ route('service-orders.show', $os) }}" style="text-decoration:none;color:inherit">
                            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9">
                                <div style="width:40px;height:40px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-gray-500" /></div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        {{ $os->client->name }}</div>
                                    <div class="text-xs text-muted mt-1">{{ $os->code }} · {{ $os->created_at->diffForHumans() }}</div>
                                </div>
                                <span class="badge badge-{{ $os->status->color() }}">{{ $os->status->label() }}</span>
                            </div>
                        </a>
                    @empty
                        <div style="text-align:center;padding:32px;color:#94a3b8">
                            <div style="margin-bottom:8px; display: flex; justify-content: center;">
                                <x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-300" /></div>
                            <div>Sem atividade recente</div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Coluna 2: Lado Comercial (Orçamentos & Vendas) --}}
            <div style="display: flex; flex-direction: column; gap: 20px;">
                {{-- Orçamentos Recentes --}}
                <div class="card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-document-magnifying-glass class="w-5 h-5 text-indigo-500" /> Orçamentos Recentes</h2>
                        <a href="{{ route('quotes.index') }}" class="btn btn-secondary btn-sm">Ver todos</a>
                    </div>

                    @forelse($recentQuotes as $quote)
                        <a href="{{ route('quotes.show', $quote) }}" style="text-decoration:none;color:inherit">
                            <div style="padding:12px;border-radius:10px;border:1px solid #f1f5f9;margin-bottom:8px;transition:background .15s"
                                onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                <div class="flex justify-between items-center">
                                    <span style="font-size:12px;font-weight:700;color:#64748b">{{ $quote->code }}</span>
                                    <span class="badge badge-{{ $quote->status->color() }}">{{ $quote->status->label() }}</span>
                                </div>
                                <div style="font-size:14px;font-weight:600;margin-top:4px">{{ $quote->client->name }}</div>
                                <div class="flex justify-between items-center mt-2">
                                    <span style="font-size:11px;color:#94a3b8;">Criado {{ $quote->created_at->diffForHumans() }}</span>
                                    <span style="font-weight:700;font-size:13px;color:#4f46e5;font-family:monospace;">R$ {{ number_format($quote->total_amount, 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div style="text-align:center;padding:32px;color:#94a3b8">
                            <div style="margin-bottom:8px; display: flex; justify-content: center;"><x-heroicon-o-document-text class="w-10 h-10 text-gray-300" /></div>
                            <div>Nenhum orçamento pendente</div>
                        </div>
                    @endforelse
                </div>

                {{-- Vendas Recentes --}}
                <div class="card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="font-bold flex items-center gap-2" style="font-size:15px"><x-heroicon-o-shopping-bag class="w-5 h-5 text-emerald-500" /> Vendas Recentes</h2>
                        <a href="{{ route('sales.index') }}" class="btn btn-secondary btn-sm">Ver todas</a>
                    </div>

                    @forelse($recentSales as $sale)
                        <a href="{{ route('sales.index') }}" style="text-decoration:none;color:inherit">
                            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9">
                                <div style="width:40px;height:40px;border-radius:10px;background:#ecfdf5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <x-heroicon-o-currency-dollar class="w-5 h-5 text-emerald-600" /></div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        {{ $sale->client->name }}</div>
                                    <div class="text-xs text-muted mt-1">{{ $sale->code }} · {{ $sale->created_at->diffForHumans() }}</div>
                                </div>
                                <div style="text-align:right;">
                                    <span style="font-weight:700;font-size:13px;color:#059669;font-family:monospace;">R$ {{ number_format($sale->total_amount, 2, ',', '.') }}</span>
                                    <div style="font-size:9px;color:#94a3b8;margin-top:2px;">{{ $sale->status->label() }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div style="text-align:center;padding:32px;color:#94a3b8">
                            <div style="margin-bottom:8px; display: flex; justify-content: center;">
                                <x-heroicon-o-shopping-bag class="w-10 h-10 text-gray-300" /></div>
                            <div>Nenhuma venda registrada</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- Quick Actions --}}
    <div class="card mt-4">
        <h2 class="font-bold mb-3 flex items-center gap-2" style="font-size:15px"><x-heroicon-s-bolt class="w-5 h-5 text-amber-500" /> Ações Rápidas</h2>
        <div class="flex gap-3 flex-wrap">
            @if(!auth()->user()->isTechnician())
                <a href="{{ route('quotes.create') }}" class="btn btn-secondary" style="border-radius: 8px;"><x-heroicon-o-document-plus class="w-4 h-4" /> Novo Orçamento</a>
            @endif
            <a href="{{ route('service-orders.create') }}" class="btn btn-primary" style="border-radius: 8px;"><x-heroicon-o-plus class="w-4 h-4" /> Nova OS</a>
            <a href="{{ route('clients.create') }}" class="btn btn-secondary" style="border-radius: 8px;"><x-heroicon-o-user-plus class="w-4 h-4" /> Novo Cliente</a>
            <a href="{{ route('routes.index') }}" class="btn btn-secondary" style="border-radius: 8px;"><x-heroicon-o-map class="w-4 h-4" /> Roteirização</a>
            <a href="{{ route('service-orders.index') }}?status=open" class="btn btn-secondary" style="border-radius: 8px;"><x-heroicon-o-clipboard-document-list class="w-4 h-4" /> OS Abertas</a>
        </div>
    </div>
@endsection
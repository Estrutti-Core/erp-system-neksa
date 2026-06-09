<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Dashboard')</title>
    <meta name="description" content="@yield('description', 'Sistema de Ordens de Serviço Externas')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Estilos adicionais para suporte a Sidebar Colapsável e Acordeões */
        :root {
            --sidebar-collapsed-width: 70px;
        }

        body.sidebar-collapsed-active .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        body.sidebar-collapsed-active .main-wrap {
            margin-left: var(--sidebar-collapsed-width);
        }

        body.sidebar-collapsed-active .sidebar .logo-text,
        body.sidebar-collapsed-active .sidebar .logo-sub,
        body.sidebar-collapsed-active .sidebar .nav-item span:not(.nav-icon),
        body.sidebar-collapsed-active .sidebar .user-info,
        body.sidebar-collapsed-active .sidebar .btn-logout-text,
        body.sidebar-collapsed-active .sidebar .nav-section-chevron,
        body.sidebar-collapsed-active .sidebar .nav-section-label span {
            display: none !important;
        }

        body.sidebar-collapsed-active .sidebar .nav-section-label {
            text-align: center;
            padding: 12px 0 6px;
        }

        body.sidebar-collapsed-active .sidebar .sidebar-logo {
            padding: 16px 12px;
            justify-content: center;
        }

        body.sidebar-collapsed-active .sidebar .sidebar-logo img,
        body.sidebar-collapsed-active .sidebar .logo-icon {
            margin: 0 auto;
        }

        body.sidebar-collapsed-active .sidebar .nav-item {
            justify-content: center;
            padding: 10px 0;
        }

        body.sidebar-collapsed-active .sidebar .user-card {
            justify-content: center;
            padding: 10px 0;
        }

        /* Acordeão por Categoria */
        .sidebar-section {
            margin-bottom: 8px;
        }

        .nav-section-label {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 12px 6px;
            user-select: none;
            transition: color 0.15s;
        }

        .nav-section-label:hover {
            color: #e2e8f0;
        }

        .nav-section-content {
            transition: max-height 0.25s ease-out, opacity 0.25s ease-out;
            max-height: 500px;
            opacity: 1;
            overflow: hidden;
        }

        .sidebar-section.collapsed .nav-section-content {
            max-height: 0 !important;
            opacity: 0 !important;
            pointer-events: none;
        }

        .nav-section-chevron {
            transition: transform 0.2s ease;
        }

        .sidebar-section.collapsed .nav-section-chevron {
            transform: rotate(-90deg);
        }

        /* Botão Colapso Sidebar */
        .btn-collapse-sidebar {
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: var(--sidebar-text);
            cursor: pointer;
            padding: 10px;
            border-radius: 6px;
            margin: 4px auto;
            transition: background 0.15s, color 0.15s;
        }

        .btn-collapse-sidebar:hover {
            background: #1e293b;
            color: #fff;
        }

        /* Custom scrollbar para a sidebar - Visual Clean Premium */
        .sidebar-nav {
            overflow-y: auto;
            overflow-x: hidden;
            -ms-overflow-style: none; /* IE/Edge */
            scrollbar-width: none; /* Firefox */
            scroll-behavior: smooth;
        }

        .sidebar-nav::-webkit-scrollbar {
            display: none; /* Chrome/Safari/Opera */
        }
    </style>
</head>
<body class="h-full">
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        @if(isset($tenantCompany) && $tenantCompany->logo_path)
            <img src="{{ asset('storage/' . $tenantCompany->logo_path) }}" alt="Logo" class="logo-icon">
        @else
            <div class="logo-icon">{{ substr($tenantCompany->name ?? 'N', 0, 1) }}</div>
        @endif
        <div>
            <div class="logo-text">{{ $tenantCompany->name ?? 'Neksa ERP' }}</div>
            <div class="logo-sub">Ordens de Serviço</div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Seção Principal -->
        <div class="sidebar-section" id="sec-principal">
            <div class="nav-section-label" onclick="toggleSection('principal')">
                <span>Principal</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-chart-bar class="w-5 h-5"/></span>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Seção Comercial -->
        <div class="sidebar-section" id="sec-comercial">
            <div class="nav-section-label" onclick="toggleSection('comercial')">
                <span>Comercial</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('quotes.index') }}" class="nav-item {{ request()->routeIs('quotes.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-document-text class="w-5 h-5"/></span>
                    <span>Orçamentos</span>
                </a>
                <a href="{{ route('sales.index') }}" class="nav-item {{ request()->routeIs('sales.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-shopping-bag class="w-5 h-5"/></span>
                    <span>Vendas</span>
                </a>
            </div>
        </div>

        <!-- Seção Estoque e Compras -->
        <div class="sidebar-section" id="sec-estoque-compras">
            <div class="nav-section-label" onclick="toggleSection('estoque-compras')">
                <span>Estoque & Compras</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-cube class="w-5 h-5"/></span>
                    <span>Produtos</span>
                </a>
                <a href="{{ route('services.index') }}" class="nav-item {{ request()->routeIs('services.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-wrench class="w-5 h-5"/></span>
                    <span>Serviços</span>
                </a>
                <a href="{{ route('purchase-orders.index') }}" class="nav-item {{ request()->routeIs('purchase-orders.*') || request()->routeIs('inventory-conferences.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-shopping-cart class="w-5 h-5"/></span>
                    <span>Pedidos de Compra</span>
                </a>
                <a href="{{ route('xml-imports.index') }}" class="nav-item {{ request()->routeIs('xml-imports.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-document-arrow-up class="w-5 h-5"/></span>
                    <span>Entradas</span>
                </a>
            </div>
        </div>

        <!-- Seção Operação -->
        <div class="sidebar-section" id="sec-operacao">
            <div class="nav-section-label" onclick="toggleSection('operacao')">
                <span>Operação</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('service-orders.index') }}" class="nav-item {{ request()->routeIs('service-orders.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-wrench-screwdriver class="w-5 h-5"/></span>
                    <span>Ordens de Serviço</span>
                </a>
                <a href="{{ route('routes.index') }}" class="nav-item {{ request()->routeIs('routes.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-map class="w-5 h-5"/></span>
                    <span>Roteirização</span>
                </a>
            </div>
        </div>

        <!-- Seção Financeiro -->
        <div class="sidebar-section" id="sec-financeiro">
            <div class="nav-section-label" onclick="toggleSection('financeiro')">
                <span>Financeiro</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('receivables.index') }}" class="nav-item {{ request()->routeIs('receivables.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-arrow-trending-up class="w-5 h-5"/></span>
                    <span>Contas a Receber</span>
                </a>
                <a href="{{ route('payables.index') }}" class="nav-item {{ request()->routeIs('payables.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-arrow-trending-down class="w-5 h-5"/></span>
                    <span>Contas a Pagar</span>
                </a>
                <a href="{{ route('financial-accounts.index') }}" class="nav-item {{ request()->routeIs('financial-accounts.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-credit-card class="w-5 h-5"/></span>
                    <span>Contas Financeiras</span>
                </a>
                <a href="{{ route('financial.cash-flow') }}" class="nav-item {{ request()->routeIs('financial.cash-flow') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-banknotes class="w-5 h-5"/></span>
                    <span>Fluxo de Caixa</span>
                </a>
                <a href="{{ route('financial.closing') }}" class="nav-item {{ request()->routeIs('financial.closing*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-calculator class="w-5 h-5"/></span>
                    <span>Fechamento Mensal</span>
                </a>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('financial.audit') }}" class="nav-item {{ request()->routeIs('financial.audit') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-shield-check class="w-5 h-5"/></span>
                    <span>Auditoria</span>
                </a>
                @endif
            </div>
        </div>

        <!-- Seção Cadastros -->
        <div class="sidebar-section" id="sec-cadastros">
            <div class="nav-section-label" onclick="toggleSection('cadastros')">
                <span>Cadastros</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('clients.index') }}" class="nav-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-users class="w-5 h-5"/></span>
                    <span>Clientes</span>
                </a>
                <a href="{{ route('suppliers.index') }}" class="nav-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-truck class="w-5 h-5"/></span>
                    <span>Fornecedores</span>
                </a>
            </div>
        </div>

        <!-- Seção Configurações -->
        <div class="sidebar-section" id="sec-configuracoes">
            <div class="nav-section-label" onclick="toggleSection('configuracoes')">
                <span>Configurações</span>
                <x-heroicon-o-chevron-down class="w-3 h-3 nav-section-chevron"/>
            </div>
            <div class="nav-section-content">
                <a href="{{ route('company.edit') }}" class="nav-item {{ request()->routeIs('company.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-cog-6-tooth class="w-5 h-5"/></span>
                    <span>Empresa</span>
                </a>
                <a href="{{ route('settings.statuses.index') }}" class="nav-item {{ request()->routeIs('settings.statuses.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-adjustments-horizontal class="w-5 h-5"/></span>
                    <span>Status de OS</span>
                </a>
                <a href="{{ route('settings.checklists.index') }}" class="nav-item {{ request()->routeIs('settings.checklists.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-clipboard-document-check class="w-5 h-5"/></span>
                    <span>Checklists</span>
                </a>
                <a href="{{ route('payment-conditions.index') }}" class="nav-item {{ request()->routeIs('payment-conditions.*') ? 'active' : '' }}">
                    <span class="nav-icon"><x-heroicon-o-credit-card class="w-5 h-5"/></span>
                    <span>Condições de Pagamento</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Botão Colapso Horizontal -->
    <button class="btn-collapse-sidebar hide-mobile" onclick="toggleSidebarCollapse()" title="Recolher/Expandir Menu">
        <x-heroicon-o-arrows-right-left class="w-5 h-5"/>
    </button>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">{{ auth()->user()->initials }}</div>
            <div class="user-info">
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">{{ ucfirst(auth()->user()->getRoleNames()->first() ?? 'Usuário') }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:8px">
            @csrf
            <button type="submit" class="btn btn-secondary w-full" style="justify-content:center;font-size:13px">
                <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4"/>
                <span class="btn-logout-text" style="margin-left: 6px;">Sair</span>
            </button>
        </form>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <div class="flex items-center gap-3">
            <button class="hamburger" onclick="toggleSidebar()"><x-heroicon-o-bars-3 class="w-6 h-6"/></button>
            <h1 class="topbar-title">@yield('title', 'Dashboard')</h1>
        </div>
        <div class="topbar-actions">
            @yield('topbar-actions')
        </div>
    </header>

    @if(session('success'))
        <div style="padding:0 24px"><div class="alert alert-success mt-3"><x-heroicon-s-check-circle class="w-5 h-5"/> {{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div style="padding:0 24px"><div class="alert alert-error mt-3"><x-heroicon-s-x-circle class="w-5 h-5"/> {{ session('error') }}</div></div>
    @endif

    <main class="page-content">
        @yield('content')
    </main>
</div>

<nav class="bottom-nav">
    <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <span class="bottom-nav-icon"><x-heroicon-o-chart-bar class="w-6 h-6"/></span><span>Dashboard</span>
    </a>
    <a href="{{ route('service-orders.index') }}" class="bottom-nav-item {{ request()->routeIs('service-orders.*') ? 'active' : '' }}">
        <span class="bottom-nav-icon"><x-heroicon-o-wrench-screwdriver class="w-6 h-6"/></span><span>OS</span>
    </a>
    <a href="{{ route('service-orders.create') }}" class="bottom-nav-item">
        <span class="bottom-nav-icon text-indigo-600"><x-heroicon-s-plus-circle class="w-8 h-8"/></span>
    </a>
    <a href="{{ route('routes.index') }}" class="bottom-nav-item {{ request()->routeIs('routes.*') ? 'active' : '' }}">
        <span class="bottom-nav-icon"><x-heroicon-o-map class="w-6 h-6"/></span><span>Rotas</span>
    </a>
    <a href="{{ route('clients.index') }}" class="bottom-nav-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">
        <span class="bottom-nav-icon"><x-heroicon-o-users class="w-6 h-6"/></span><span>Clientes</span>
    </a>
</nav>

@livewireScripts
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/imask"></script>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');}

// Acordeão por Categoria
function toggleSection(name) {
    const sec = document.getElementById('sec-' + name);
    if (sec) {
        sec.classList.toggle('collapsed');
        localStorage.setItem('sidebar-section-' + name, sec.classList.contains('collapsed') ? 'collapsed' : 'expanded');
    }
}

// Colapso Horizontal da Sidebar
function toggleSidebarCollapse() {
    const body = document.body;
    body.classList.toggle('sidebar-collapsed-active');
    localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed-active') ? 'collapsed' : 'expanded');
}

// Carrega estados do LocalStorage antes/durante a renderização
(function() {
    // Carrega colapso horizontal
    if (localStorage.getItem('sidebar-collapsed') === 'collapsed') {
        document.body.classList.add('sidebar-collapsed-active');
    }
    
    // Carrega subseções
    const sections = ['principal', 'comercial', 'estoque-compras', 'operacao', 'financeiro', 'cadastros', 'configuracoes'];
    sections.forEach(secName => {
        const state = localStorage.getItem('sidebar-section-' + secName);
        const el = document.getElementById('sec-' + secName);
        if (el) {
            // Por padrão, se não tiver estado, começa expandida. Se tiver 'collapsed', adiciona a classe.
            if (state === 'collapsed') {
                el.classList.add('collapsed');
            }
        }
    });
})();

document.addEventListener('DOMContentLoaded', function() {
    // Re-checa se as classes de seção estão sincronizadas com o DOM (para segurança após carregamento completo)
    const sections = ['principal', 'comercial', 'estoque-compras', 'operacao', 'financeiro', 'cadastros', 'configuracoes'];
    sections.forEach(secName => {
        const state = localStorage.getItem('sidebar-section-' + secName);
        const el = document.getElementById('sec-' + secName);
        if (el) {
            if (state === 'collapsed') {
                el.classList.add('collapsed');
            } else if (state === 'expanded') {
                el.classList.remove('collapsed');
            }
        }
    });

    // Auto-mask Document (CPF/CNPJ)
    document.querySelectorAll('[data-mask="document"]').forEach(el => {
        IMask(el, {
            mask: [
                { mask: '000.000.000-00' },
                { mask: '00.000.000/0000-00' }
            ]
        });
    });

    // Auto-mask Phone
    document.querySelectorAll('[data-mask="phone"]').forEach(el => {
        IMask(el, {
            mask: [
                { mask: '(00) 0000-0000' },
                { mask: '(00) 00000-0000' }
            ]
        });
    });

    // Auto-mask CEP
    document.querySelectorAll('[data-mask="cep"]').forEach(el => {
        IMask(el, { mask: '00000-000' });
    });
});
</script>
@stack('scripts')
</body>
</html>


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
</head>
<body>
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
        <div class="nav-section-label">Principal</div>
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-chart-bar class="w-5 h-5"/></span> Dashboard
        </a>
        
        <div class="nav-section-label">Comercial</div>
        <a href="{{ route('quotes.index') }}" class="nav-item {{ request()->routeIs('quotes.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-document-text class="w-5 h-5"/></span> Orçamentos
        </a>
        <a href="{{ route('sales.index') }}" class="nav-item {{ request()->routeIs('sales.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-shopping-bag class="w-5 h-5"/></span> Vendas
        </a>

        <div class="nav-section-label">Operação</div>
        <a href="{{ route('service-orders.index') }}" class="nav-item {{ request()->routeIs('service-orders.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-wrench-screwdriver class="w-5 h-5"/></span> Ordens de Serviço
        </a>
        <a href="{{ route('routes.index') }}" class="nav-item {{ request()->routeIs('routes.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-map class="w-5 h-5"/></span> Roteirização
        </a>

        <div class="nav-section-label">Cadastros</div>
        <a href="{{ route('clients.index') }}" class="nav-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-users class="w-5 h-5"/></span> Clientes
        </a>
        <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-cube class="w-5 h-5"/></span> Produtos
        </a>
        <a href="{{ route('services.index') }}" class="nav-item {{ request()->routeIs('services.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-wrench class="w-5 h-5"/></span> Serviços
        </a>

        <div class="nav-section-label">Configurações</div>
        <a href="{{ route('company.edit') }}" class="nav-item {{ request()->routeIs('company.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-cog-6-tooth class="w-5 h-5"/></span> Empresa
        </a>
        <a href="{{ route('settings.statuses.index') }}" class="nav-item {{ request()->routeIs('settings.statuses.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-adjustments-horizontal class="w-5 h-5"/></span> Status de OS
        </a>
        <a href="{{ route('settings.checklists.index') }}" class="nav-item {{ request()->routeIs('settings.checklists.*') ? 'active' : '' }}">
            <span class="nav-icon"><x-heroicon-o-clipboard-document-check class="w-5 h-5"/></span> Checklists
        </a>
    </nav>
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
            <button type="submit" class="btn btn-secondary w-full" style="justify-content:center;font-size:13px"><x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4"/> Sair</button>
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

document.addEventListener('DOMContentLoaded', function() {
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

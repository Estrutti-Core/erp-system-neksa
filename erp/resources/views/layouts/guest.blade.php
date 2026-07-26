<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="Sistema de Ordens de Serviço Externas">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon.svg">

    <title>{{ $tenantCompany->name ?? config('app.name', 'Neksa ERP') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
    html, body { height: 100%; }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: #fff;
        display: flex;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    .g-wrap {
        display: flex;
        width: 100%;
        min-height: 100vh;
    }

    /* Painel esquerdo — marca */
    .g-brand {
        background: #0f172a;
        width: 400px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 48px 40px;
    }

    .g-logo { display: flex; align-items: center; gap: 14px; }

    .g-logo-icon {
        width: 48px;
        height: 48px;
        background: #4f46e5;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 800;
        color: #fff;
        flex-shrink: 0;
        letter-spacing: -.04em;
    }

    .g-logo-img { width: 48px; height: 48px; border-radius: 10px; object-fit: contain; }

    .g-logo-name { font-size: 20px; font-weight: 800; color: #f8fafc; letter-spacing: -.03em; line-height: 1.15; }
    .g-logo-sub  { font-size: 11px; color: #475569; font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }

    .g-brand-body { padding: 32px 0; }

    .g-tagline {
        font-size: 26px;
        font-weight: 700;
        color: #f8fafc;
        line-height: 1.3;
        letter-spacing: -.025em;
        margin-bottom: 14px;
    }

    .g-desc { font-size: 14px; color: #64748b; line-height: 1.65; }

    .g-brand-footer { font-size: 11px; color: #1e293b; font-weight: 500; }

    /* Painel direito — formulário */
    .g-form-panel {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        padding: 40px 24px;
    }

    .g-form-inner { width: 100%; max-width: 380px; }

    /* ── Elementos do formulário (usados em login.blade.php) ─────────── */
    .g-title    { font-size: 22px; font-weight: 700; color: #0f172a; letter-spacing: -.025em; margin-bottom: 6px; }
    .g-subtitle { font-size: 14px; color: #64748b; margin-bottom: 28px; }

    .g-field { margin-bottom: 18px; }

    .g-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .g-input {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        color: #0f172a;
        background: #fff;
        transition: border-color .15s;
        outline: none;
        -webkit-appearance: none;
    }

    .g-input:focus { border-color: #4f46e5; }
    .g-input.is-invalid { border-color: #ef4444; }

    .g-error { font-size: 12px; color: #ef4444; margin-top: 4px; }

    .g-remember {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 24px;
    }

    .g-remember input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: #4f46e5;
        cursor: pointer;
        flex-shrink: 0;
    }

    .g-remember label { font-size: 13px; color: #64748b; cursor: pointer; user-select: none; }

    .g-btn {
        display: block;
        width: 100%;
        padding: 13px;
        background: #4f46e5;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 700;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        text-align: center;
        transition: background .15s;
        text-decoration: none;
        line-height: 1;
    }

    .g-btn:hover  { background: #3730a3; }
    .g-btn:active { background: #312e81; }

    .g-link {
        display: block;
        text-align: center;
        margin-top: 16px;
        font-size: 13px;
        color: #4f46e5;
        text-decoration: none;
        font-weight: 500;
    }

    .g-link:hover { text-decoration: underline; }

    .g-status {
        background: #dcfce7;
        border: 1px solid #86efac;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13px;
        color: #166534;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .g-alert {
        background: #fee2e2;
        border: 1px solid #fca5a5;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13px;
        color: #dc2626;
        font-weight: 500;
        margin-bottom: 20px;
    }

    /* ── Mobile ────────────────────────────────────────────────────────── */
    @media (max-width: 768px) {
        .g-wrap { flex-direction: column; }

        .g-brand {
            width: 100%;
            flex-direction: row;
            align-items: center;
            padding: 16px 20px;
        }

        .g-brand-body,
        .g-brand-footer { display: none; }

        .g-form-panel {
            align-items: flex-start;
            padding: 28px 20px;
        }

        .g-form-inner { max-width: 100%; }
        .g-title { font-size: 20px; }
    }
    </style>
</head>
<body>
<div class="g-wrap">

    {{-- Painel de marca --}}
    <div class="g-brand">
        <div class="g-logo">
            @if(isset($tenantCompany) && $tenantCompany?->logo_path)
                <img src="{{ asset('storage/' . $tenantCompany->logo_path) }}" alt="Logo" class="g-logo-img">
            @else
                <div class="g-logo-icon">N</div>
            @endif
            <div>
                <div class="g-logo-name">{{ $tenantCompany->name ?? config('app.name', 'Neksa ERP') }}</div>
                <div class="g-logo-sub">Ordens de Serviço</div>
            </div>
        </div>

        <div class="g-brand-body">
            <div class="g-tagline">Gerencie suas operações em campo</div>
            <div class="g-desc">Controle de OS, checklists,<br>roteirização e financeiro<br>em um único lugar.</div>
        </div>

        <div class="g-brand-footer">© {{ date('Y') }} Neksa · Todos os direitos reservados</div>
    </div>

    {{-- Painel do formulário --}}
    <div class="g-form-panel">
        <div class="g-form-inner">
            {{ $slot }}
        </div>
    </div>

</div>
</body>
</html>

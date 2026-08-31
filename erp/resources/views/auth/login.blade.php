<x-guest-layout>

    @if(session('status'))
        <div class="g-status">{{ session('status') }}</div>
    @endif

    <div class="g-title">Entrar</div>
    <div class="g-subtitle">Acesse sua conta para continuar</div>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        {{-- E-mail --}}
        <div class="g-field">
            <label for="email" class="g-label">E-mail</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="g-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                required
                autofocus
                autocomplete="username"
                placeholder="voce@empresa.com"
            >
            @error('email')
                <div class="g-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Senha --}}
        <div class="g-field">
            <label for="password" class="g-label">Senha</label>
            <input
                id="password"
                type="password"
                name="password"
                class="g-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                required
                autocomplete="current-password"
                placeholder="••••••••"
            >
            @error('password')
                <div class="g-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Lembrar --}}
        <div class="g-remember">
            <input id="remember_me" type="checkbox" name="remember" value="1">
            <label for="remember_me">Manter conectado</label>
        </div>

        {{-- Submit --}}
        <button type="submit" class="g-btn">Entrar</button>

        @if(Route::has('password.request'))
            <a class="g-link" href="{{ route('password.request') }}">Esqueci minha senha</a>
        @endif
    </form>

</x-guest-layout>

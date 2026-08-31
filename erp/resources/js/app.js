import './bootstrap';

// ── Service Worker ──────────────────────────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

// ── Ngrok bypass: injeta header em fetch e XHR quando rodando em túnel ────
(function () {
    const isNgrok =
        location.hostname.endsWith('.ngrok-free.app') ||
        location.hostname.endsWith('.ngrok-free.dev') ||
        location.hostname.endsWith('.ngrok.io');

    if (!isNgrok) return;

    // Patch fetch
    const originalFetch = window.fetch;
    window.fetch = function (input, init = {}) {
        const headers = new Headers(init.headers || {});
        headers.set('ngrok-skip-browser-warning', '1');
        return originalFetch.call(this, input, { ...init, headers });
    };

    // Patch XMLHttpRequest (usado pelo Livewire internamente)
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (...args) {
        this._patchNgrok = true;
        return originalOpen.apply(this, args);
    };

    XMLHttpRequest.prototype.send = function (body) {
        if (this._patchNgrok) {
            try { this.setRequestHeader('ngrok-skip-browser-warning', '1'); } catch (_) {}
        }
        return originalSend.call(this, body);
    };
})();

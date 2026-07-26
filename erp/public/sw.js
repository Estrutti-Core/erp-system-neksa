/**
 * Neksa ERP — Service Worker
 * Estratégia: Cache-First para assets estáticos, Network-First para HTML.
 * Bump CACHE_VERSION a cada deploy para limpar caches antigos.
 */

const CACHE_VERSION = 'v1';
const STATIC_CACHE  = `neksa-static-${CACHE_VERSION}`;
const PAGE_CACHE    = `neksa-pages-${CACHE_VERSION}`;

const isNgrok = (
    self.location.hostname.endsWith('.ngrok-free.app') ||
    self.location.hostname.endsWith('.ngrok-free.dev') ||
    self.location.hostname.endsWith('.ngrok.io')
);

/**
 * Clona a request adicionando o header que bypassa o interstitial do ngrok.
 * Só atua quando rodando em túnel ngrok — sem impacto em produção.
 */
function withNgrokBypass(request) {
    if (!isNgrok || request.method !== 'GET') return request;
    const headers = new Headers(request.headers);
    headers.set('ngrok-skip-browser-warning', '1');
    return new Request(request, { headers });
}

// ── Install: pré-cacheia a tela de login ───────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(PAGE_CACHE)
            .then(cache => cache.addAll(['/login']))
            .catch(() => {}) // não bloqueia o install se o pre-cache falhar
    );
    self.skipWaiting();
});

// ── Activate: remove caches de versões anteriores ─────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== STATIC_CACHE && k !== PAGE_CACHE)
                    .map(k => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// ── Fetch ─────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignora non-GET, rotas de API e requests cross-origin não confiáveis
    if (request.method !== 'GET') return;
    if (url.pathname.startsWith('/api/')) return;
    if (
        url.origin !== self.location.origin &&
        !url.hostname.includes('fonts.gstatic.com') &&
        !url.hostname.includes('fonts.googleapis.com')
    ) return;

    const augmented = withNgrokBypass(request);

    // Assets estáticos (Vite build, fontes, ícones, imagens) → Cache First
    const isStaticAsset =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/storage/') ||
        url.hostname.includes('fonts.gstatic.com') ||
        url.hostname.includes('fonts.googleapis.com') ||
        /\.(css|js|woff2?|ttf|eot|svg|png|jpe?g|webp|ico|gif)(\?.*)?$/.test(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(async cache => {
                const cached = await cache.match(request);
                if (cached) return cached;
                try {
                    const response = await fetch(augmented);
                    if (response.ok) {
                        const copy = response.clone();
                        cache.put(request, copy);
                    }
                    return response;
                } catch {
                    return new Response('', { status: 503, statusText: 'Offline' });
                }
            })
        );
        return;
    }

    // Navegação HTML → Network First, fallback para cache (login offline)
    if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(augmented)
                .then(response => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(PAGE_CACHE).then(c => c.put(request, copy));
                    }
                    return response;
                })
                .catch(async () => {
                    const cached = await caches.match(request);
                    return cached ?? caches.match('/login');
                })
        );
    }
});

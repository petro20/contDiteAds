// Service Worker básico — habilita instalação PWA e cache leve dos assets estáticos.
// Não faz cache de páginas dinâmicas (sempre busca da rede).
const CACHE_VER = 'diteads-v1';
const STATIC_ASSETS = [
  '/assets/img/logo.png',
  '/assets/css/style.css',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_VER).then(c => c.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_VER).map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  // PHP / API / POST → sempre da rede (não cacheia)
  if (e.request.method !== 'GET' || url.pathname.endsWith('.php') || url.pathname.includes('/uploads/')) return;
  // Assets estáticos: cache first, network fallback
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(
      caches.match(e.request).then(cached => cached || fetch(e.request).then(resp => {
        const copy = resp.clone();
        caches.open(CACHE_VER).then(c => c.put(e.request, copy));
        return resp;
      }))
    );
  }
});

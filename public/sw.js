/*
 * Service worker de la app instalable.
 *
 * - Estilos, scripts e imágenes (assets/): se sirven del caché y se actualizan de
 *   fondo, así la app abre rápido aunque la conexión del local sea mala.
 * - Páginas: siempre desde el servidor (tienen datos de clientes y deben estar al
 *   día). Si no hay conexión, se muestra offline.html.
 *
 * Las páginas nunca se guardan en el caché: los datos personales no quedan en el teléfono.
 * Al cambiar este archivo, subir VERSION para descartar el caché anterior.
 */
const VERSION = 'v1';
const CACHE = `gestion-${VERSION}`;
const OFFLINE = new URL('offline.html', self.registration.scope).href;
const ASSETS = new URL('assets/', self.registration.scope).href;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll([OFFLINE, new URL('assets/img/logo.png', self.registration.scope).href]))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves.filter((c) => c.startsWith('gestion-') && c !== CACHE).map((c) => caches.delete(c))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
    return;
  }

  if (request.url.startsWith(ASSETS)) {
    event.respondWith(
      caches.open(CACHE).then(async (cache) => {
        const guardada = await cache.match(request);
        const deRed = fetch(request)
          .then((respuesta) => {
            if (respuesta.ok) {
              cache.put(request, respuesta.clone());
            }
            return respuesta;
          })
          .catch(() => guardada);

        if (guardada) {
          event.waitUntil(deRed);
          return guardada;
        }
        return deRed;
      }),
    );
  }
});

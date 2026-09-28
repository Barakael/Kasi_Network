/* Kill-switch for a root-scoped console service worker that was registered
   when the console was built with base "/". It updates, clears caches, and
   unregisters so /console/ can load its own assets again. */
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(Promise.resolve());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.map((key) => caches.delete(key)));
      await self.registration.unregister();
      const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
      await Promise.all(
        windows.map((client) => {
          if ('navigate' in client) {
            return client.navigate(client.url);
          }
          return undefined;
        }),
      );
    })(),
  );
});

// Service Worker for PWA
var CACHE = 'tracker-v1';
self.addEventListener('install', function(e) { self.skipWaiting(); });
self.addEventListener('fetch', function(e) {
  // Network first strategy
  e.respondWith(fetch(e.request).catch(function() { return caches.match(e.request); }));
});

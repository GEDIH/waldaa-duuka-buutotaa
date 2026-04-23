// Service Worker for Waldaa Duuka Bu'ootaa
// Provides offline functionality, caching, and push notifications

const CACHE_NAME = 'waldaa-duuka-v1.0';
const STATIC_CACHE = 'waldaa-static-v1.0';
const DYNAMIC_CACHE = 'waldaa-dynamic-v1.0';

// Files to cache for offline functionality
const STATIC_FILES = [
  '/',
  '/index.html',
  '/images/WDB_LOGO_CIRCLE.png',
  '/images/IMG_4569.png',
  '/images/Abune-Matthias.webp',
  '/images/images.jpg',
  '/images/images 2.jpg',
  '/images/mana.jpg',
  '/images/slide3.webp',
  '/images/Kakuu_Haaraa.jpg',
  // Add more critical assets
];

// Dynamic content patterns
const DYNAMIC_PATTERNS = [
  /\/api\//,
  /\/upload\//,
  /\/gallery\//
];

// Install event - cache static files
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('Service Worker: Caching static files');
        return cache.addAll(STATIC_FILES);
      })
      .then(() => {
        console.log('Service Worker: Static files cached');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Error caching static files', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
              console.log('Service Worker: Deleting old cache', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Activated');
        return self.clients.claim();
      })
  );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Handle different types of requests
  if (request.method === 'GET') {
    if (isStaticAsset(request.url)) {
      event.respondWith(cacheFirstStrategy(request));
    } else if (isDynamicContent(request.url)) {
      event.respondWith(networkFirstStrategy(request));
    } else {
      event.respondWith(staleWhileRevalidateStrategy(request));
    }
  }
});

// Caching strategies
async function cacheFirstStrategy(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    const networkResponse = await fetch(request);
    const cache = await caches.open(STATIC_CACHE);
    cache.put(request, networkResponse.clone());
    return networkResponse;
  } catch (error) {
    console.error('Cache first strategy failed:', error);
    return getOfflineFallback(request);
  }
}

async function networkFirstStrategy(request) {
  try {
    const networkResponse = await fetch(request);
    const cache = await caches.open(DYNAMIC_CACHE);
    cache.put(request, networkResponse.clone());
    return networkResponse;
  } catch (error) {
    console.log('Network failed, trying cache:', error);
    const cachedResponse = await caches.match(request);
    return cachedResponse || getOfflineFallback(request);
  }
}

async function staleWhileRevalidateStrategy(request) {
  const cache = await caches.open(DYNAMIC_CACHE);
  const cachedResponse = await cache.match(request);
  
  const networkResponsePromise = fetch(request)
    .then(networkResponse => {
      cache.put(request, networkResponse.clone());
      return networkResponse;
    })
    .catch(() => cachedResponse);
  
  return cachedResponse || networkResponsePromise;
}

// Helper functions
function isStaticAsset(url) {
  return url.includes('/images/') || 
         url.includes('/css/') || 
         url.includes('/js/') ||
         url.endsWith('.png') ||
         url.endsWith('.jpg') ||
         url.endsWith('.webp') ||
         url.endsWith('.gif');
}

function isDynamicContent(url) {
  return DYNAMIC_PATTERNS.some(pattern => pattern.test(url));
}

function getOfflineFallback(request) {
  if (request.destination === 'document') {
    return caches.match('/offline.html') || 
           new Response('Offline - Tokkummaan interneetii hin jiru', {
             status: 200,
             headers: { 'Content-Type': 'text/html; charset=utf-8' }
           });
  }
  
  if (request.destination === 'image') {
    return new Response(
      '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' +
      '<rect width="200" height="200" fill="#f0f0f0"/>' +
      '<text x="100" y="100" text-anchor="middle" fill="#666">Offline</text>' +
      '</svg>',
      { headers: { 'Content-Type': 'image/svg+xml' } }
    );
  }
  
  return new Response('Offline', { status: 503 });
}

// Push notification handling
self.addEventListener('push', event => {
  console.log('Push notification received');
  
  const options = {
    body: event.data ? event.data.text() : 'Ergaa haaraa Waldaa Duuka Bu\'ootaa irraa',
    icon: '/images/WDB_LOGO_CIRCLE.png',
    badge: '/images/WDB_LOGO_CIRCLE.png',
    vibrate: [200, 100, 200],
    data: {
      url: '/'
    },
    actions: [
      {
        action: 'open',
        title: 'Bani',
        icon: '/images/WDB_LOGO_CIRCLE.png'
      },
      {
        action: 'close',
        title: 'Cufii'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('Waldaa Duuka Bu\'ootaa', options)
  );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'open' || !event.action) {
    event.waitUntil(
      clients.openWindow(event.notification.data.url || '/')
    );
  }
});

// Background sync for offline actions
self.addEventListener('sync', event => {
  console.log('Background sync triggered:', event.tag);
  
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

async function doBackgroundSync() {
  try {
    // Sync offline actions when connection is restored
    const offlineActions = await getOfflineActions();
    
    for (const action of offlineActions) {
      await processOfflineAction(action);
    }
    
    await clearOfflineActions();
    console.log('Background sync completed');
  } catch (error) {
    console.error('Background sync failed:', error);
  }
}

async function getOfflineActions() {
  // Retrieve offline actions from IndexedDB or localStorage
  return JSON.parse(localStorage.getItem('offlineActions') || '[]');
}

async function processOfflineAction(action) {
  // Process individual offline actions
  switch (action.type) {
    case 'contact_form':
      await submitContactForm(action.data);
      break;
    case 'feedback':
      await submitFeedback(action.data);
      break;
    case 'chat_message':
      await syncChatMessage(action.data);
      break;
  }
}

async function clearOfflineActions() {
  localStorage.removeItem('offlineActions');
}

// Periodic background sync
self.addEventListener('periodicsync', event => {
  if (event.tag === 'content-sync') {
    event.waitUntil(syncContent());
  }
});

async function syncContent() {
  try {
    // Sync latest content, events, news, etc.
    const response = await fetch('/api/sync');
    const data = await response.json();
    
    // Update cached content
    const cache = await caches.open(DYNAMIC_CACHE);
    await cache.put('/api/sync', new Response(JSON.stringify(data)));
    
    // Notify clients of new content
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
      client.postMessage({
        type: 'CONTENT_UPDATED',
        data: data
      });
    });
  } catch (error) {
    console.error('Content sync failed:', error);
  }
}

// Handle messages from main thread
self.addEventListener('message', event => {
  const { type, data } = event.data;
  
  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;
    case 'CACHE_URLS':
      cacheUrls(data.urls);
      break;
    case 'CLEAR_CACHE':
      clearCache(data.cacheName);
      break;
  }
});

async function cacheUrls(urls) {
  const cache = await caches.open(DYNAMIC_CACHE);
  await cache.addAll(urls);
}

async function clearCache(cacheName) {
  await caches.delete(cacheName || DYNAMIC_CACHE);
}

// Error handling
self.addEventListener('error', event => {
  console.error('Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
  console.error('Service Worker unhandled rejection:', event.reason);
});

console.log('Service Worker: Loaded successfully');
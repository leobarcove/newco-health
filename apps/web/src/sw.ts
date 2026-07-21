/// <reference lib="webworker" />
/**
 * The service worker — deliberately minimal (dev plan §16):
 *   1. App-shell precache
 *   2. Offline consult intake: queue + replay, with a synthetic 202 so the
 *      app can show "saved — will send when you're back online"
 *   3. Web push display + click-through
 * Resist adding caching cleverness here.
 */
import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching'
import { clientsClaim } from 'workbox-core'
import { Queue } from 'workbox-background-sync'

declare let self: ServiceWorkerGlobalScope

self.skipWaiting()
clientsClaim()

cleanupOutdatedCaches()
precacheAndRoute(self.__WB_MANIFEST)

// — Offline intake queue (business plan §6 rule 3) —
const intakeQueue = new Queue('consult-intake-queue', { maxRetentionTime: 24 * 60 })

self.addEventListener('fetch', (event) => {
  const { request } = event
  if (request.method !== 'POST' || !new URL(request.url).pathname.endsWith('/api/consults')) return

  event.respondWith(
    (async () => {
      try {
        return await fetch(request.clone())
      } catch {
        await intakeQueue.pushRequest({ request })

        return new Response(JSON.stringify({ queued_offline: true }), {
          status: 202,
          headers: { 'Content-Type': 'application/json' },
        })
      }
    })(),
  )
})

// — Web push —
self.addEventListener('push', (event) => {
  const data = (() => {
    try {
      return event.data?.json() as { title?: string; body?: string }
    } catch {
      return { body: event.data?.text() }
    }
  })()

  event.waitUntil(
    self.registration.showNotification(data?.title ?? 'NewCo Health', {
      body: data?.body ?? '',
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
    }),
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      const existing = clients.find((c) => 'focus' in c)
      return existing ? (existing as WindowClient).focus() : self.clients.openWindow('/')
    }),
  )
})

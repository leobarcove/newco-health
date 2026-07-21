import { api } from './api'

/**
 * Contextual push subscription (design plan: ask at the moment it matters —
 * "we'll notify you when the doctor is ready", not on first load).
 * Silent no-op wherever push isn't available.
 */
export async function subscribeToPush(): Promise<boolean> {
  const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY as string | undefined
  if (!vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return false

  try {
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return false

    const registration = await navigator.serviceWorker.ready
    const subscription =
      (await registration.pushManager.getSubscription()) ??
      (await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: vapidKey,
      }))

    const json = subscription.toJSON()
    await api('/push/subscribe', {
      method: 'POST',
      body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys }),
    })

    return true
  } catch {
    return false // push is an enhancement — the SMS rung always exists
  }
}

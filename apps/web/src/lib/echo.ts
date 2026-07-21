import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { getToken } from './api'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

let instance: Echo<'reverb'> | null = null

/**
 * Lazy singleton — the websocket only connects once a consult screen needs it.
 * Private-channel auth reuses the Sanctum bearer token via /api/broadcasting/auth.
 *
 * Returns null when Reverb isn't configured (VITE_REVERB_APP_KEY unset) —
 * callers fall back to polling, the same rung the ladder uses when the
 * socket can't hold on a weak network.
 */
export function echo(): Echo<'reverb'> | null {
  if (!import.meta.env.VITE_REVERB_APP_KEY) return null

  if (instance === null) {
    instance = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY as string,
      wsHost: (import.meta.env.VITE_REVERB_HOST as string) ?? window.location.hostname,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/api/broadcasting/auth',
      auth: { headers: { Authorization: `Bearer ${getToken()}` } },
    })
  }

  return instance
}

export function disconnectEcho(): void {
  instance?.disconnect()
  instance = null
}

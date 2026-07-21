import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface CallSession {
  provider: string
  room_url: string
  token: string
  modality: 'voice' | 'video'
}

/**
 * The connection-quality gate (business plan §6): the video button only
 * appears when the network can plausibly carry it; voice needs less.
 * navigator.connection is Chrome/Android — exactly our market.
 */
function connectionAllows(modality: 'voice' | 'video'): boolean {
  const connection = (navigator as Navigator & { connection?: { effectiveType?: string; downlink?: number } }).connection
  if (!connection) return true // no signal API (iOS/desktop) — let them try

  const type = connection.effectiveType ?? '4g'
  if (modality === 'video') return type === '4g' && (connection.downlink ?? 10) >= 1.5
  return type === '4g' || type === '3g'
}

/** Voice/video upgrade buttons + the in-consult call panel (the ladder's upper rungs). */
export function CallControls({ consultId, live }: { consultId: string; live: boolean }) {
  const [session, setSession] = useState<CallSession | null>(null)
  const queryClient = useQueryClient()

  const { data: features = {} } = useQuery({
    queryKey: ['features'],
    queryFn: () => api<Record<string, boolean>>('/features'),
    staleTime: 60_000,
  })

  const start = useMutation({
    mutationFn: (modality: 'voice' | 'video') =>
      api<CallSession>(`/consults/${consultId}/video-session`, { method: 'POST', body: JSON.stringify({ modality }) }),
    onSuccess: (result) => {
      setSession(result)
      void queryClient.invalidateQueries({ queryKey: ['messages', consultId] })
    },
  })

  const end = useMutation({
    mutationFn: () => api(`/consults/${consultId}/end-call`, { method: 'POST' }),
    onSuccess: () => {
      setSession(null)
      void queryClient.invalidateQueries({ queryKey: ['messages', consultId] })
    },
  })

  if (!live || !features.video_consults) return null

  if (session) {
    return (
      <div className="border-b border-slate-900/8 bg-slate-950">
        {session.provider === 'fake' ? (
          <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
            <span className="grid size-14 animate-pulse place-items-center rounded-full bg-emerald-500/20">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="size-7 text-emerald-400" aria-hidden="true">
                {session.modality === 'video'
                  ? <><path d="m16 10 6-3.5v11L16 14" /><rect x="2" y="6" width="14" height="12" rx="2" /></>
                  : <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.5 2.8.7a2 2 0 0 1 1.7 2Z" />}
              </svg>
            </span>
            <p className="text-base font-semibold text-white">
              {session.modality === 'video' ? 'Video' : 'Voice'} call running (simulated)
            </p>
            <p className="max-w-sm text-sm leading-relaxed text-slate-400">
              Locally the call room is simulated — add a <code className="text-slate-300">DAILY_API_KEY</code> and this
              panel becomes a real Daily.co call, no code changes.
            </p>
            <button onClick={() => end.mutate()} className="mt-1 min-h-11 rounded-full bg-red-600 px-6 text-sm font-semibold text-white transition hover:bg-red-700">
              End call — back to chat
            </button>
          </div>
        ) : (
          <div className="relative">
            <iframe
              title="Consult call"
              src={`${session.room_url}?t=${session.token}`}
              allow="camera; microphone; autoplay; display-capture"
              className="aspect-video w-full"
            />
            <button
              onClick={() => end.mutate()}
              className="absolute bottom-3 left-1/2 min-h-11 -translate-x-1/2 rounded-full bg-red-600 px-6 text-sm font-semibold text-white shadow-lg transition hover:bg-red-700"
            >
              End call
            </button>
          </div>
        )}
      </div>
    )
  }

  return (
    <div className="flex justify-end gap-1 border-b border-slate-900/8 bg-white px-3 py-1">
      {connectionAllows('voice') && (
        <button
          onClick={() => start.mutate('voice')}
          disabled={start.isPending}
          aria-label="Start a voice call"
          className="grid size-10 place-items-center rounded-full text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-45"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" className="size-5">
            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.5 2.8.7a2 2 0 0 1 1.7 2Z" />
          </svg>
        </button>
      )}
      {connectionAllows('video') && (
        <button
          onClick={() => start.mutate('video')}
          disabled={start.isPending}
          aria-label="Start a video call"
          className="grid size-10 place-items-center rounded-full text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-45"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" className="size-5">
            <path d="m16 10 6-3.5v11L16 14" />
            <rect x="2" y="6" width="14" height="12" rx="2" />
          </svg>
        </button>
      )}
    </div>
  )
}

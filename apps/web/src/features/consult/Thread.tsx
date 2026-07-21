import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Message } from '../../lib/api'
import { echo } from '../../lib/echo'
import { PrescriptionCard } from './PrescriptionCard'

/**
 * The consult thread — the canonical clinical surface (design plan §4.1).
 * WhatsApp-familiar. Live updates arrive over Reverb websockets; a slow
 * poll stays on as the fallback rung — on networks where the socket can't
 * hold, the thread still works (the low-bandwidth ladder, dev plan §6).
 */
export function Thread({ consultId, live }: { consultId: string; live: boolean }) {
  const [draft, setDraft] = useState('')
  const [socketUp, setSocketUp] = useState(false)
  const bottom = useRef<HTMLDivElement>(null)
  const queryClient = useQueryClient()

  const { data: messages = [] } = useQuery({
    queryKey: ['messages', consultId],
    queryFn: () => api<Message[]>(`/consults/${consultId}/messages`),
    // Socket healthy → gentle 30s safety poll; socket down → 3s fallback poll.
    refetchInterval: live ? (socketUp ? 30000 : 3000) : false,
  })

  useEffect(() => {
    if (!live) return

    const channel = echo()
      .private(`consult.${consultId}`)
      .listen('.message.sent', () => {
        // Frames carry the id only (no PHI on the wire) — refetch via REST.
        void queryClient.invalidateQueries({ queryKey: ['messages', consultId] })
      })
      .subscribed(() => setSocketUp(true))
      .error(() => setSocketUp(false))

    return () => {
      channel.stopListening('.message.sent')
      echo().leave(`consult.${consultId}`)
      setSocketUp(false)
    }
  }, [consultId, live, queryClient])

  const send = useMutation({
    mutationFn: (body: string) => api(`/consults/${consultId}/messages`, { method: 'POST', body: JSON.stringify({ body }) }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['messages', consultId] }),
  })

  useEffect(() => {
    bottom.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages.length])

  return (
    <div className="flex h-full flex-col">
      <div className="flex-1 space-y-2 overflow-y-auto p-4">
        {messages.map((m) =>
          m.kind === 'system' ? (
            <p key={m.id} className="mx-auto max-w-xs rounded-lg bg-slate-100 px-3 py-2 text-center text-sm text-slate-600">
              {m.body}
            </p>
          ) : m.kind === 'prescription' ? (
            <PrescriptionCard key={m.id} prescriptionId={m.body} />
          ) : (
            <div key={m.id} className={m.mine ? 'flex justify-end' : 'flex justify-start'}>
              <p
                className={
                  m.mine
                    ? 'max-w-[80%] rounded-2xl rounded-br-sm bg-emerald-600 px-4 py-2 text-white'
                    : 'max-w-[80%] rounded-2xl rounded-bl-sm bg-white px-4 py-2 text-slate-900 shadow-sm'
                }
              >
                {m.body}
              </p>
            </div>
          ),
        )}
        <div ref={bottom} />
      </div>

      {live && (
        <form
          className="flex gap-2 border-t border-slate-200 bg-white p-3"
          onSubmit={(e) => {
            e.preventDefault()
            const body = draft.trim()
            if (body.length === 0) return
            setDraft('')
            send.mutate(body)
          }}
        >
          <input
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            placeholder="Type your message…"
            className="min-h-12 flex-1 rounded-full border border-slate-300 px-4 text-base outline-none focus:border-emerald-600"
          />
          <button
            type="submit"
            disabled={send.isPending}
            className="min-h-12 rounded-full bg-emerald-600 px-6 text-base font-semibold text-white disabled:opacity-50"
          >
            Send
          </button>
        </form>
      )}
    </div>
  )
}

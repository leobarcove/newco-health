import { useEffect, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface Note {
  subjective: string | null
  objective: string | null
  assessment: string | null
  plan: string | null
  updated_at: string | null
}

const FIELDS: { key: keyof Omit<Note, 'updated_at'>; label: string; hint: string }[] = [
  { key: 'subjective', label: 'S — Subjective', hint: 'What the patient reports' },
  { key: 'objective', label: 'O — Objective', hint: 'Findings, vitals if reported' },
  { key: 'assessment', label: 'A — Assessment', hint: 'Working diagnosis' },
  { key: 'plan', label: 'P — Plan', hint: 'Treatment, referrals, follow-up' },
]

/** SOAP-lite clinical notes — doctor-only, saved alongside the thread (design plan §4.2). */
export function NotesPanel({ consultId }: { consultId: string }) {
  const [note, setNote] = useState<Note>({ subjective: null, objective: null, assessment: null, plan: null, updated_at: null })
  const [dirty, setDirty] = useState(false)

  const { data } = useQuery({
    queryKey: ['notes', consultId],
    queryFn: () => api<Note>(`/doctor/consults/${consultId}/notes`),
  })

  useEffect(() => {
    if (data && !dirty) setNote(data)
  }, [data, dirty])

  const save = useMutation({
    mutationFn: () =>
      api<Note>(`/doctor/consults/${consultId}/notes`, {
        method: 'PUT',
        body: JSON.stringify({
          subjective: note.subjective,
          objective: note.objective,
          assessment: note.assessment,
          plan: note.plan,
        }),
      }),
    onSuccess: () => setDirty(false),
  })

  return (
    <div className="flex flex-col gap-3 p-4">
      {FIELDS.map(({ key, label, hint }) => (
        <label key={key} className="flex flex-col gap-1">
          <span className="text-sm font-semibold text-slate-700">{label}</span>
          <textarea
            value={note[key] ?? ''}
            onChange={(e) => {
              setNote({ ...note, [key]: e.target.value })
              setDirty(true)
            }}
            rows={2}
            placeholder={hint}
            className="rounded-xl border border-slate-300/80 bg-white p-2.5 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
          />
        </label>
      ))}
      <button
        onClick={() => save.mutate()}
        disabled={save.isPending || !dirty}
        className="min-h-11 rounded-xl bg-slate-800 text-sm font-semibold text-white transition hover:bg-slate-900 disabled:opacity-40"
      >
        {save.isPending ? 'Saving…' : dirty ? 'Save notes' : 'Notes saved'}
      </button>
    </div>
  )
}

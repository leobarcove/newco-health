import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api, ApiError, type Consult } from '../../lib/api'
import { Shell, PageTitle } from '../../ui/shells'
import { btn, input } from '../../ui/primitives'

/** Red-flag screen first — emergencies must never sit in a queue (startup plan §10). */
const RED_FLAG_QUESTIONS: { key: string; label: string }[] = [
  { key: 'chest_pain', label: 'Crushing chest pain right now' },
  { key: 'severe_breathing_difficulty', label: 'Severe difficulty breathing' },
  { key: 'uncontrolled_bleeding', label: 'Bleeding that will not stop' },
  { key: 'loss_of_consciousness', label: 'Fainted or lost consciousness' },
]

interface Dependant {
  id: string
  name: string
  relationship: string
}

const chip = (selected: boolean) =>
  `min-h-11 rounded-full px-4 text-[15px] font-medium transition ${
    selected
      ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/25'
      : 'border border-slate-300/80 bg-white text-slate-700 hover:border-emerald-500/60'
  }`

export function IntakePage() {
  const [complaint, setComplaint] = useState('')
  const [flags, setFlags] = useState<Record<string, boolean>>({})
  const [consentNeeded, setConsentNeeded] = useState(false)
  const [forDependant, setForDependant] = useState<string | null>(null)
  const navigate = useNavigate()

  const { data: dependants = [] } = useQuery({
    queryKey: ['dependants'],
    queryFn: () => api<Dependant[]>('/dependants'),
  })

  const [queuedOffline, setQueuedOffline] = useState(false)

  const start = useMutation({
    mutationFn: () =>
      api<Consult & { queued_offline?: boolean }>('/consults', {
        method: 'POST',
        body: JSON.stringify({ complaint, answers: flags, dependant_id: forDependant }),
      }),
    onSuccess: (consult) => {
      // The service worker queued it — no signal (business plan §6 rule 3).
      if (consult.queued_offline) {
        setQueuedOffline(true)
        return
      }
      navigate(`/consult/${consult.id}`, { replace: true })
    },
    onError: (e) => {
      if (e instanceof ApiError && e.status === 428) setConsentNeeded(true)
    },
  })

  if (queuedOffline) {
    return (
      <Shell back="/">
        <div className="flex flex-col items-center gap-4 rounded-3xl border border-emerald-600/25 bg-emerald-50/70 px-6 py-12 text-center">
          <span className="grid size-16 place-items-center rounded-2xl bg-emerald-600/10">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="size-8 text-emerald-700" aria-hidden="true">
              <path d="M5 13l4 4L19 7" />
            </svg>
          </span>
          <p className="text-lg font-semibold text-slate-900">Saved — you're offline right now</p>
          <p className="max-w-xs text-[15px] leading-relaxed text-slate-600">
            We'll send your consult request the moment your connection returns. You don't need to do anything.
          </p>
        </div>
      </Shell>
    )
  }

  const consent = useMutation({
    mutationFn: () =>
      api('/consents', {
        method: 'POST',
        body: JSON.stringify({ kind: 'telemedicine_terms', granted: true }),
      }),
    onSuccess: () => {
      setConsentNeeded(false)
      start.mutate()
    },
  })

  return (
    <Shell back="/">
      <PageTitle sub="A doctor reads this before they join — the more detail, the faster they can help.">
        Tell us what's wrong
      </PageTitle>

      {dependants.length > 0 && (
        <fieldset>
          <legend className="mb-3 text-[15px] font-semibold text-slate-900">Who is this consult for?</legend>
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={() => setForDependant(null)} className={chip(forDependant === null)}>
              Myself
            </button>
            {dependants.map((d) => (
              <button key={d.id} type="button" onClick={() => setForDependant(d.id)} className={chip(forDependant === d.id)}>
                {d.name}
              </button>
            ))}
          </div>
        </fieldset>
      )}

      <label className="flex flex-col gap-3">
        <span className="text-[15px] font-semibold text-slate-900">Describe how you're feeling</span>
        <textarea
          autoFocus
          value={complaint}
          onChange={(e) => setComplaint(e.target.value)}
          rows={4}
          placeholder="e.g. Fever and headache since Monday…"
          className={`${input} min-h-28 py-3.5`}
        />
      </label>

      <fieldset>
        <legend className="mb-3 text-[15px] font-semibold text-slate-900">
          Tick any that apply <span className="font-normal text-slate-400">(this helps us act fast)</span>
        </legend>
        <div className="flex flex-col gap-2">
          {RED_FLAG_QUESTIONS.map((q) => (
            <label
              key={q.key}
              className={`flex min-h-13 cursor-pointer items-center gap-3.5 rounded-2xl border bg-white px-4 shadow-xs transition ${
                flags[q.key] ? 'border-red-400 bg-red-50/60' : 'border-slate-900/8 hover:border-slate-300'
              }`}
            >
              <input
                type="checkbox"
                checked={flags[q.key] ?? false}
                onChange={(e) => setFlags({ ...flags, [q.key]: e.target.checked })}
                className="size-5 accent-red-600"
              />
              <span className="text-[15px] text-slate-800">{q.label}</span>
            </label>
          ))}
        </div>
      </fieldset>

      <button onClick={() => start.mutate()} disabled={start.isPending || complaint.trim().length < 5} className={btn.primary}>
        {start.isPending ? 'Starting…' : 'See a doctor'}
      </button>

      {consentNeeded && (
        <div className="flex flex-col gap-3 rounded-3xl border border-emerald-600/25 bg-emerald-50/70 p-5">
          <p className="text-base font-semibold text-slate-900">One thing before you see a doctor</p>
          <p className="text-[15px] leading-relaxed text-slate-700">
            Online consults have limits — for emergencies, go to a hospital. Your health information stays private
            and is only shared with the doctor treating you. By continuing you agree to our telemedicine terms.
          </p>
          <button onClick={() => consent.mutate()} disabled={consent.isPending} className={btn.primary}>
            {consent.isPending ? 'Saving…' : 'I agree — continue'}
          </button>
        </div>
      )}

      {start.isError && !consentNeeded && (
        <p className="rounded-2xl bg-red-50 p-4 text-[15px] text-red-800">
          We couldn't start your consult. You haven't been charged — please try again.
        </p>
      )}
    </Shell>
  )
}

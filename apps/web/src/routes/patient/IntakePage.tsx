import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api, ApiError, type Consult } from '../../lib/api'

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

  const start = useMutation({
    mutationFn: () =>
      api<Consult>('/consults', {
        method: 'POST',
        body: JSON.stringify({ complaint, answers: flags, dependant_id: forDependant }),
      }),
    onSuccess: (consult) => navigate(`/consult/${consult.id}`, { replace: true }),
    onError: (e) => {
      if (e instanceof ApiError && e.status === 428) setConsentNeeded(true)
    },
  })

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
    <main className="mx-auto flex min-h-dvh max-w-md flex-col gap-6 p-6">
      <h1 className="text-2xl font-bold text-slate-900">Tell us what's wrong</h1>

      {dependants.length > 0 && (
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-base font-medium text-slate-700">Who is this consult for?</legend>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => setForDependant(null)}
              className={`min-h-11 rounded-full border px-4 text-base ${
                forDependant === null ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-700'
              }`}
            >
              Myself
            </button>
            {dependants.map((d) => (
              <button
                key={d.id}
                type="button"
                onClick={() => setForDependant(d.id)}
                className={`min-h-11 rounded-full border px-4 text-base ${
                  forDependant === d.id ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-700'
                }`}
              >
                {d.name}
              </button>
            ))}
          </div>
        </fieldset>
      )}

      <label className="flex flex-col gap-2">
        <span className="text-base font-medium text-slate-700">Describe how you're feeling</span>
        <textarea
          autoFocus
          value={complaint}
          onChange={(e) => setComplaint(e.target.value)}
          rows={4}
          placeholder="e.g. Fever and headache since Monday…"
          className="rounded-2xl border border-slate-300/80 p-4 text-base outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
        />
      </label>

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-base font-medium text-slate-700">
          Tick any that apply <span className="text-slate-500">(this helps us act fast)</span>
        </legend>
        {RED_FLAG_QUESTIONS.map((q) => (
          <label key={q.key} className="flex min-h-12 items-center gap-3 rounded-xl border border-slate-200 px-4">
            <input
              type="checkbox"
              checked={flags[q.key] ?? false}
              onChange={(e) => setFlags({ ...flags, [q.key]: e.target.checked })}
              className="size-5 accent-emerald-600"
            />
            <span className="text-base text-slate-800">{q.label}</span>
          </label>
        ))}
      </fieldset>

      <button
        onClick={() => start.mutate()}
        disabled={start.isPending || complaint.trim().length < 5}
        className="min-h-13 rounded-2xl bg-emerald-600 text-base font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
      >
        {start.isPending ? 'Starting…' : 'See a doctor'}
      </button>

      {consentNeeded && (
        <div className="flex flex-col gap-3 rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4">
          <p className="text-base font-semibold text-slate-900">One thing before you see a doctor</p>
          <p className="text-base text-slate-700">
            Online consults have limits — for emergencies, go to a hospital. Your health information stays private
            and is only shared with the doctor treating you. By continuing you agree to our telemedicine terms.
          </p>
          <button
            onClick={() => consent.mutate()}
            disabled={consent.isPending}
            className="min-h-12 rounded-xl bg-emerald-600 text-base font-semibold text-white disabled:opacity-50"
          >
            {consent.isPending ? 'Saving…' : 'I agree — continue'}
          </button>
        </div>
      )}

      {start.isError && !consentNeeded && (
        <p className="rounded-2xl bg-red-50 p-4 text-base text-red-700">
          We couldn't start your consult. You haven't been charged — please try again.
        </p>
      )}
    </main>
  )
}

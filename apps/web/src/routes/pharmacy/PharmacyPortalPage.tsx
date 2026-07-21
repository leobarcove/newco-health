import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useMutation } from '@tanstack/react-query'
import { api, getToken, setToken } from '../../lib/api'

interface LookupResult {
  pickup_code: string
  status: 'issued' | 'dispensed' | 'cancelled'
  patient_first_name: string
  doctor_name: string
  mdcn_licence_no: string
  items: { medicine: string; dosage: string; duration_days: number; instructions: string | null }[]
}

/** The counter flow: code in → verify medicines → dispense. Designed for speed. */
export function PharmacyPortalPage() {
  const [code, setCode] = useState('')
  const [result, setResult] = useState<LookupResult | null>(null)
  const navigate = useNavigate()

  const lookup = useMutation({
    mutationFn: (c: string) => api<LookupResult>(`/pharmacy/prescriptions/${encodeURIComponent(c)}`),
    onSuccess: setResult,
  })

  const dispense = useMutation({
    mutationFn: () => api<LookupResult>('/pharmacy/dispense', { method: 'POST', body: JSON.stringify({ pickup_code: result?.pickup_code }) }),
    onSuccess: setResult,
  })

  if (getToken() === null) {
    navigate('/pharmacy/login', { replace: true })
    return null
  }

  return (
    <main className="mx-auto flex min-h-dvh max-w-lg flex-col gap-5 p-6">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">Dispense a prescription</h1>
        <button onClick={() => { setToken(null); navigate('/pharmacy/login') }} className="text-base text-slate-500 underline">
          Sign out
        </button>
      </header>

      <form
        className="flex gap-2"
        onSubmit={(e) => {
          e.preventDefault()
          setResult(null)
          if (code.trim()) lookup.mutate(code.trim().toUpperCase())
        }}
      >
        <input
          value={code}
          onChange={(e) => setCode(e.target.value.toUpperCase())}
          placeholder="RX-XXXXXXXX"
          autoFocus
          className="min-h-14 flex-1 rounded-2xl border border-slate-300/80 px-4 font-mono text-lg tracking-widest outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
        />
        <button
          type="submit"
          disabled={lookup.isPending}
          className="min-h-14 rounded-xl bg-emerald-600 px-6 text-lg font-semibold text-white disabled:opacity-50"
        >
          Look up
        </button>
      </form>

      {lookup.isError && (
        <p className="rounded-2xl bg-red-50 p-4 text-base text-red-700">
          No active prescription with that code. Check the code with the patient — it may already be collected.
        </p>
      )}

      {result && (
        <section className={`rounded-xl border-2 p-4 ${result.status === 'issued' ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50'}`}>
          <div className="mb-3 flex items-center justify-between">
            <div>
              <p className="text-lg font-semibold text-slate-900">For {result.patient_first_name}</p>
              <p className="text-sm text-slate-600">
                Dr {result.doctor_name} · {result.mdcn_licence_no}
              </p>
            </div>
            <span className={`rounded-full px-3 py-1 text-sm font-semibold capitalize ${result.status === 'issued' ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700'}`}>
              {result.status}
            </span>
          </div>

          <ul className="mb-4 space-y-2">
            {result.items.map((item, i) => (
              <li key={i} className="rounded-lg bg-white p-3">
                <p className="text-base font-medium text-slate-900">{item.medicine}</p>
                <p className="text-sm text-slate-600">{item.dosage} · {item.duration_days} days</p>
                {item.instructions && <p className="mt-1 text-sm text-slate-500">{item.instructions}</p>}
              </li>
            ))}
          </ul>

          {result.status === 'issued' ? (
            <button
              onClick={() => dispense.mutate()}
              disabled={dispense.isPending}
              className="min-h-13 w-full rounded-2xl bg-emerald-600 text-base font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
            >
              {dispense.isPending ? 'Recording…' : 'Mark as dispensed'}
            </button>
          ) : (
            <p className="text-center text-base font-medium text-slate-600">
              {result.status === 'dispensed' ? '✓ Collected — nothing more to do' : 'This prescription is not dispensable'}
            </p>
          )}
        </section>
      )}
    </main>
  )
}

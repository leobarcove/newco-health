import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useMutation } from '@tanstack/react-query'
import { api, getToken, setToken } from '../../lib/api'
import { Logo } from '../../ui/Logo'
import { Badge, Card } from '../../ui/primitives'

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
    <div className="mx-auto flex min-h-dvh w-full max-w-lg flex-col px-5">
      <header className="flex min-h-16 items-center justify-between pt-2">
        <Logo size="sm" />
        <button
          onClick={() => { setToken(null); navigate('/pharmacy/login') }}
          className="text-sm font-medium text-slate-400 transition hover:text-slate-700"
        >
          Sign out
        </button>
      </header>

      <main className="flex flex-1 flex-col gap-5 pb-10 pt-2">
        <div>
          <h1 className="text-[1.7rem] font-bold leading-tight tracking-tight text-slate-900">Dispense a prescription</h1>
          <p className="mt-1 text-[15px] text-slate-500">Ask the patient for their pickup code.</p>
        </div>

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
            className="min-h-14 w-full flex-1 rounded-2xl border border-slate-300/80 bg-white px-4 font-mono text-lg tracking-[0.2em] text-slate-900 outline-none transition placeholder:text-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
          />
          <button
            type="submit"
            disabled={lookup.isPending}
            className="min-h-14 shrink-0 rounded-2xl bg-emerald-600 px-6 text-base font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
          >
            Look up
          </button>
        </form>

        {lookup.isError && (
          <p className="rounded-2xl bg-red-50 p-4 text-[15px] leading-relaxed text-red-800">
            No active prescription with that code. Check it with the patient — it may already be collected.
          </p>
        )}

        {result && (
          <Card className={result.status === 'issued' ? 'border-emerald-600/30' : ''}>
            <div className="mb-4 flex items-start justify-between gap-3">
              <div>
                <p className="text-lg font-semibold text-slate-900">For {result.patient_first_name}</p>
                <p className="mt-0.5 text-sm text-slate-500">
                  Dr {result.doctor_name} · {result.mdcn_licence_no}
                </p>
              </div>
              <Badge tone={result.status === 'issued' ? 'brand' : 'neutral'}>{result.status}</Badge>
            </div>

            <ul className="mb-5 divide-y divide-slate-900/6 rounded-2xl border border-slate-900/8">
              {result.items.map((item, i) => (
                <li key={i} className="px-4 py-3">
                  <p className="text-[15px] font-semibold text-slate-900">{item.medicine}</p>
                  <p className="mt-0.5 text-sm text-slate-600">
                    {item.dosage} · {item.duration_days} days
                  </p>
                  {item.instructions && <p className="mt-1 text-sm text-slate-400">{item.instructions}</p>}
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
              <p className="text-center text-[15px] font-medium text-slate-500">
                {result.status === 'dispensed' ? '✓ Collected — nothing more to do' : 'This prescription is not dispensable'}
              </p>
            )}
          </Card>
        )}
      </main>
    </div>
  )
}

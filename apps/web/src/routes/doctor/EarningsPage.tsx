import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface Earnings {
  pending_display: string
  paid_display: string
  recent: { consult_id: string; amount_kobo: number; status: 'pending' | 'paid'; earned_at: string }[]
}

const WHEN = new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Lagos' })

/** The doctor retention screen (design plan §4.2): earnings always one click away. */
export function EarningsPage() {
  const { data } = useQuery({
    queryKey: ['earnings'],
    queryFn: () => api<Earnings>('/doctor/earnings'),
  })

  return (
    <main className="mx-auto flex min-h-dvh max-w-2xl flex-col gap-4 p-6">
      <header className="flex items-center gap-3">
        <Link to="/doctor" className="text-2xl text-slate-500" aria-label="Back">‹</Link>
        <h1 className="text-2xl font-bold text-slate-900">Your earnings</h1>
      </header>

      <div className="grid grid-cols-2 gap-3">
        <div className="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4">
          <p className="text-sm uppercase tracking-wide text-emerald-800">Awaiting payout</p>
          <p className="text-3xl font-bold text-slate-900">{data?.pending_display ?? '—'}</p>
          <p className="mt-1 text-sm text-slate-600">Paid out weekly to your bank</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4">
          <p className="text-sm uppercase tracking-wide text-slate-500">Paid to date</p>
          <p className="text-3xl font-bold text-slate-900">{data?.paid_display ?? '—'}</p>
        </div>
      </div>

      <section>
        <h2 className="mb-2 text-base font-medium text-slate-700">Recent consults</h2>
        {data?.recent.length === 0 && (
          <p className="rounded-xl bg-slate-100 p-5 text-base text-slate-600">
            Earnings appear here as soon as you conclude a paid consult.
          </p>
        )}
        <ul className="flex flex-col gap-2">
          {data?.recent.map((e) => (
            <li key={e.consult_id} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
              <div>
                <p className="text-base font-semibold text-slate-900">₦{(e.amount_kobo / 100).toLocaleString()}</p>
                <p className="text-sm text-slate-500">{WHEN.format(new Date(e.earned_at))}</p>
              </div>
              <span className={`rounded-full px-3 py-1 text-sm ${e.status === 'paid' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-800'}`}>
                {e.status === 'paid' ? 'Paid' : 'Pending'}
              </span>
            </li>
          ))}
        </ul>
      </section>
    </main>
  )
}

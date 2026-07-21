import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { ConsoleShell, PageTitle } from '../../ui/shells'
import { Badge, Card, EmptyState } from '../../ui/primitives'

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
    <ConsoleShell active="earnings">
      <div className="mb-6">
        <PageTitle sub="You earn 65% of every paid consult. Payouts go to your bank every week.">Your earnings</PageTitle>
      </div>

      <div className="mb-8 grid grid-cols-2 gap-4">
        <div className="rounded-3xl bg-emerald-700 p-5 text-white shadow-lg shadow-emerald-700/25">
          <p className="text-xs font-semibold uppercase tracking-widest text-emerald-200">Awaiting payout</p>
          <p className="mt-2 text-3xl font-bold tabular-nums">{data?.pending_display ?? '—'}</p>
          <p className="mt-1 text-sm text-emerald-100/90">Next payout: this week</p>
        </div>
        <Card>
          <p className="text-xs font-semibold uppercase tracking-widest text-slate-400">Paid to date</p>
          <p className="mt-2 text-3xl font-bold tabular-nums text-slate-900">{data?.paid_display ?? '—'}</p>
          <p className="mt-1 text-sm text-slate-500">Since you joined</p>
        </Card>
      </div>

      <h2 className="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-400">Recent consults</h2>

      {data?.recent.length === 0 ? (
        <EmptyState
          icon="wallet"
          title="No earnings yet"
          hint="Your share lands here the moment you conclude a paid consult — go online and accept your first patient."
        />
      ) : (
        <Card className="divide-y divide-slate-900/6 p-0">
          {data?.recent.map((e) => (
            <div key={e.consult_id} className="flex items-center justify-between px-5 py-4">
              <div>
                <p className="text-base font-semibold tabular-nums text-slate-900">
                  ₦{(e.amount_kobo / 100).toLocaleString()}
                </p>
                <p className="mt-0.5 text-sm text-slate-500">{WHEN.format(new Date(e.earned_at))}</p>
              </div>
              <Badge tone={e.status === 'paid' ? 'neutral' : 'warning'}>{e.status === 'paid' ? 'Paid' : 'Pending'}</Badge>
            </div>
          ))}
        </Card>
      )}
    </ConsoleShell>
  )
}

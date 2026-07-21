import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'
import { Thread } from '../../features/consult/Thread'

const LIVE_STATES = ['queued', 'assigned', 'in_consult', 'concluded']

export function ConsultPage() {
  const { id = '' } = useParams()

  const { data: consult } = useQuery({
    queryKey: ['consult', id],
    queryFn: () => api<Consult>(`/consults/${id}`),
    refetchInterval: (q) => (q.state.data && q.state.data.state === 'queued' ? 5000 : false),
  })

  if (!consult) {
    return (
      <main className="flex min-h-dvh items-center justify-center">
        <div className="h-24 w-64 animate-pulse rounded-2xl bg-slate-100" />
      </main>
    )
  }

  return (
    <main className="mx-auto flex h-dvh max-w-md flex-col bg-slate-50">
      <header className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
        <Link to="/" className="text-2xl text-slate-500" aria-label="Back to home">
          ‹
        </Link>
        <div>
          <h1 className="text-base font-semibold text-slate-900">
            {consult.doctor ? `Dr ${consult.doctor.name}` : 'Your consult'}
          </h1>
          <StateBanner consult={consult} />
        </div>
      </header>

      <div className="min-h-0 flex-1">
        <Thread consultId={consult.id} live={LIVE_STATES.includes(consult.state)} />
      </div>
    </main>
  )
}

function StateBanner({ consult }: { consult: Consult }) {
  switch (consult.state) {
    case 'queued':
      return (
        <p className="text-sm text-amber-700">
          {consult.queue_position === 1
            ? "You're next — a doctor will join shortly."
            : `You're number ${consult.queue_position ?? '…'} in the queue. We'll notify you.`}
        </p>
      )
    case 'in_consult':
      return <p className="text-sm text-emerald-700">Doctor is with you now</p>
    case 'concluded':
      return <p className="text-sm text-slate-500">Consult ended — you can reply for 72 hours</p>
    case 'escalated_emergency':
      return <p className="text-sm font-semibold text-red-700">Please go to the nearest hospital now</p>
    default:
      return null
  }
}

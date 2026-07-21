import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'
import { Thread } from '../../features/consult/Thread'

const LIVE_STATES = ['queued', 'assigned', 'in_consult', 'concluded']

interface PaymentResult {
  status: string
  amount_display: string
  checkout_url: string | null
}

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

      {consult.state === 'triaged' && <PayPanel consultId={consult.id} />}

      <div className="min-h-0 flex-1">
        <Thread consultId={consult.id} live={LIVE_STATES.includes(consult.state)} />
      </div>
    </main>
  )
}

/** Payment gate: price upfront, no surprises (design plan §6 money rules). */
function PayPanel({ consultId }: { consultId: string }) {
  const queryClient = useQueryClient()

  const pay = useMutation({
    mutationFn: () => api<PaymentResult>(`/consults/${consultId}/pay`, { method: 'POST' }),
    onSuccess: (payment) => {
      if (payment.checkout_url) {
        window.location.assign(payment.checkout_url) // real gateway redirect
      } else {
        void queryClient.invalidateQueries({ queryKey: ['consult', consultId] })
        void queryClient.invalidateQueries({ queryKey: ['messages', consultId] })
      }
    },
  })

  return (
    <div className="border-b border-amber-200 bg-amber-50 p-4">
      <p className="mb-2 text-base text-amber-900">
        Consult fee: <strong>₦2,500</strong> — pay by card, transfer, or USSD. You only pay once per consult.
      </p>
      <button
        onClick={() => pay.mutate()}
        disabled={pay.isPending}
        className="min-h-12 w-full rounded-xl bg-emerald-600 text-base font-semibold text-white disabled:opacity-50"
      >
        {pay.isPending ? 'Starting payment…' : 'Pay and join the queue'}
      </button>
      {pay.isError && (
        <p className="mt-2 text-sm text-red-700">Payment didn't start. You haven't been charged — try again.</p>
      )}
    </div>
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

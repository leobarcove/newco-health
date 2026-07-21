import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'
import { Thread } from '../../features/consult/Thread'
import { Avatar } from '../../ui/primitives'

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
      <main className="mx-auto flex min-h-dvh max-w-md items-center justify-center px-5">
        <div className="h-28 w-full animate-pulse rounded-3xl bg-slate-900/5" />
      </main>
    )
  }

  const doctorName = consult.doctor ? `Dr ${consult.doctor.name}` : 'Your consult'

  return (
    <main className="mx-auto flex h-dvh w-full max-w-md flex-col">
      <header className="flex items-center gap-2.5 border-b border-slate-900/8 bg-white px-4 py-3">
        <Link
          to="/"
          aria-label="Back to home"
          className="-ml-1 grid size-10 shrink-0 place-items-center rounded-full text-slate-500 transition hover:bg-slate-900/5 hover:text-slate-900"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" className="size-5">
            <path d="m15 18-6-6 6-6" />
          </svg>
        </Link>
        {consult.doctor ? (
          <Avatar name={consult.doctor.name} size="sm" />
        ) : (
          <span className="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-600/10">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="size-5 text-emerald-700" aria-hidden="true">
              <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
            </svg>
          </span>
        )}
        <div className="min-w-0">
          <h1 className="truncate text-base font-semibold text-slate-900">
            {consult.for_dependant ? `${doctorName} · for ${consult.for_dependant.name}` : doctorName}
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

function StateBanner({ consult }: { consult: Consult }) {
  switch (consult.state) {
    case 'queued':
      return (
        <p className="flex items-center gap-1.5 text-[13px] font-medium text-amber-700">
          <span className="size-1.5 animate-pulse rounded-full bg-amber-500" aria-hidden="true" />
          {consult.queue_position === 1
            ? "You're next — a doctor will join shortly"
            : `You're number ${consult.queue_position ?? '…'} in the queue`}
        </p>
      )
    case 'in_consult':
      return (
        <p className="flex items-center gap-1.5 text-[13px] font-medium text-emerald-700">
          <span className="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
          With you now
        </p>
      )
    case 'concluded':
      return <p className="text-[13px] text-slate-500">Ended — you can reply for 72 hours</p>
    case 'escalated_emergency':
      return <p className="text-[13px] font-semibold text-red-700">Please go to the nearest hospital now</p>
    default:
      return null
  }
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
    <div className="border-b border-amber-200/70 bg-amber-50 p-4">
      <p className="mb-3 text-[15px] leading-relaxed text-amber-900">
        Consult fee: <strong>₦2,500</strong> — card, transfer, or USSD. You only pay once per consult.
      </p>
      <button
        onClick={() => pay.mutate()}
        disabled={pay.isPending}
        className="min-h-12 w-full rounded-2xl bg-emerald-600 text-[15px] font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
      >
        {pay.isPending ? 'Starting payment…' : 'Pay and join the queue'}
      </button>
      {pay.isError && (
        <p className="mt-2 text-sm text-red-700">Payment didn't start. You haven't been charged — try again.</p>
      )}
    </div>
  )
}

import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { Shell, PageTitle } from '../../ui/shells'
import { Avatar, Badge, Card, EmptyState } from '../../ui/primitives'

interface Booking {
  id: string
  state: 'pending_payment' | 'confirmed' | 'completed' | 'cancelled' | 'no_show'
  starts_at: string
  ends_at: string
  doctor: { id: string; name: string }
  consult_id: string | null
}

const WHEN = new Intl.DateTimeFormat('en-NG', {
  weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Lagos',
})

export function AppointmentsPage() {
  const queryClient = useQueryClient()

  const { data: bookings = [] } = useQuery({
    queryKey: ['bookings'],
    queryFn: () => api<Booking[]>('/bookings'),
  })

  const cancel = useMutation({
    mutationFn: (id: string) => api(`/bookings/${id}/cancel`, { method: 'POST' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['bookings'] }),
  })

  const pay = useMutation({
    mutationFn: (id: string) => api<{ checkout_url: string | null }>(`/bookings/${id}/pay`, { method: 'POST' }),
    onSuccess: (payment) => {
      if (payment.checkout_url) window.location.assign(payment.checkout_url)
      else void queryClient.invalidateQueries({ queryKey: ['bookings'] })
    },
  })

  const upcoming = bookings.filter((b) => b.state === 'confirmed' || b.state === 'pending_payment')
  const past = bookings.filter((b) => b.state !== 'confirmed' && b.state !== 'pending_payment')

  return (
    <Shell back="/">
      <PageTitle>Your appointments</PageTitle>

      {upcoming.length === 0 && (
        <EmptyState
          icon="calendar"
          title="No upcoming appointments"
          hint="Book a time with a doctor that suits your day — mornings, evenings, whatever works."
          action={
            <Link to="/book" className="mt-1 inline-flex min-h-11 items-center rounded-2xl bg-emerald-600 px-5 text-[15px] font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700">
              Book one now
            </Link>
          }
        />
      )}

      {upcoming.map((b) => (
        <Card key={b.id} className={b.state === 'pending_payment' ? 'border-amber-300/70' : 'border-emerald-600/25'}>
          <div className="flex items-center gap-3.5">
            <Avatar name={b.doctor.name} size="md" />
            <div className="flex-1">
              <p className="text-base font-semibold text-slate-900">Dr {b.doctor.name}</p>
              <p className="mt-0.5 text-[15px] text-slate-600">{WHEN.format(new Date(b.starts_at))}</p>
            </div>
            <Badge tone={b.state === 'pending_payment' ? 'warning' : 'success'}>
              {b.state === 'pending_payment' ? 'Unpaid' : 'Confirmed'}
            </Badge>
          </div>

          {b.state === 'pending_payment' && (
            <button
              onClick={() => pay.mutate(b.id)}
              disabled={pay.isPending}
              className="mt-4 min-h-12 w-full rounded-2xl bg-amber-500 text-[15px] font-semibold text-white shadow-sm shadow-amber-500/30 transition hover:bg-amber-600 disabled:opacity-45"
            >
              Pay ₦2,500 to confirm — slot held 15 minutes
            </button>
          )}

          <div className="mt-4 flex gap-2">
            {b.consult_id && (
              <Link
                to={`/consult/${b.consult_id}`}
                className="flex min-h-12 flex-1 items-center justify-center rounded-2xl bg-emerald-600 text-[15px] font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700"
              >
                Join consult
              </Link>
            )}
            <button
              onClick={() => cancel.mutate(b.id)}
              disabled={cancel.isPending}
              className="min-h-12 flex-1 rounded-2xl border border-slate-300/80 bg-white text-[15px] font-medium text-slate-600 transition hover:bg-slate-50"
            >
              Cancel
            </button>
          </div>
          {cancel.isError && (
            <p className="mt-3 text-sm text-red-700">
              Appointments can only be cancelled up to 2 hours before the start time.
            </p>
          )}
        </Card>
      ))}

      {past.length > 0 && (
        <section>
          <h2 className="mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Past</h2>
          <Card className="divide-y divide-slate-900/6 p-0">
            {past.map((b) => (
              <div key={b.id} className="flex items-center justify-between px-5 py-4">
                <div>
                  <p className="text-[15px] font-medium text-slate-900">Dr {b.doctor.name}</p>
                  <p className="mt-0.5 text-sm text-slate-500">{WHEN.format(new Date(b.starts_at))}</p>
                </div>
                <span className="text-sm capitalize text-slate-400">{b.state.replace('_', ' ')}</span>
              </div>
            ))}
          </Card>
        </section>
      )}
    </Shell>
  )
}

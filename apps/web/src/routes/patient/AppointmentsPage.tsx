import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'

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
    <main className="mx-auto flex min-h-dvh max-w-md flex-col gap-6 p-6">
      <header className="flex items-center gap-3">
        <Link to="/" className="text-2xl text-slate-500" aria-label="Back">‹</Link>
        <h1 className="text-2xl font-bold text-slate-900">Your appointments</h1>
      </header>

      {upcoming.length === 0 && (
        <div className="flex flex-col gap-3 rounded-xl bg-slate-100 p-6 text-center">
          <p className="text-base text-slate-600">No upcoming appointments.</p>
          <Link to="/book" className="text-base font-semibold text-emerald-700 underline">Book one now</Link>
        </div>
      )}

      {upcoming.map((b) => (
        <div key={b.id} className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
          <p className="text-base font-semibold text-slate-900">Dr {b.doctor.name}</p>
          <p className="mb-3 text-base text-slate-700">{WHEN.format(new Date(b.starts_at))}</p>
          {b.state === 'pending_payment' && (
            <button
              onClick={() => pay.mutate(b.id)}
              disabled={pay.isPending}
              className="mb-2 min-h-11 w-full rounded-lg bg-amber-500 text-base font-semibold text-white disabled:opacity-50"
            >
              Pay ₦2,500 to confirm — slot held 15 minutes
            </button>
          )}
          <div className="flex gap-2">
            {b.consult_id && (
              <Link
                to={`/consult/${b.consult_id}`}
                className="flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald-600 text-base font-semibold text-white"
              >
                Join consult
              </Link>
            )}
            <button
              onClick={() => cancel.mutate(b.id)}
              disabled={cancel.isPending}
              className="min-h-11 flex-1 rounded-lg border border-slate-300 bg-white text-base text-slate-700"
            >
              Cancel
            </button>
          </div>
          {cancel.isError && (
            <p className="mt-2 text-sm text-red-700">
              Appointments can only be cancelled up to 2 hours before the start time.
            </p>
          )}
        </div>
      ))}

      {past.length > 0 && (
        <section>
          <h2 className="mb-2 text-base font-medium text-slate-500">Past</h2>
          <ul className="flex flex-col gap-2">
            {past.map((b) => (
              <li key={b.id} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
                <div>
                  <p className="text-base text-slate-900">Dr {b.doctor.name}</p>
                  <p className="text-sm text-slate-500">{WHEN.format(new Date(b.starts_at))}</p>
                </div>
                <span className="text-sm capitalize text-slate-500">{b.state.replace('_', ' ')}</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </main>
  )
}

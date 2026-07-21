import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'

const OPEN_STATES = ['queued', 'assigned', 'in_consult', 'concluded']

/** Patient home: one primary action (design plan §2.2 — no dashboards). */
export function HomePage() {
  const { data: consults = [] } = useQuery({
    queryKey: ['consults'],
    queryFn: () => api<Consult[]>('/consults'),
  })

  const open = consults.find((c) => OPEN_STATES.includes(c.state))

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
      <h1 className="text-2xl font-bold text-slate-900">How can we help today?</h1>

      {open ? (
        <Link
          to={`/consult/${open.id}`}
          className="flex min-h-14 items-center justify-center rounded-xl bg-emerald-600 px-6 text-lg font-semibold text-white"
        >
          Continue your consult
        </Link>
      ) : (
        <Link
          to="/intake"
          className="flex min-h-14 items-center justify-center rounded-xl bg-emerald-600 px-6 text-lg font-semibold text-white"
        >
          Talk to a doctor now
        </Link>
      )}

      <Link
        to="/book"
        className="flex min-h-14 items-center justify-center rounded-xl border-2 border-emerald-600 px-6 text-lg font-semibold text-emerald-700"
      >
        Book an appointment
      </Link>

      <Link to="/appointments" className="text-center text-base text-slate-500 underline">
        Your appointments
      </Link>

      <p className="text-center text-sm text-slate-500">
        Licensed Nigerian doctors · Available every day · Fair, upfront prices
      </p>
    </main>
  )
}

import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'

const OPEN_STATES = ['queued', 'assigned', 'in_consult', 'concluded']

interface SponsorshipInvite {
  id: string
  sponsor_name: string
  status: string
}

/** Patient home: one primary action (design plan §2.2 — no dashboards). */
export function HomePage() {
  const queryClient = useQueryClient()

  const { data: consults = [] } = useQuery({
    queryKey: ['consults'],
    queryFn: () => api<Consult[]>('/consults'),
  })

  const { data: sponsorships = [] } = useQuery({
    queryKey: ['sponsorships'],
    queryFn: () => api<SponsorshipInvite[]>('/sponsorships'),
  })

  const respond = useMutation({
    mutationFn: ({ id, accept }: { id: string; accept: boolean }) =>
      api(`/sponsorships/${id}/respond`, { method: 'POST', body: JSON.stringify({ accept }) }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['sponsorships'] }),
  })

  const open = consults.find((c) => OPEN_STATES.includes(c.state))
  const pendingInvite = sponsorships.find((s) => s.status === 'pending')

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
      <h1 className="text-2xl font-bold text-slate-900">How can we help today?</h1>

      {pendingInvite && (
        <div className="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4">
          <p className="mb-3 text-base text-slate-800">
            <strong>{pendingInvite.sponsor_name}</strong> wants to sponsor your healthcare — consults would be paid
            for you. They'll only see your care details if you choose to share them.
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => respond.mutate({ id: pendingInvite.id, accept: true })}
              disabled={respond.isPending}
              className="min-h-11 flex-1 rounded-lg bg-emerald-600 text-base font-semibold text-white disabled:opacity-50"
            >
              Accept
            </button>
            <button
              onClick={() => respond.mutate({ id: pendingInvite.id, accept: false })}
              disabled={respond.isPending}
              className="min-h-11 flex-1 rounded-lg border border-slate-300 bg-white text-base text-slate-700"
            >
              No thanks
            </button>
          </div>
        </div>
      )}

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

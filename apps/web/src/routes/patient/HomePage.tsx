import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'
import { Logo } from '../../ui/Logo'
import { Card } from '../../ui/primitives'

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
    <div className="mx-auto flex min-h-dvh w-full max-w-md flex-col px-5">
      <header className="flex min-h-16 items-center justify-between pt-2">
        <Logo size="sm" />
        <Link to="/appointments" className="text-sm font-medium text-slate-500 transition hover:text-slate-900">
          Appointments
        </Link>
      </header>

      <main className="flex flex-1 flex-col justify-center gap-5 pb-14">
        <h1 className="text-[1.9rem] font-bold leading-tight tracking-tight text-slate-900">
          How can we help
          <br />
          you today?
        </h1>

        {pendingInvite && (
          <Card className="border-emerald-600/25 bg-emerald-50/70">
            <p className="text-[15px] leading-relaxed text-slate-700">
              <strong className="text-slate-900">{pendingInvite.sponsor_name}</strong> wants to sponsor your healthcare —
              consults would be paid for you. They only see your care details if you choose to share them.
            </p>
            <div className="mt-4 flex gap-2">
              <button
                onClick={() => respond.mutate({ id: pendingInvite.id, accept: true })}
                disabled={respond.isPending}
                className="min-h-11 flex-1 rounded-xl bg-emerald-600 text-[15px] font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
              >
                Accept
              </button>
              <button
                onClick={() => respond.mutate({ id: pendingInvite.id, accept: false })}
                disabled={respond.isPending}
                className="min-h-11 flex-1 rounded-xl border border-slate-300 bg-white text-[15px] font-medium text-slate-600 transition hover:bg-slate-50"
              >
                No thanks
              </button>
            </div>
          </Card>
        )}

        {/* Primary action — the whole reason the app exists */}
        <Link
          to={open ? `/consult/${open.id}` : '/intake'}
          className="group relative overflow-hidden rounded-3xl bg-emerald-600 p-6 text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="mb-4 size-8 text-emerald-200" aria-hidden="true">
            <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
          </svg>
          <p className="text-xl font-bold leading-snug">{open ? 'Continue your consult' : 'Talk to a doctor now'}</p>
          <p className="mt-1 text-[15px] text-emerald-100">
            {open ? 'Your conversation is still open — pick up where you left off.' : 'Chat, voice or video · usually under 10 minutes'}
          </p>
          <span className="absolute right-5 top-1/2 -translate-y-1/2 text-2xl text-emerald-200 transition group-hover:translate-x-1" aria-hidden="true">
            →
          </span>
        </Link>

        {/* Secondary actions */}
        {[
          {
            to: '/book',
            title: 'Book an appointment',
            sub: 'Pick a doctor and a time that suits you',
            icon: (
              <>
                <rect x="3" y="4" width="18" height="18" rx="2" />
                <path d="M16 2v4M8 2v4M3 10h18" />
              </>
            ),
          },
          {
            to: '/programmes',
            title: 'Care programmes',
            sub: 'Ongoing check-ins for blood pressure & diabetes',
            icon: (
              <>
                <path d="M19.5 12.6 12 20l-7.5-7.4a5 5 0 1 1 7.5-6.6 5 5 0 1 1 7.5 6.6Z" />
              </>
            ),
          },
        ].map((item) => (
          <Link
            key={item.to}
            to={item.to}
            className="group flex items-center gap-4 rounded-3xl border border-slate-900/8 bg-white p-5 shadow-xs transition hover:border-emerald-600/40"
          >
            <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-emerald-600/10 text-emerald-700">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="size-6" aria-hidden="true">
                {item.icon}
              </svg>
            </span>
            <span className="flex-1">
              <span className="block text-base font-semibold text-slate-900">{item.title}</span>
              <span className="mt-0.5 block text-sm text-slate-500">{item.sub}</span>
            </span>
            <span className="text-xl text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-600" aria-hidden="true">
              →
            </span>
          </Link>
        ))}

        <p className="mt-2 text-center text-xs leading-relaxed text-slate-400">
          Licensed Nigerian doctors · Available every day
          <br />
          ₦2,500 per consult — always shown before you pay
        </p>
      </main>
    </div>
  )
}

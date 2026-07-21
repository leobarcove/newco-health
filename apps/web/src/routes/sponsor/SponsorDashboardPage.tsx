import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, setToken } from '../../lib/api'
import { Logo } from '../../ui/Logo'
import { Avatar, Badge, Card, EmptyState, input } from '../../ui/primitives'

interface Overview {
  wallet_balance_kobo: number
  wallet_display: string
  beneficiaries: {
    sponsorship_id: string
    label: string
    phone: string
    status: 'pending' | 'active' | 'declined' | 'paused'
    care_visible: boolean
    last_consult: { state: string; at: string } | null
  }[]
}

const WHEN = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })

/** Reassurance first, transactions second (design plan §4.3). */
export function SponsorDashboardPage() {
  const [phone, setPhone] = useState('')
  const [label, setLabel] = useState('')
  const [topUpAmount, setTopUpAmount] = useState(10000) // naira
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const { data } = useQuery({
    queryKey: ['sponsor-overview'],
    queryFn: () => api<Overview>('/sponsor/overview'),
  })

  const invite = useMutation({
    mutationFn: () =>
      api('/sponsor/beneficiaries', {
        method: 'POST',
        body: JSON.stringify({ phone: `+234${phone.replace(/^0/, '')}`, label }),
      }),
    onSuccess: () => {
      setPhone(''); setLabel('')
      void queryClient.invalidateQueries({ queryKey: ['sponsor-overview'] })
    },
  })

  const topUp = useMutation({
    mutationFn: () =>
      api<{ checkout_url: string | null }>('/sponsor/wallet/topup', {
        method: 'POST',
        body: JSON.stringify({ amount_kobo: topUpAmount * 100 }),
      }),
    onSuccess: (payment) => {
      if (payment.checkout_url) window.location.assign(payment.checkout_url)
      else void queryClient.invalidateQueries({ queryKey: ['sponsor-overview'] })
    },
  })

  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-md flex-col px-5">
      <header className="flex min-h-16 items-center justify-between pt-2">
        <Logo size="sm" />
        <button
          onClick={() => { setToken(null); navigate('/sponsor/login') }}
          className="text-sm font-medium text-slate-400 transition hover:text-slate-700"
        >
          Sign out
        </button>
      </header>

      <main className="flex flex-1 flex-col gap-5 pb-10 pt-2">
        <h1 className="text-[1.7rem] font-bold leading-tight tracking-tight text-slate-900">Your family's care</h1>

        {!data ? (
          <div className="h-44 animate-pulse rounded-3xl bg-slate-900/5" />
        ) : (
          <>
            {/* Beneficiaries — the emotional centre of the page */}
            {data.beneficiaries.length === 0 ? (
              <EmptyState
                icon="queue"
                title="Add your first family member"
                hint="They'll get an SMS invitation and accept with one tap — it costs them nothing."
              />
            ) : (
              data.beneficiaries.map((b) => (
                <Card key={b.sponsorship_id}>
                  <div className="flex items-center gap-3.5">
                    <Avatar name={b.label} size="md" />
                    <div className="flex-1">
                      <p className="text-lg font-semibold text-slate-900">{b.label}</p>
                      <p className="text-sm text-slate-500">{b.phone}</p>
                    </div>
                    <Badge tone={b.status === 'active' ? 'success' : b.status === 'pending' ? 'warning' : 'neutral'}>
                      {b.status === 'pending' ? 'Awaiting accept' : b.status}
                    </Badge>
                  </div>
                  {b.care_visible && b.last_consult ? (
                    <p className="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-[15px] leading-relaxed text-emerald-900">
                      Last consult {WHEN.format(new Date(b.last_consult.at))} — {b.last_consult.state.replace('_', ' ')}
                    </p>
                  ) : b.status === 'active' ? (
                    <p className="mt-3 text-sm leading-relaxed text-slate-400">
                      Care details stay private unless {b.label} chooses to share them with you.
                    </p>
                  ) : null}
                </Card>
              ))
            )}

            {/* Wallet */}
            <div className="rounded-3xl bg-emerald-700 p-5 text-white shadow-lg shadow-emerald-700/25">
              <p className="text-xs font-semibold uppercase tracking-widest text-emerald-200">Care wallet</p>
              <p className="mt-1.5 text-3xl font-bold tabular-nums">{data.wallet_display}</p>
              <p className="mt-2 text-sm leading-relaxed text-emerald-100/90">
                Consults cost ₦2,500 and are paid from here automatically — money can't be diverted.
              </p>
              <div className="mt-4 flex gap-2">
                <span className="relative block">
                  <select
                    value={topUpAmount}
                    onChange={(e) => setTopUpAmount(Number(e.target.value))}
                    aria-label="Top-up amount"
                    className="min-h-12 appearance-none rounded-2xl border border-white/25 bg-white/10 pl-4 pr-9 text-[15px] font-medium text-white outline-none transition focus:border-white/60 [&>option]:text-slate-900"
                  >
                    {[5000, 10000, 25000, 50000].map((n) => (
                      <option key={n} value={n}>₦{n.toLocaleString()}</option>
                    ))}
                  </select>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-emerald-200" aria-hidden="true">
                    <path d="m6 9 6 6 6-6" />
                  </svg>
                </span>
                <button
                  onClick={() => topUp.mutate()}
                  disabled={topUp.isPending}
                  className="min-h-12 flex-1 rounded-2xl bg-white text-[15px] font-semibold text-emerald-800 shadow-sm transition hover:bg-emerald-50 disabled:opacity-45"
                >
                  {topUp.isPending ? 'Processing…' : 'Top up'}
                </button>
              </div>
            </div>

            {/* Invite */}
            <Card>
              <h2 className="mb-3 text-base font-semibold text-slate-900">Add a family member</h2>
              <div className="flex flex-col gap-2.5">
                <input
                  value={label}
                  onChange={(e) => setLabel(e.target.value)}
                  placeholder="Who are they to you? e.g. Mum"
                  className={input}
                />
                <div className="flex items-stretch gap-2">
                  <span className="flex items-center rounded-2xl border border-slate-300/80 bg-white px-3.5 text-[15px] font-medium text-slate-600">
                    🇳🇬 +234
                  </span>
                  <input
                    inputMode="tel"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value.replace(/[^0-9]/g, ''))}
                    placeholder="Their phone number"
                    className={input}
                  />
                </div>
                <button
                  onClick={() => invite.mutate()}
                  disabled={invite.isPending || !label || phone.replace(/^0/, '').length !== 10}
                  className="min-h-13 rounded-2xl border-[1.5px] border-emerald-600/70 bg-white text-[15px] font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:pointer-events-none disabled:opacity-45"
                >
                  {invite.isPending ? 'Sending…' : 'Send invitation by SMS'}
                </button>
              </div>
            </Card>
          </>
        )}
      </main>
    </div>
  )
}

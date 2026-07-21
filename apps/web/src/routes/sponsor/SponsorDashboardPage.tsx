import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'

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

  if (!data) {
    return (
      <main className="mx-auto max-w-lg p-6">
        <div className="h-40 animate-pulse rounded-2xl bg-slate-100" />
      </main>
    )
  }

  return (
    <main className="mx-auto flex min-h-dvh max-w-lg flex-col gap-6 p-6">
      <h1 className="text-2xl font-bold text-slate-900">Your family's care</h1>

      {/* Beneficiaries — the emotional centre of the page */}
      <section className="flex flex-col gap-3">
        {data.beneficiaries.length === 0 && (
          <p className="rounded-2xl bg-slate-900/5 p-5 text-base text-slate-600">
            Add a family member below — they'll get an SMS and accept with one tap.
          </p>
        )}
        {data.beneficiaries.map((b) => (
          <div key={b.sponsorship_id} className="rounded-2xl border border-slate-900/8 bg-white shadow-xs p-4">
            <div className="flex items-center justify-between">
              <p className="text-lg font-semibold text-slate-900">{b.label}</p>
              <span
                className={`rounded-full px-3 py-1 text-sm capitalize ${
                  b.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'
                }`}
              >
                {b.status === 'pending' ? 'Waiting for them to accept' : b.status}
              </span>
            </div>
            <p className="text-sm text-slate-500">{b.phone}</p>
            {b.care_visible && b.last_consult ? (
              <p className="mt-2 rounded-lg bg-emerald-50 p-3 text-base text-emerald-900">
                Last consult: {WHEN.format(new Date(b.last_consult.at))} — {b.last_consult.state.replace('_', ' ')}
              </p>
            ) : b.status === 'active' ? (
              <p className="mt-2 text-sm text-slate-500">
                Care details are private unless {b.label} chooses to share them with you.
              </p>
            ) : null}
          </div>
        ))}
      </section>

      {/* Wallet */}
      <section className="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4">
        <p className="text-sm uppercase tracking-wide text-emerald-800">Care wallet</p>
        <p className="mb-3 text-3xl font-bold text-slate-900">{data.wallet_display}</p>
        <p className="mb-3 text-sm text-slate-600">
          Consults cost ₦2,500 each and are paid from this wallet automatically — money can't be diverted.
        </p>
        <div className="flex gap-2">
          <select
            value={topUpAmount}
            onChange={(e) => setTopUpAmount(Number(e.target.value))}
            className="min-h-12 rounded-2xl border border-slate-300/80 px-3 text-base"
          >
            {[5000, 10000, 25000, 50000].map((n) => (
              <option key={n} value={n}>₦{n.toLocaleString()}</option>
            ))}
          </select>
          <button
            onClick={() => topUp.mutate()}
            disabled={topUp.isPending}
            className="min-h-12 flex-1 rounded-xl bg-emerald-600 text-base font-semibold text-white disabled:opacity-50"
          >
            {topUp.isPending ? 'Processing…' : 'Top up'}
          </button>
        </div>
      </section>

      {/* Invite */}
      <section className="flex flex-col gap-3 rounded-2xl border border-slate-900/8 bg-white shadow-xs p-4">
        <h2 className="text-base font-semibold text-slate-900">Add a family member</h2>
        <input
          value={label}
          onChange={(e) => setLabel(e.target.value)}
          placeholder="Who are they to you? e.g. Mum"
          className="min-h-12 rounded-2xl border border-slate-300/80 px-4 text-base outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
        />
        <div className="flex items-stretch gap-2">
          <span className="flex items-center rounded-2xl border border-slate-300/80 bg-slate-50 px-3 text-base text-slate-700">+234</span>
          <input
            inputMode="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value.replace(/[^0-9]/g, ''))}
            placeholder="Their phone number"
            className="min-h-12 flex-1 rounded-2xl border border-slate-300/80 px-4 text-base outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
          />
        </div>
        <button
          onClick={() => invite.mutate()}
          disabled={invite.isPending || !label || phone.replace(/^0/, '').length !== 10}
          className="min-h-12 rounded-xl border-2 border-emerald-600 text-base font-semibold text-emerald-700 disabled:opacity-50"
        >
          {invite.isPending ? 'Sending…' : 'Send invitation by SMS'}
        </button>
      </section>
    </main>
  )
}

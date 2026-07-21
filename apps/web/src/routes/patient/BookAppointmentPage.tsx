import { useMemo, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { Shell, PageTitle } from '../../ui/shells'
import { Avatar, EmptyState, btn, input } from '../../ui/primitives'

interface BookableDoctor {
  id: string
  name: string
  next_slot: string
}

interface Slot {
  starts_at: string
  ends_at: string
}

const LAGOS_TIME = new Intl.DateTimeFormat('en-NG', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Lagos' })
const LAGOS_DAY = new Intl.DateTimeFormat('en-NG', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'Africa/Lagos' })

/** Numbered step header — consistent wizard language. */
function Step({ children, muted }: { children: ReactNode; muted?: string }) {
  return (
    <h2 className="mb-3 text-[15px] font-semibold text-slate-900">
      {children} {muted && <span className="font-normal text-slate-400">{muted}</span>}
    </h2>
  )
}

const chip = (selected: boolean) =>
  `min-h-11 shrink-0 rounded-full px-4 text-[15px] font-medium transition ${
    selected
      ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/25'
      : 'border border-slate-300/80 bg-white text-slate-700 hover:border-emerald-500/60'
  }`

/** Booking wizard: doctor → day → time → confirm. One decision per step (design plan §2.2). */
export function BookAppointmentPage() {
  const [doctorId, setDoctorId] = useState<string | null>(null)
  const [day, setDay] = useState<string | null>(null)
  const [slot, setSlot] = useState<Slot | null>(null)
  const [complaint, setComplaint] = useState('')
  const navigate = useNavigate()

  const days = useMemo(() => {
    const list: { value: string; label: string }[] = []
    for (let i = 0; i < 14; i++) {
      const d = new Date(Date.now() + i * 86_400_000)
      list.push({
        value: d.toLocaleDateString('en-CA', { timeZone: 'Africa/Lagos' }),
        label: i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : LAGOS_DAY.format(d),
      })
    }
    return list
  }, [])

  const { data: doctors = [] } = useQuery({
    queryKey: ['bookable-doctors'],
    queryFn: () => api<BookableDoctor[]>('/booking/doctors'),
  })

  const { data: slots = [], isFetching: slotsLoading } = useQuery({
    queryKey: ['slots', doctorId, day],
    queryFn: () => api<Slot[]>(`/booking/doctors/${doctorId}/slots?date=${day}`),
    enabled: doctorId !== null && day !== null,
  })

  const book = useMutation({
    mutationFn: async () => {
      const booking = await api<{ id: string; state: string }>('/bookings', {
        method: 'POST',
        body: JSON.stringify({ doctor_id: doctorId, starts_at: slot?.starts_at, complaint }),
      })

      // Slot is held for 15 minutes; pay to confirm (fake gateway settles
      // instantly; real gateways redirect to checkout).
      if (booking.state === 'pending_payment') {
        const payment = await api<{ checkout_url: string | null }>(`/bookings/${booking.id}/pay`, { method: 'POST' })
        if (payment.checkout_url) {
          window.location.assign(payment.checkout_url)
          return booking
        }
      }

      return booking
    },
    onSuccess: () => navigate('/appointments', { replace: true }),
  })

  return (
    <Shell back="/">
      <PageTitle sub="Pick a doctor and a time that suits you — all times are Lagos time.">
        Book an appointment
      </PageTitle>

      <section>
        <Step>1. Choose a doctor</Step>
        {doctors.length === 0 ? (
          <EmptyState
            icon="calendar"
            title="No bookable times right now"
            hint="Our doctors' diaries are full or closed at the moment — an instant consult is usually under 10 minutes."
          />
        ) : (
          <ul className="flex flex-col gap-2.5">
            {doctors.map((d) => (
              <li key={d.id}>
                <button
                  onClick={() => { setDoctorId(d.id); setSlot(null) }}
                  className={`flex w-full items-center gap-3.5 rounded-3xl border bg-white p-4 text-left shadow-xs transition ${
                    doctorId === d.id
                      ? 'border-emerald-600 ring-4 ring-emerald-600/15'
                      : 'border-slate-900/8 hover:border-emerald-500/50'
                  }`}
                >
                  <Avatar name={d.name} size="md" />
                  <span className="flex-1">
                    <span className="block text-base font-semibold text-slate-900">Dr {d.name}</span>
                    <span className="mt-0.5 block text-sm text-slate-500">
                      Next free: {LAGOS_DAY.format(new Date(d.next_slot))}
                    </span>
                  </span>
                  <span
                    className={`grid size-6 shrink-0 place-items-center rounded-full border-2 transition ${
                      doctorId === d.id ? 'border-emerald-600 bg-emerald-600' : 'border-slate-300'
                    }`}
                    aria-hidden="true"
                  >
                    {doctorId === d.id && (
                      <svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" className="size-3.5">
                        <path d="m5 13 4 4L19 7" />
                      </svg>
                    )}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      {doctorId && (
        <section>
          <Step>2. Pick a day</Step>
          <div className="-mx-5 flex gap-2 overflow-x-auto px-5 pb-2">
            {days.map((d) => (
              <button key={d.value} onClick={() => { setDay(d.value); setSlot(null) }} className={chip(day === d.value)}>
                {d.label}
              </button>
            ))}
          </div>
        </section>
      )}

      {doctorId && day && (
        <section>
          <Step muted="(Lagos time)">3. Pick a time</Step>
          {slotsLoading ? (
            <div className="grid grid-cols-3 gap-2">
              {[0, 1, 2].map((i) => (
                <div key={i} className="h-12 animate-pulse rounded-2xl bg-slate-900/5" />
              ))}
            </div>
          ) : slots.length === 0 ? (
            <p className="rounded-2xl bg-slate-900/5 p-4 text-[15px] text-slate-600">
              No free times this day — try another day.
            </p>
          ) : (
            <div className="grid grid-cols-3 gap-2">
              {slots.map((s) => (
                <button
                  key={s.starts_at}
                  onClick={() => setSlot(s)}
                  className={`min-h-12 rounded-2xl text-[15px] font-medium tabular-nums transition ${
                    slot?.starts_at === s.starts_at
                      ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/25'
                      : 'border border-slate-300/80 bg-white text-slate-700 hover:border-emerald-500/60'
                  }`}
                >
                  {LAGOS_TIME.format(new Date(s.starts_at))}
                </button>
              ))}
            </div>
          )}
        </section>
      )}

      {slot && (
        <section className="flex flex-col gap-3">
          <Step muted="(optional)">4. What is it about?</Step>
          <textarea
            value={complaint}
            onChange={(e) => setComplaint(e.target.value)}
            rows={3}
            placeholder="e.g. Follow-up on my blood pressure…"
            className={`${input} min-h-24 py-3.5`}
          />
          <button onClick={() => book.mutate()} disabled={book.isPending} className={btn.primary}>
            {book.isPending
              ? 'Booking…'
              : `Confirm — ${LAGOS_DAY.format(new Date(slot.starts_at))}, ${LAGOS_TIME.format(new Date(slot.starts_at))}`}
          </button>
          <p className="text-center text-sm text-slate-400">₦2,500 per appointment — shown before you pay, always.</p>
          {book.isError && (
            <p className="rounded-2xl bg-red-50 p-4 text-[15px] text-red-800">
              That time may have just been taken — please pick another slot.
            </p>
          )}
        </section>
      )}
    </Shell>
  )
}

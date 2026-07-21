import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'

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
    <main className="mx-auto flex min-h-dvh max-w-md flex-col gap-6 p-6">
      <header className="flex items-center gap-3">
        <Link to="/" className="text-2xl text-slate-500" aria-label="Back">‹</Link>
        <h1 className="text-2xl font-bold text-slate-900">Book an appointment</h1>
      </header>

      <section>
        <h2 className="mb-2 text-base font-medium text-slate-700">1. Choose a doctor</h2>
        <ul className="flex flex-col gap-2">
          {doctors.map((d) => (
            <li key={d.id}>
              <button
                onClick={() => { setDoctorId(d.id); setSlot(null) }}
                className={`flex w-full items-center justify-between rounded-xl border p-4 text-left ${
                  doctorId === d.id ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200 bg-white'
                }`}
              >
                <span className="text-base font-medium text-slate-900">Dr {d.name}</span>
                <span className="text-sm text-slate-500">Next: {LAGOS_DAY.format(new Date(d.next_slot))}</span>
              </button>
            </li>
          ))}
          {doctors.length === 0 && (
            <p className="rounded-2xl bg-slate-900/5 p-4 text-base text-slate-600">
              No doctors are taking bookings right now — try an instant consult instead.
            </p>
          )}
        </ul>
      </section>

      {doctorId && (
        <section>
          <h2 className="mb-2 text-base font-medium text-slate-700">2. Pick a day</h2>
          <div className="flex gap-2 overflow-x-auto pb-2">
            {days.map((d) => (
              <button
                key={d.value}
                onClick={() => { setDay(d.value); setSlot(null) }}
                className={`min-h-12 shrink-0 rounded-full border px-4 text-base ${
                  day === d.value ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-700'
                }`}
              >
                {d.label}
              </button>
            ))}
          </div>
        </section>
      )}

      {doctorId && day && (
        <section>
          <h2 className="mb-2 text-base font-medium text-slate-700">3. Pick a time <span className="text-slate-400">(Lagos time)</span></h2>
          {slotsLoading ? (
            <div className="h-12 animate-pulse rounded-xl bg-slate-100" />
          ) : slots.length === 0 ? (
            <p className="rounded-2xl bg-slate-900/5 p-4 text-base text-slate-600">No free times this day — try another day.</p>
          ) : (
            <div className="grid grid-cols-3 gap-2">
              {slots.map((s) => (
                <button
                  key={s.starts_at}
                  onClick={() => setSlot(s)}
                  className={`min-h-12 rounded-xl border text-base ${
                    slot?.starts_at === s.starts_at
                      ? 'border-emerald-600 bg-emerald-600 text-white'
                      : 'border-slate-300 bg-white text-slate-700'
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
          <h2 className="text-base font-medium text-slate-700">4. What is it about? <span className="text-slate-400">(optional)</span></h2>
          <textarea
            value={complaint}
            onChange={(e) => setComplaint(e.target.value)}
            rows={3}
            placeholder="e.g. Follow-up on my blood pressure…"
            className="rounded-2xl border border-slate-300/80 p-4 text-base outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
          />
          <button
            onClick={() => book.mutate()}
            disabled={book.isPending}
            className="min-h-13 rounded-2xl bg-emerald-600 text-base font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
          >
            {book.isPending ? 'Booking…' : `Confirm — ${LAGOS_DAY.format(new Date(slot.starts_at))}, ${LAGOS_TIME.format(new Date(slot.starts_at))}`}
          </button>
          {book.isError && (
            <p className="rounded-2xl bg-red-50 p-4 text-base text-red-700">
              That time may have just been taken — please pick another slot.
            </p>
          )}
        </section>
      )}
    </main>
  )
}

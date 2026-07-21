import { Link, useNavigate } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface AgendaItem {
  id: string
  starts_at: string
  ends_at: string
  state: 'confirmed' | 'completed'
  patient_name: string
  consult_id: string | null
}

const TIME = new Intl.DateTimeFormat('en-NG', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Lagos' })

/** Today's booked appointments; begin opens the consult workspace. */
export function AgendaPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: agenda = [] } = useQuery({
    queryKey: ['agenda'],
    queryFn: () => api<AgendaItem[]>('/doctor/agenda'),
    refetchInterval: 30000,
  })

  const begin = useMutation({
    mutationFn: (id: string) => api<{ consult_id: string }>(`/doctor/bookings/${id}/begin`, { method: 'POST' }),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['agenda'] })
      navigate(`/doctor/consult/${data.consult_id}`)
    },
  })

  return (
    <main className="mx-auto flex min-h-dvh max-w-2xl flex-col gap-4 p-6">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">Today's appointments</h1>
        <nav className="flex gap-4 text-base">
          <Link to="/doctor" className="text-slate-500 underline">Queue</Link>
          <Link to="/doctor/availability" className="text-slate-500 underline">Availability</Link>
        </nav>
      </header>

      {agenda.length === 0 ? (
        <p className="rounded-xl bg-slate-100 p-6 text-center text-base text-slate-600">
          No appointments booked for today.
        </p>
      ) : (
        <ul className="flex flex-col gap-3">
          {agenda.map((item) => (
            <li key={item.id} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
              <div>
                <p className="text-base font-medium text-slate-900">
                  {TIME.format(new Date(item.starts_at))} — {item.patient_name}
                </p>
                <p className="text-sm capitalize text-slate-500">{item.state}</p>
              </div>
              {item.consult_id ? (
                <Link
                  to={`/doctor/consult/${item.consult_id}`}
                  className="min-h-11 rounded-lg border border-emerald-600 px-5 py-2 text-base font-semibold text-emerald-700"
                >
                  Open
                </Link>
              ) : (
                <button
                  onClick={() => begin.mutate(item.id)}
                  disabled={begin.isPending}
                  className="min-h-11 rounded-lg bg-emerald-600 px-5 text-base font-semibold text-white disabled:opacity-50"
                >
                  Begin
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {begin.isError && (
        <p className="rounded-xl bg-amber-50 p-3 text-base text-amber-800">
          Consults can be started from 5 minutes before the booked time.
        </p>
      )}
    </main>
  )
}

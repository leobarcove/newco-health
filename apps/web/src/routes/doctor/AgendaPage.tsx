import { useNavigate } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { ConsoleShell, PageTitle } from '../../ui/shells'
import { Avatar, Badge, Card, EmptyState } from '../../ui/primitives'

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
    <ConsoleShell active="agenda">
      <div className="mb-6">
        <PageTitle sub="All times are Lagos time. Consults open from five minutes before the booked slot.">
          Today's appointments
        </PageTitle>
      </div>

      {agenda.length === 0 ? (
        <EmptyState
          icon="calendar"
          title="Nothing booked for today"
          hint="Appointments patients book with you show up here. Keep your weekly availability up to date so they can find you."
        />
      ) : (
        <ul className="space-y-3">
          {agenda.map((item) => (
            <li key={item.id}>
              <Card className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                  <div className="w-16 shrink-0 text-center">
                    <p className="text-lg font-bold tabular-nums text-slate-900">{TIME.format(new Date(item.starts_at))}</p>
                    <p className="text-xs text-slate-400">20 min</p>
                  </div>
                  <span className="h-10 w-px bg-slate-900/10" aria-hidden="true" />
                  <div className="flex items-center gap-3">
                    <Avatar name={item.patient_name} size="sm" />
                    <div>
                      <p className="text-base font-semibold text-slate-900">{item.patient_name}</p>
                      <Badge tone={item.state === 'completed' ? 'neutral' : 'success'}>
                        {item.state === 'completed' ? 'Seen' : 'Confirmed'}
                      </Badge>
                    </div>
                  </div>
                </div>

                {item.consult_id ? (
                  <button
                    onClick={() => navigate(`/doctor/consult/${item.consult_id}`)}
                    className="inline-flex min-h-11 items-center rounded-2xl border-[1.5px] border-emerald-600/70 px-5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50"
                  >
                    Open
                  </button>
                ) : (
                  <button
                    onClick={() => begin.mutate(item.id)}
                    disabled={begin.isPending}
                    className="inline-flex min-h-11 items-center rounded-2xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
                  >
                    Begin
                  </button>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}

      {begin.isError && (
        <p className="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">
          Consults can be started from 5 minutes before the booked time.
        </p>
      )}
    </ConsoleShell>
  )
}

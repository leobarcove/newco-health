import { useNavigate } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { ConsoleShell, PageTitle } from '../../ui/shells'
import { Avatar, Badge, Card, EmptyState } from '../../ui/primitives'

interface QueueItem {
  id: string
  patient_name: string
  for_dependant: string | null
  queued_at: string
  waiting_minutes: number
}

/** Doctor queue board — oldest first, one-tap accept (design plan §4.2). */
export function QueuePage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: queue = [], isLoading } = useQuery({
    queryKey: ['doctor-queue'],
    queryFn: () => api<QueueItem[]>('/doctor/queue'),
    refetchInterval: 5000,
  })

  const accept = useMutation({
    mutationFn: (id: string) => api(`/doctor/consults/${id}/accept`, { method: 'POST' }),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: ['doctor-queue'] })
      navigate(`/doctor/consult/${id}`)
    },
  })

  return (
    <ConsoleShell active="queue">
      <div className="mb-6 flex items-end justify-between">
        <PageTitle sub="Patients are ordered by how long they've been waiting.">Waiting patients</PageTitle>
        {queue.length > 0 && <Badge tone="warning">{queue.length} waiting</Badge>}
      </div>

      {isLoading ? (
        <div className="space-y-3">
          {[0, 1].map((i) => (
            <div key={i} className="h-24 animate-pulse rounded-3xl bg-slate-900/5" />
          ))}
        </div>
      ) : queue.length === 0 ? (
        <EmptyState
          icon="queue"
          title="No one is waiting right now"
          hint="New patients appear here the moment they join the queue — this board refreshes itself every few seconds."
        />
      ) : (
        <ul className="space-y-3">
          {queue.map((item, index) => (
            <li key={item.id}>
              <Card className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                  <span className="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-600/10 text-sm font-bold text-emerald-700">
                    {index + 1}
                  </span>
                  <Avatar name={item.patient_name} size="md" />
                  <div>
                    <p className="text-base font-semibold text-slate-900">
                      {item.patient_name}
                      {item.for_dependant && (
                        <span className="font-normal text-slate-500"> · for {item.for_dependant}</span>
                      )}
                    </p>
                    <p className="mt-0.5 text-sm text-slate-500">
                      Waiting{' '}
                      <span className={item.waiting_minutes >= 15 ? 'font-semibold text-amber-700' : ''}>
                        {item.waiting_minutes} min
                      </span>
                    </p>
                  </div>
                </div>
                <button
                  onClick={() => accept.mutate(item.id)}
                  disabled={accept.isPending}
                  className="inline-flex min-h-12 items-center rounded-2xl bg-emerald-600 px-6 text-base font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
                >
                  Accept
                </button>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </ConsoleShell>
  )
}

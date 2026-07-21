import { useNavigate } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface QueueItem {
  id: string
  queued_at: string
  waiting_minutes: number
}

/** Doctor queue board — oldest first, one-tap accept (design plan §4.2). */
export function QueuePage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: queue = [] } = useQuery({
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
    <main className="mx-auto flex min-h-dvh max-w-2xl flex-col gap-4 p-6">
      <h1 className="text-2xl font-bold text-slate-900">Waiting patients</h1>

      {queue.length === 0 ? (
        <p className="rounded-xl bg-slate-100 p-6 text-center text-base text-slate-600">
          No one is waiting right now. We'll refresh automatically.
        </p>
      ) : (
        <ul className="flex flex-col gap-3">
          {queue.map((item, index) => (
            <li key={item.id} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
              <div>
                <p className="text-base font-medium text-slate-900">Patient #{index + 1}</p>
                <p className="text-sm text-slate-500">Waiting {item.waiting_minutes} min</p>
              </div>
              <button
                onClick={() => accept.mutate(item.id)}
                disabled={accept.isPending}
                className="min-h-12 rounded-xl bg-emerald-600 px-6 text-base font-semibold text-white disabled:opacity-50"
              >
                Accept
              </button>
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}

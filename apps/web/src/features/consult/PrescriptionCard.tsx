import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface Prescription {
  id: string
  status: 'issued' | 'dispensed' | 'cancelled'
  pickup_code: string
  doctor: { name: string } | null
  items: { medicine: string; dosage: string; duration_days: number; instructions: string | null }[]
}

/** In-thread prescription card: medicines + the pickup code for any partner pharmacy. */
export function PrescriptionCard({ prescriptionId }: { prescriptionId: string }) {
  const { data } = useQuery({
    queryKey: ['prescription', prescriptionId],
    queryFn: () => api<Prescription>(`/prescriptions/${prescriptionId}`),
  })

  if (!data) {
    return <div className="mx-auto h-32 w-72 animate-pulse rounded-2xl bg-emerald-50" />
  }

  return (
    <div className="mx-auto w-full max-w-sm rounded-3xl border border-emerald-600/25 bg-emerald-50 p-4 shadow-xs">
      <p className="mb-2 text-sm font-semibold uppercase tracking-wide text-emerald-800">Prescription</p>

      <ul className="mb-3 space-y-2">
        {data.items.map((item, i) => (
          <li key={i} className="rounded-xl bg-white p-3 shadow-xs">
            <p className="text-base font-medium text-slate-900">{item.medicine}</p>
            <p className="text-sm text-slate-600">
              {item.dosage} · {item.duration_days} days
            </p>
            {item.instructions && <p className="mt-1 text-sm text-slate-500">{item.instructions}</p>}
          </li>
        ))}
      </ul>

      <div className="rounded-2xl bg-emerald-600 p-3.5 text-center shadow-sm shadow-emerald-600/25">
        <p className="text-xs uppercase tracking-wide text-emerald-100">
          {data.status === 'dispensed' ? 'Collected' : 'Show this code at any partner pharmacy'}
        </p>
        <p className="text-xl font-bold tracking-widest text-white">{data.pickup_code}</p>
      </div>
    </div>
  )
}

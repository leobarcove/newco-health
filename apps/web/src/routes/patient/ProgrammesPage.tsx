import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { Shell, PageTitle } from '../../ui/shells'
import { Badge, Card } from '../../ui/primitives'

interface ProgrammeItem {
  id: string
  name: string
  description: string
  monthly_display: string
  check_in_every_days: number
  enrolment: {
    id: string
    status: 'active' | 'lapsed' | 'cancelled'
    renews_at: string
    next_check_in_at: string
  } | null
}

const WHEN = new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short', timeZone: 'Africa/Lagos' })

/** Chronic-care programmes — ongoing doctor-led care, not one-off consults. */
export function ProgrammesPage() {
  const queryClient = useQueryClient()

  const { data: programmes = [] } = useQuery({
    queryKey: ['programmes'],
    queryFn: () => api<ProgrammeItem[]>('/programmes'),
  })

  const enrol = useMutation({
    mutationFn: (id: string) =>
      api<{ status: string; checkout_url: string | null }>(`/programmes/${id}/enrol`, { method: 'POST' }),
    onSuccess: (result) => {
      if (result.checkout_url) window.location.assign(result.checkout_url)
      else void queryClient.invalidateQueries({ queryKey: ['programmes'] })
    },
  })

  const cancel = useMutation({
    mutationFn: (enrolmentId: string) => api(`/programme-enrolments/${enrolmentId}/cancel`, { method: 'POST' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['programmes'] }),
  })

  return (
    <Shell back="/">
      <PageTitle sub="Ongoing care with regular doctor check-ins — for conditions that need more than one visit.">
        Care programmes
      </PageTitle>

      {programmes.length === 0 && <div className="h-40 animate-pulse rounded-3xl bg-slate-900/5" />}

      {programmes.map((p) => {
        const active = p.enrolment?.status === 'active'

        return (
          <Card key={p.id} className={active ? 'border-emerald-600/25' : ''}>
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">{p.name}</h2>
                <p className="mt-0.5 text-sm text-slate-500">
                  Check-in every {p.check_in_every_days} days · {p.monthly_display}/month
                </p>
              </div>
              {p.enrolment && (
                <Badge tone={active ? 'success' : 'neutral'}>
                  {active ? 'Enrolled' : p.enrolment.status}
                </Badge>
              )}
            </div>

            <p className="mt-3 text-[15px] leading-relaxed text-slate-600">{p.description}</p>

            {active && p.enrolment ? (
              <>
                <p className="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-[15px] text-emerald-900">
                  Next check-in around {WHEN.format(new Date(p.enrolment.next_check_in_at))} · renews{' '}
                  {WHEN.format(new Date(p.enrolment.renews_at))}
                </p>
                <button
                  onClick={() => cancel.mutate(p.enrolment!.id)}
                  disabled={cancel.isPending}
                  className="mt-3 min-h-11 w-full rounded-2xl border border-slate-300/80 bg-white text-[15px] font-medium text-slate-600 transition hover:bg-slate-50"
                >
                  Cancel programme
                </button>
              </>
            ) : (
              <button
                onClick={() => enrol.mutate(p.id)}
                disabled={enrol.isPending}
                className="mt-4 min-h-12 w-full rounded-2xl bg-emerald-600 text-[15px] font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-45"
              >
                {enrol.isPending ? 'One moment…' : `${p.enrolment ? 'Re-enrol' : 'Enrol'} — ${p.monthly_display}/month`}
              </button>
            )}
          </Card>
        )
      })}

      <p className="text-center text-xs leading-relaxed text-slate-400">
        Fees can be covered by your employer's plan or your sponsor automatically.
      </p>
    </Shell>
  )
}

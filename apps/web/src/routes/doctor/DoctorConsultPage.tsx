import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'
import { NotesPanel } from '../../features/consult/NotesPanel'
import { PrescribePanel } from '../../features/consult/PrescribePanel'
import { Thread } from '../../features/consult/Thread'
import { ConsoleShell } from '../../ui/shells'
import { Badge } from '../../ui/primitives'

/** The consult workspace: thread + Notes/Prescribe side panels (design plan §4.2). */
export function DoctorConsultPage() {
  const { id = '' } = useParams()
  const [panel, setPanel] = useState<'none' | 'notes' | 'prescribe'>('none')
  const queryClient = useQueryClient()

  const { data: consult } = useQuery({
    queryKey: ['consult', id],
    queryFn: () => api<Consult>(`/consults/${id}`),
  })

  const conclude = useMutation({
    mutationFn: () => api(`/doctor/consults/${id}/conclude`, { method: 'POST' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['consult', id] }),
  })

  if (!consult) {
    return (
      <ConsoleShell active="consult">
        <div className="h-40 animate-pulse rounded-3xl bg-slate-900/5" />
      </ConsoleShell>
    )
  }

  const live = consult.state === 'in_consult' || consult.state === 'concluded'

  return (
    <ConsoleShell active="consult" flush>
      {/* Workspace toolbar — same visual language as the console bar */}
      <div className="flex items-center justify-between gap-3 border-b border-slate-900/8 bg-white px-5 py-3">
        <div className="flex items-center gap-3">
          <Link
            to="/doctor"
            aria-label="Back to queue"
            className="-ml-1 grid size-9 place-items-center rounded-full text-slate-500 transition hover:bg-slate-900/5 hover:text-slate-900"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" className="size-5">
              <path d="m15 18-6-6 6-6" />
            </svg>
          </Link>
          <div>
            <p className="text-base font-semibold text-slate-900">Consult workspace</p>
            <Badge tone={consult.state === 'in_consult' ? 'success' : 'neutral'}>
              {consult.state === 'in_consult' ? 'Live' : consult.state.replace('_', ' ')}
            </Badge>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1 rounded-full bg-slate-900/5 p-1">
            {(['notes', 'prescribe'] as const).map((name) => (
              <button
                key={name}
                onClick={() => setPanel(panel === name ? 'none' : name)}
                className={`rounded-full px-4 py-2 text-sm font-semibold capitalize transition ${
                  panel === name ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                {name}
              </button>
            ))}
          </div>
          {consult.state === 'in_consult' && (
            <button
              onClick={() => conclude.mutate()}
              disabled={conclude.isPending}
              className="min-h-10 rounded-full border-[1.5px] border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700"
            >
              End consult
            </button>
          )}
        </div>
      </div>

      <div className="flex min-h-0 flex-1">
        <div className="min-h-0 min-w-0 flex-1">
          <Thread consultId={consult.id} live={live} />
        </div>
        {panel !== 'none' && (
          <aside className="fixed inset-x-0 bottom-0 top-32 z-30 overflow-y-auto rounded-t-3xl border-t border-slate-900/10 bg-white shadow-2xl md:static md:inset-auto md:z-auto md:w-85 md:shrink-0 md:rounded-none md:border-l md:border-t-0 md:shadow-none">
            <div className="flex items-center justify-between px-4 pt-3 md:hidden">
              <p className="text-sm font-semibold capitalize text-slate-900">{panel}</p>
              <button onClick={() => setPanel('none')} className="grid size-9 place-items-center rounded-full text-slate-500 hover:bg-slate-900/5" aria-label="Close panel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="size-4.5"><path d="M18 6 6 18M6 6l12 12" /></svg>
              </button>
            </div>
            {panel === 'notes' ? <NotesPanel consultId={consult.id} /> : <PrescribePanel consultId={consult.id} />}
          </aside>
        )}
      </div>
    </ConsoleShell>
  )
}

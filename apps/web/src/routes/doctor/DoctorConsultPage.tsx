import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, type Consult } from '../../lib/api'
import { NotesPanel } from '../../features/consult/NotesPanel'
import { PrescribePanel } from '../../features/consult/PrescribePanel'
import { Thread } from '../../features/consult/Thread'

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

  if (!consult) return null

  return (
    <main className="mx-auto flex h-dvh max-w-2xl flex-col bg-slate-50">
      <header className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
        <div className="flex items-center gap-3">
          <Link to="/doctor" className="text-2xl text-slate-500" aria-label="Back to queue">
            ‹
          </Link>
          <h1 className="text-base font-semibold text-slate-900">Consult</h1>
        </div>
        <div className="flex gap-2">
          {(['notes', 'prescribe'] as const).map((name) => (
            <button
              key={name}
              onClick={() => setPanel(panel === name ? 'none' : name)}
              className={`min-h-10 rounded-lg border px-4 text-sm font-semibold capitalize ${
                panel === name ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 text-slate-700'
              }`}
            >
              {name}
            </button>
          ))}
          {consult.state === 'in_consult' && (
            <button
              onClick={() => conclude.mutate()}
              disabled={conclude.isPending}
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700"
            >
              End consult
            </button>
          )}
        </div>
      </header>

      <div className="flex min-h-0 flex-1">
        <div className="min-h-0 min-w-0 flex-1">
          <Thread consultId={consult.id} live={consult.state === 'in_consult' || consult.state === 'concluded'} />
        </div>
        {panel !== 'none' && (
          <aside className="w-80 shrink-0 overflow-y-auto border-l border-slate-200 bg-white">
            {panel === 'notes' ? <NotesPanel consultId={consult.id} /> : <PrescribePanel consultId={consult.id} />}
          </aside>
        )}
      </div>
    </main>
  )
}

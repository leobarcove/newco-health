import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'

interface FormularyHit {
  id: number
  label: string
}

interface DraftItem {
  formulary_item_id: number
  label: string
  dosage: string
  duration_days: number
}

/** Formulary-driven prescribing with dose fields (design plan §4.2). */
export function PrescribePanel({ consultId }: { consultId: string }) {
  const [query, setQuery] = useState('')
  const [items, setItems] = useState<DraftItem[]>([])
  const queryClient = useQueryClient()

  const { data: hits = [] } = useQuery({
    queryKey: ['formulary', query],
    queryFn: () => api<FormularyHit[]>(`/formulary?q=${encodeURIComponent(query)}`),
    enabled: query.length >= 2,
  })

  const issue = useMutation({
    mutationFn: () =>
      api(`/doctor/consults/${consultId}/prescriptions`, {
        method: 'POST',
        body: JSON.stringify({
          items: items.map(({ formulary_item_id, dosage, duration_days }) => ({ formulary_item_id, dosage, duration_days })),
        }),
      }),
    onSuccess: () => {
      setItems([])
      void queryClient.invalidateQueries({ queryKey: ['messages', consultId] })
    },
  })

  return (
    <div className="flex flex-col gap-3 p-4">
      <label className="flex flex-col gap-1">
        <span className="text-sm font-semibold text-slate-700">Add medicine</span>
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search the formulary…"
          className="rounded-xl border border-slate-300/80 bg-white p-2.5 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
        />
      </label>

      {query.length >= 2 && (
        <ul className="max-h-40 overflow-y-auto rounded-xl border border-slate-900/8 shadow-xs">
          {hits.map((hit) => (
            <li key={hit.id}>
              <button
                onClick={() => {
                  if (!items.some((i) => i.formulary_item_id === hit.id)) {
                    setItems([...items, { formulary_item_id: hit.id, label: hit.label, dosage: '', duration_days: 5 }])
                  }
                  setQuery('')
                }}
                className="w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
              >
                {hit.label}
              </button>
            </li>
          ))}
          {hits.length === 0 && <li className="px-3 py-2 text-sm text-slate-400">No matches</li>}
        </ul>
      )}

      {items.map((item, index) => (
        <div key={item.formulary_item_id} className="rounded-xl border border-slate-900/8 bg-white p-3 shadow-xs">
          <div className="mb-2 flex items-start justify-between gap-2">
            <p className="text-sm font-medium text-slate-900">{item.label}</p>
            <button
              onClick={() => setItems(items.filter((_, i) => i !== index))}
              className="text-sm text-red-600"
              aria-label={`Remove ${item.label}`}
            >
              ✕
            </button>
          </div>
          <input
            value={item.dosage}
            onChange={(e) => setItems(items.map((it, i) => (i === index ? { ...it, dosage: e.target.value } : it)))}
            placeholder="Dosage, e.g. 1 tablet twice daily"
            className="mb-2 w-full rounded-xl border border-slate-300/80 bg-white p-2.5 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
          />
          <label className="flex items-center gap-2 text-sm text-slate-600">
            for
            <input
              type="number"
              min={1}
              max={90}
              value={item.duration_days}
              onChange={(e) => setItems(items.map((it, i) => (i === index ? { ...it, duration_days: Number(e.target.value) } : it)))}
              className="w-16 rounded-xl border border-slate-300/80 bg-white p-1.5 text-center text-sm"
            />
            days
          </label>
        </div>
      ))}

      <button
        onClick={() => issue.mutate()}
        disabled={issue.isPending || items.length === 0 || items.some((i) => i.dosage.trim() === '')}
        className="min-h-11 rounded-xl bg-emerald-600 text-sm font-semibold text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-700 disabled:opacity-40 disabled:shadow-none"
      >
        {issue.isPending ? 'Issuing…' : `Issue prescription${items.length > 0 ? ` (${items.length})` : ''}`}
      </button>
      {issue.isError && <p className="text-sm text-red-700">Could not issue — check the consult is still active.</p>}
    </div>
  )
}

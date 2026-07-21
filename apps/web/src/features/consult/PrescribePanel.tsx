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
          className="rounded-lg border border-slate-300 p-2 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
        />
      </label>

      {query.length >= 2 && (
        <ul className="max-h-40 overflow-y-auto rounded-lg border border-slate-200">
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
        <div key={item.formulary_item_id} className="rounded-lg border border-slate-200 p-3">
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
            className="mb-2 w-full rounded-lg border border-slate-300 p-2 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15"
          />
          <label className="flex items-center gap-2 text-sm text-slate-600">
            for
            <input
              type="number"
              min={1}
              max={90}
              value={item.duration_days}
              onChange={(e) => setItems(items.map((it, i) => (i === index ? { ...it, duration_days: Number(e.target.value) } : it)))}
              className="w-16 rounded-lg border border-slate-300 p-1 text-sm"
            />
            days
          </label>
        </div>
      ))}

      <button
        onClick={() => issue.mutate()}
        disabled={issue.isPending || items.length === 0 || items.some((i) => i.dosage.trim() === '')}
        className="min-h-11 rounded-lg bg-emerald-600 text-sm font-semibold text-white disabled:opacity-40"
      >
        {issue.isPending ? 'Issuing…' : `Issue prescription${items.length > 0 ? ` (${items.length})` : ''}`}
      </button>
      {issue.isError && <p className="text-sm text-red-700">Could not issue — check the consult is still active.</p>}
    </div>
  )
}

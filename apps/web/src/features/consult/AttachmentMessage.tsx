import { useQuery } from '@tanstack/react-query'
import { fetchMediaUrl } from '../../lib/media'

/** Renders an image or voice-note message via authenticated blob URLs. */
export function AttachmentMessage({ kind, fileUrl, mine }: { kind: 'image' | 'voice_note'; fileUrl: string; mine: boolean }) {
  const { data: src } = useQuery({
    queryKey: ['media', fileUrl],
    queryFn: () => fetchMediaUrl(fileUrl),
    staleTime: Infinity,
  })

  return (
    <div className={mine ? 'flex justify-end' : 'flex justify-start'}>
      {src === undefined ? (
        <div className="h-24 w-40 animate-pulse rounded-2xl bg-slate-200" />
      ) : kind === 'image' ? (
        <img src={src} alt="Shared in consult" className="max-h-64 max-w-[80%] rounded-2xl object-cover shadow-sm" />
      ) : (
        <audio controls src={src} className="max-w-[80%]" preload="metadata" />
      )}
    </div>
  )
}

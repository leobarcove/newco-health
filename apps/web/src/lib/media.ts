import { getToken } from './api'

/**
 * Client-side image compression (design plan §6: uploads ≤200 KB, EXIF
 * stripped). Canvas re-encode inherently drops EXIF/GPS metadata.
 */
export async function compressImage(file: File): Promise<Blob> {
  const bitmap = await createImageBitmap(file)
  const scale = Math.min(1, 1280 / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(bitmap.width * scale)
  canvas.height = Math.round(bitmap.height * scale)
  canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height)

  for (const quality of [0.7, 0.5, 0.3]) {
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality))
    if (blob !== null && blob.size <= 200 * 1024) return blob
    if (quality === 0.3 && blob !== null) return blob // best effort
  }

  throw new Error('Could not compress image')
}

export async function uploadAttachment(consultId: string, kind: 'image' | 'voice_note', blob: Blob, filename: string): Promise<void> {
  const form = new FormData()
  form.append('kind', kind)
  form.append('file', blob, filename)

  const response = await fetch(`/api/consults/${consultId}/attachments`, {
    method: 'POST',
    headers: { Accept: 'application/json', Authorization: `Bearer ${getToken()}` },
    body: form,
  })
  if (!response.ok) throw new Error('Upload failed')
}

/** Authenticated media fetch → object URL (img/audio tags cannot send bearer tokens). */
export async function fetchMediaUrl(fileUrl: string): Promise<string> {
  const response = await fetch(fileUrl, {
    headers: { Authorization: `Bearer ${getToken()}` },
  })
  if (!response.ok) throw new Error('Could not load attachment')

  return URL.createObjectURL(await response.blob())
}

const TOKEN_KEY = 'newco.token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token === null) localStorage.removeItem(TOKEN_KEY)
  else localStorage.setItem(TOKEN_KEY, token)
}

export class ApiError extends Error {
  status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

/**
 * Thin typed fetch wrapper. Replaced by the generated @newco/api-client once
 * the OpenAPI spec lands (dev plan §11) — keep call sites identical in shape.
 */
export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken()
  const response = await fetch(`/api${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as { message?: string } | null
    throw new ApiError(response.status, body?.message ?? 'Something went wrong. Please try again.')
  }

  return response.json() as Promise<T>
}

export interface Me {
  id: number
  name: string
  phone: string
  role: 'patient' | 'doctor' | 'sponsor' | 'staff'
}

export interface Consult {
  id: string
  state: string
  modality: string
  queue_position: number | null
  doctor: { name: string } | null
  created_at: string
}

export interface Message {
  id: string
  kind: 'text' | 'image' | 'voice_note' | 'system' | 'prescription'
  body: string
  file_url: string | null
  sender_id: number | null
  mine: boolean
  created_at: string
}

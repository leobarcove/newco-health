import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, setToken } from '../../lib/api'
import { Logo } from '../../ui/Logo'
import { btn, input } from '../../ui/primitives'

export function PharmacyLoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  async function submit() {
    setBusy(true)
    setError(null)
    try {
      const result = await api<{ token: string }>('/auth/pharmacy/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      })
      setToken(result.token)
      navigate('/pharmacy', { replace: true })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-7 px-6 py-10">
      <div className="flex flex-col items-start gap-6">
        <Logo size="lg" />
        <div>
          <h1 className="text-[2rem] font-bold leading-tight tracking-tight text-slate-900">Pharmacy counter</h1>
          <p className="mt-3 text-base leading-relaxed text-slate-500">
            Sign in to look up pickup codes and dispense NewCo Health prescriptions.
          </p>
        </div>
      </div>

      <div className="flex flex-col gap-3">
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Counter email" className={input} />
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Password" className={input} />
        <button onClick={submit} disabled={busy || !email || !password} className={btn.primary}>
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </div>

      {error && <p className="rounded-2xl bg-red-50 p-4 text-[15px] text-red-800">{error}</p>}
    </div>
  )
}

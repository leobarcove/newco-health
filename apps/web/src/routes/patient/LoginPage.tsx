import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, setToken, type Me } from '../../lib/api'

/** Phone → OTP sign-in. One thing per screen (design plan §2.2). */
export function LoginPage() {
  const [step, setStep] = useState<'phone' | 'code'>('phone')
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  const fullPhone = `+234${phone.replace(/^0/, '')}`

  async function requestCode() {
    setBusy(true)
    setError(null)
    try {
      await api('/auth/otp/request', { method: 'POST', body: JSON.stringify({ phone: fullPhone }) })
      setStep('code')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  async function verify() {
    setBusy(true)
    setError(null)
    try {
      const result = await api<{ token: string; user: Me }>('/auth/otp/verify', {
        method: 'POST',
        body: JSON.stringify({ phone: fullPhone, code }),
      })
      setToken(result.token)
      navigate(result.user.role === 'doctor' ? '/doctor' : '/', { replace: true })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Please try again.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
      <h1 className="text-2xl font-bold text-slate-900">
        {step === 'phone' ? 'Talk to a doctor' : 'Enter your code'}
      </h1>

      {step === 'phone' ? (
        <>
          <p className="text-base text-slate-600">Enter your phone number and we'll send you a sign-in code by SMS.</p>
          <div className="flex items-stretch gap-2">
            <span className="flex items-center rounded-xl border border-slate-300 bg-slate-50 px-3 text-base text-slate-700">
              +234
            </span>
            <input
              inputMode="tel"
              autoFocus
              value={phone}
              onChange={(e) => setPhone(e.target.value.replace(/[^0-9]/g, ''))}
              placeholder="801 234 5678"
              className="min-h-14 flex-1 rounded-xl border border-slate-300 px-4 text-lg outline-none focus:border-emerald-600"
            />
          </div>
          <button
            onClick={requestCode}
            disabled={busy || phone.replace(/^0/, '').length !== 10}
            className="min-h-14 rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-50"
          >
            {busy ? 'Sending…' : 'Send my code'}
          </button>
        </>
      ) : (
        <>
          <p className="text-base text-slate-600">
            We sent a 6-digit code to <strong>{fullPhone}</strong>.
          </p>
          <input
            inputMode="numeric"
            autoFocus
            maxLength={6}
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/[^0-9]/g, ''))}
            placeholder="••••••"
            className="min-h-14 rounded-xl border border-slate-300 px-4 text-center text-2xl tracking-[0.5em] outline-none focus:border-emerald-600"
          />
          <button
            onClick={verify}
            disabled={busy || code.length !== 6}
            className="min-h-14 rounded-xl bg-emerald-600 text-lg font-semibold text-white disabled:opacity-50"
          >
            {busy ? 'Checking…' : 'Sign in'}
          </button>
          <button onClick={() => setStep('phone')} className="text-base text-slate-500 underline">
            Use a different number
          </button>
        </>
      )}

      {error && <p className="rounded-xl bg-red-50 p-3 text-base text-red-700">{error}</p>}
    </main>
  )
}

import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { login } from '../api/client'

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
})

export function LoginPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) =>
      login(email, password),
    onSuccess: () => {
      navigate('/app/today')
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Login failed.')
    },
  })

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault()
    const parsed = loginSchema.safeParse(form)
    if (!parsed.success) {
      setError(parsed.error.errors[0]?.message || 'Check your credentials.')
      return
    }
    setError(null)
    mutation.mutate(parsed.data)
  }

  return (
    <div className="auth-layout">
      <div className="auth-panel">
        <p className="eyebrow">Welcome back</p>
        <h1>Log in to BackOffice Autopilot</h1>
        <p className="muted">
          Keep your tutoring business in motion with session-first logging.
        </p>
        <form className="auth-form" onSubmit={handleSubmit}>
          <label className="field">
            <span>Email</span>
            <input
              type="email"
              value={form.email}
              onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))}
            />
          </label>
          <label className="field">
            <span>Password</span>
            <input
              type="password"
              value={form.password}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, password: event.target.value }))
              }
            />
          </label>
          {error ? <p className="form-error">{error}</p> : null}
          <button className="button button--primary" type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Signing in...' : 'Log in'}
          </button>
        </form>
        <button className="button button--ghost" onClick={() => navigate('/onboarding')}>
          Create a new account
        </button>
      </div>
      <div className="auth-visual">
        <div>
          <p className="eyebrow">Autopilot preview</p>
          <h2>Log a session so invoices and follow-ups appear automatically.</h2>
          <p className="muted">Get the first draft in under 5 seconds.</p>
        </div>
      </div>
    </div>
  )
}

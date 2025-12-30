import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { completeOnboarding, register } from '../api/client'
import { CURRENCIES } from '../data/currencies'

const registerSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
})

const defaultStartAt = () => {
  const now = new Date()
  const offsetMs = now.getTimezoneOffset() * 60_000
  return new Date(now.getTime() - offsetMs).toISOString().slice(0, 16)
}

export function OnboardingPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    email: '',
    password: '',
    referralCode: '',
    businessType: 'Tutor',
    chargeModel: 'per_session',
    defaultRateCents: '',
    defaultCurrency: 'EUR',
    followUpDays: '3',
    invoiceReminderDays: '7',
    firstClientName: '',
    firstStartAt: defaultStartAt(),
    firstDurationMinutes: '60',
    firstBillable: true,
    firstNotes: '',
  })
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: async ({
      email,
      password,
      referralCode,
    }: {
      email: string
      password: string
      referralCode?: string
    }) => {
      await register(email, password, referralCode)

      const payload: Record<string, unknown> = {
        business_type: form.businessType,
        charge_model: form.chargeModel,
        default_currency: form.defaultCurrency.trim().toUpperCase(),
        follow_up_days: Number(form.followUpDays),
        invoice_reminder_days: Number(form.invoiceReminderDays),
      }

      if (form.defaultRateCents.trim() !== '') {
        payload.default_rate_cents = Number(form.defaultRateCents)
      }

      if (form.firstClientName.trim() !== '') {
        const startAt = new Date(form.firstStartAt)
        if (Number.isNaN(startAt.getTime())) {
          throw new Error('Start time must be a valid date.')
        }
        payload.first_client_name = form.firstClientName.trim()
        payload.first_start_at = startAt.toISOString()
        payload.first_duration_minutes = Number(form.firstDurationMinutes)
        payload.first_billable = form.firstBillable
        payload.first_notes = form.firstNotes.trim() || undefined
        payload.first_type = 'session'
      }

      await completeOnboarding(payload)
    },
    onSuccess: () => {
      navigate('/app/today')
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not create account.')
    },
  })

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault()
    const parsed = registerSchema.safeParse({
      email: form.email,
      password: form.password,
    })
    if (!parsed.success) {
      setError(parsed.error.errors[0]?.message || 'Check the form fields.')
      return
    }
    const currency = form.defaultCurrency.trim()
    if (currency === '') {
      setError('Select a currency.')
      return
    }
    if (form.defaultRateCents.trim() !== '') {
      const rate = Number(form.defaultRateCents)
      if (!Number.isFinite(rate) || rate < 0) {
        setError('Default rate must be 0 or greater.')
        return
      }
    }
    const followUpDays = Number(form.followUpDays)
    if (!Number.isFinite(followUpDays) || followUpDays < 0) {
      setError('Follow-up days must be 0 or greater.')
      return
    }
    const reminderDays = Number(form.invoiceReminderDays)
    if (!Number.isFinite(reminderDays) || reminderDays < 0) {
      setError('Invoice reminder days must be 0 or greater.')
      return
    }
    if (form.firstClientName.trim() !== '') {
      const duration = Number(form.firstDurationMinutes)
      if (!Number.isFinite(duration) || duration <= 0) {
        setError('Duration must be greater than 0.')
        return
      }
    }
    setError(null)
    mutation.mutate({ ...parsed.data, referralCode: form.referralCode.trim() || undefined })
  }

  return (
    <div className="onboarding-layout">
      <div className="onboarding-card">
        <p className="eyebrow">First 5 minutes</p>
        <h1>Set your autopilot in motion.</h1>
        <p className="muted">
          Start with a quick setup. You can fine-tune rates and templates later.
        </p>
        <form className="stack" onSubmit={handleSubmit}>
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
          <label className="field">
            <span>Referral code (optional)</span>
            <input
              type="text"
              value={form.referralCode}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, referralCode: event.target.value }))
              }
            />
          </label>
          <div className="grid two-columns">
            <label className="field">
              <span>Business type</span>
              <select
                value={form.businessType}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, businessType: event.target.value }))
                }
              >
                <option value="Tutor">Tutor</option>
                <option value="Coach">Coach</option>
                <option value="Consultant">Consultant</option>
              </select>
            </label>
            <label className="field">
              <span>Charge model</span>
              <select
                value={form.chargeModel}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, chargeModel: event.target.value }))
                }
              >
                <option value="per_session">Per session</option>
                <option value="package">Packages</option>
                <option value="monthly">Monthly</option>
              </select>
            </label>
          </div>
          <div className="grid two-columns">
            <label className="field">
              <span>Default rate (cents)</span>
              <input
                type="number"
                min="0"
                value={form.defaultRateCents}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, defaultRateCents: event.target.value }))
                }
                placeholder="6500"
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <select
                value={form.defaultCurrency}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, defaultCurrency: event.target.value }))
                }
              >
                {CURRENCIES.map((currencyOption) => (
                  <option key={currencyOption.code} value={currencyOption.code}>
                    {currencyOption.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <div className="grid two-columns">
            <label className="field">
              <span>Follow-up delay (days)</span>
              <input
                type="number"
                min="0"
                value={form.followUpDays}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, followUpDays: event.target.value }))
                }
              />
            </label>
            <label className="field">
              <span>Invoice reminder (days)</span>
              <input
                type="number"
                min="0"
                value={form.invoiceReminderDays}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, invoiceReminderDays: event.target.value }))
                }
              />
            </label>
          </div>
          <div className="divider">
            <span>First work event (optional)</span>
          </div>
          <label className="field">
            <span>Client name</span>
            <input
              type="text"
              value={form.firstClientName}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, firstClientName: event.target.value }))
              }
              placeholder="Anna"
            />
          </label>
          <div className="grid two-columns">
            <label className="field">
              <span>Start time</span>
              <input
                type="datetime-local"
                value={form.firstStartAt}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, firstStartAt: event.target.value }))
                }
              />
            </label>
            <label className="field">
              <span>Duration (minutes)</span>
              <input
                type="number"
                min="15"
                step="15"
                value={form.firstDurationMinutes}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, firstDurationMinutes: event.target.value }))
                }
              />
            </label>
          </div>
          <label className="field field--checkbox">
            <input
              type="checkbox"
              checked={form.firstBillable}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, firstBillable: event.target.checked }))
              }
            />
            <span>Billable session</span>
          </label>
          <label className="field">
            <span>Notes</span>
            <textarea
              rows={3}
              value={form.firstNotes}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, firstNotes: event.target.value }))
              }
              placeholder="Tutoring session with Anna - 1h online."
            />
          </label>
          {error ? <p className="form-error">{error}</p> : null}
          <button className="button button--primary" type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Creating...' : 'Start autopilot'}
          </button>
        </form>
      </div>
      <div className="onboarding-preview">
        <div className="card">
          <h3>Autopilot response</h3>
          <ul className="list">
            <li>
              <span>Client created: Anna</span>
              <strong>OK</strong>
            </li>
            <li>
              <span>Billable detected, draft invoice created</span>
              <strong>OK</strong>
            </li>
            <li>
              <span>Follow-up suggested in 3 days</span>
              <strong>OK</strong>
            </li>
          </ul>
        </div>
      </div>
    </div>
  )
}

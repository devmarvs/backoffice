import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useLocation, useNavigate } from 'react-router-dom'
import {
  connectGoogle,
  confirmPayPalSubscription,
  disconnectGoogle,
  type BillingPlan,
  fetchAuditLogs,
  fetchGoogleStatus,
  fetchPayPalStatus,
  fetchCalendarEvents,
  fetchReferrals,
  fetchSettings,
  fetchTemplates,
  runReminders,
  startPayPalCheckout,
  startPayPalManage,
  syncGoogleCalendar,
  updateSettings,
  updateTemplate,
} from '../api/client'
import { CURRENCIES } from '../data/currencies'

const templateLabels = {
  follow_up: 'Follow-up template',
  payment_reminder: 'Payment reminder template',
  no_show: 'No-show template',
}

export function SettingsPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    businessType: '',
    chargeModel: '',
    defaultRateCents: '',
    defaultCurrency: 'EUR',
    followUpDays: '3',
    invoiceReminderDays: '7',
  })
  const [templateState, setTemplateState] = useState<Record<string, { subject?: string; body: string }>>({})
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [showCalendar, setShowCalendar] = useState(false)
  const [handledPayPal, setHandledPayPal] = useState(false)
  const [billingPlan, setBillingPlan] = useState<BillingPlan>('starter')
  const [billingPlanTouched, setBillingPlanTouched] = useState(false)

  const settingsQuery = useQuery({
    queryKey: ['settings'],
    queryFn: fetchSettings,
  })
  const templatesQuery = useQuery({
    queryKey: ['templates'],
    queryFn: fetchTemplates,
  })
  const paypalStatusQuery = useQuery({
    queryKey: ['billing-status', 'paypal'],
    queryFn: fetchPayPalStatus,
  })
  const referralsQuery = useQuery({
    queryKey: ['referrals'],
    queryFn: fetchReferrals,
  })
  const calendarQuery = useQuery({
    queryKey: ['calendar-events'],
    queryFn: () => fetchCalendarEvents({}),
    enabled: showCalendar,
  })
  const googleStatusQuery = useQuery({
    queryKey: ['google-status'],
    queryFn: fetchGoogleStatus,
  })
  const auditQuery = useQuery({
    queryKey: ['audit-logs'],
    queryFn: () => fetchAuditLogs(10),
  })

  const planFromSubscription = paypalStatusQuery.data?.plan ?? null

  useEffect(() => {
    if (!billingPlanTouched && planFromSubscription) {
      setBillingPlan(planFromSubscription)
    }
  }, [billingPlanTouched, planFromSubscription])

  useEffect(() => {
    if (settingsQuery.data) {
      setForm({
        businessType: settingsQuery.data.business_type || '',
        chargeModel: settingsQuery.data.charge_model || '',
        defaultRateCents:
          settingsQuery.data.default_rate_cents !== null && settingsQuery.data.default_rate_cents !== undefined
            ? String(settingsQuery.data.default_rate_cents)
            : '',
        defaultCurrency: settingsQuery.data.default_currency || 'EUR',
        followUpDays:
          settingsQuery.data.follow_up_days !== null && settingsQuery.data.follow_up_days !== undefined
            ? String(settingsQuery.data.follow_up_days)
            : '3',
        invoiceReminderDays:
          settingsQuery.data.invoice_reminder_days !== null && settingsQuery.data.invoice_reminder_days !== undefined
            ? String(settingsQuery.data.invoice_reminder_days)
            : '7',
      })
    }
  }, [settingsQuery.data])

  useEffect(() => {
    if (templatesQuery.data) {
      const next: Record<string, { subject?: string; body: string }> = {}
      templatesQuery.data.forEach((template) => {
        next[template.type] = {
          subject: template.subject || '',
          body: template.body,
        }
      })
      setTemplateState(next)
    }
  }, [templatesQuery.data])

  const settingsMutation = useMutation({
    mutationFn: updateSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] })
      setSuccess('Settings saved.')
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not update settings.')
      setSuccess(null)
    },
  })

  const templateMutation = useMutation({
    mutationFn: ({ type, body, subject }: { type: string; body: string; subject?: string }) =>
      updateTemplate(type as 'follow_up' | 'payment_reminder' | 'no_show', { body, subject }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['templates'] })
      setSuccess('Template updated.')
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not update template.')
      setSuccess(null)
    },
  })

  const remindersMutation = useMutation({
    mutationFn: runReminders,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['settings'] })
      setSuccess(`Created ${result.created} reminder(s).`)
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Reminder run failed.')
      setSuccess(null)
    },
  })

  const paypalCheckoutMutation = useMutation({
    mutationFn: () => startPayPalCheckout(billingPlan),
    onSuccess: (result) => {
      window.location.href = result.url
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'PayPal checkout is not configured yet.')
      setSuccess(null)
    },
  })

  const paypalManageMutation = useMutation({
    mutationFn: startPayPalManage,
    onSuccess: (result) => {
      window.location.href = result.url
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'PayPal management is not available yet.')
      setSuccess(null)
    },
  })

  const paypalConfirmMutation = useMutation({
    mutationFn: (subscriptionId: string) => confirmPayPalSubscription(subscriptionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing-status', 'paypal'] })
      setSuccess('PayPal subscription confirmed.')
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'PayPal confirmation failed.')
      setSuccess(null)
    },
  })

  const connectMutation = useMutation({
    mutationFn: connectGoogle,
    onSuccess: (result) => {
      window.location.href = result.url
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Google integration is not configured yet.')
      setSuccess(null)
    },
  })

  const syncMutation = useMutation({
    mutationFn: syncGoogleCalendar,
    onSuccess: (result) => {
      setSuccess(`Imported ${result.imported} calendar events.`)
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
      queryClient.invalidateQueries({ queryKey: ['google-status'] })
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Calendar sync failed.')
      setSuccess(null)
    },
  })

  const disconnectMutation = useMutation({
    mutationFn: disconnectGoogle,
    onSuccess: () => {
      setSuccess('Disconnected Google Calendar.')
      setError(null)
      queryClient.invalidateQueries({ queryKey: ['calendar-events'] })
      queryClient.invalidateQueries({ queryKey: ['google-status'] })
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not disconnect.')
      setSuccess(null)
    },
  })

  const referralCode = referralsQuery.data?.code.code
  const referralCount = referralsQuery.data?.referrals.length || 0
  const referralRows = useMemo(() => referralsQuery.data?.referrals ?? [], [referralsQuery.data])

  useEffect(() => {
    if (handledPayPal) {
      return
    }
    const params = new URLSearchParams(location.search)
    const subscriptionId = params.get('subscription_id')
    const status = params.get('paypal')

    if (subscriptionId) {
      setHandledPayPal(true)
      paypalConfirmMutation.mutate(subscriptionId, {
        onSettled: () => navigate('/app/settings', { replace: true }),
      })
      return
    }

    if (status === 'cancel') {
      setHandledPayPal(true)
      setError('PayPal checkout was cancelled.')
      navigate('/app/settings', { replace: true })
    }
  }, [handledPayPal, location.search, navigate, paypalConfirmMutation])

  return (
    <div className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">Settings</p>
          <h2>Rates, templates, and automation.</h2>
          <p className="muted">
            Configure your defaults once and the autopilot will handle the rest.
          </p>
        </div>
      </section>

      <section className="grid two-columns">
        <div className="card">
          <div className="card-header">
            <h3>Business defaults</h3>
          </div>
          <div className="stack">
            <div className="grid two-columns">
              <label className="field">
                <span>Business type</span>
                <input
                  type="text"
                  value={form.businessType}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, businessType: event.target.value }))
                  }
                  placeholder="Tutor"
                />
              </label>
              <label className="field">
                <span>Charge model</span>
                <select
                  value={form.chargeModel}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, chargeModel: event.target.value }))
                  }
                >
                  <option value="">Select</option>
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
            <button
              className="button button--primary"
              type="button"
              onClick={() =>
                settingsMutation.mutate({
                  business_type: form.businessType || null,
                  charge_model: form.chargeModel || null,
                  default_rate_cents: form.defaultRateCents ? Number(form.defaultRateCents) : null,
                  default_currency: form.defaultCurrency.trim().toUpperCase(),
                  follow_up_days: Number(form.followUpDays),
                  invoice_reminder_days: Number(form.invoiceReminderDays),
                })
              }
            >
              Save settings
            </button>
            {error ? <p className="form-error">{error}</p> : null}
            {success ? <p className="success-text">{success}</p> : null}
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Reminder engine</h3>
            <span className="chip">Automation</span>
          </div>
          <p className="muted">
            Generate payment reminder follow-ups for old drafts with one click.
          </p>
          {settingsQuery.data?.last_reminder_run_at ? (
            <p className="muted">
              Last run: {new Date(settingsQuery.data.last_reminder_run_at).toLocaleString()}
            </p>
          ) : (
            <p className="muted">Last run: never</p>
          )}
          <button
            className="button button--ghost"
            type="button"
            onClick={() => remindersMutation.mutate()}
          >
            {remindersMutation.isPending ? 'Running...' : 'Run reminders now'}
          </button>
        </div>
      </section>

      <section className="grid">
        <div className="card">
          <div className="card-header">
            <h3>Message templates</h3>
          </div>
          {templatesQuery.data ? (
            <div className="template-grid">
              {templatesQuery.data.map((template) => (
                <div key={template.type} className="template-card">
                  <h4>{templateLabels[template.type]}</h4>
                  <label className="field">
                    <span>Subject (optional)</span>
                    <input
                      type="text"
                      value={templateState[template.type]?.subject || ''}
                      onChange={(event) =>
                        setTemplateState((prev) => ({
                          ...prev,
                          [template.type]: {
                            ...(prev[template.type] ?? {}),
                            subject: event.target.value,
                          },
                        }))
                      }
                    />
                  </label>
                  <label className="field">
                    <span>Body</span>
                    <textarea
                      rows={3}
                      value={templateState[template.type]?.body || ''}
                      onChange={(event) =>
                        setTemplateState((prev) => ({
                          ...prev,
                          [template.type]: {
                            ...(prev[template.type] ?? {}),
                            body: event.target.value,
                          },
                        }))
                      }
                    />
                  </label>
                  <button
                    className="button button--ghost"
                    type="button"
                    onClick={() =>
                      templateMutation.mutate({
                        type: template.type,
                        subject: templateState[template.type]?.subject || undefined,
                        body: templateState[template.type]?.body || '',
                      })
                    }
                  >
                    Save template
                  </button>
                </div>
              ))}
            </div>
          ) : (
            <p className="muted">Loading templates...</p>
          )}
        </div>
      </section>

      <section className="grid">
        <div className="card">
          <div className="card-header">
            <h3>Recent activity</h3>
            <span className="chip">Audit log</span>
          </div>
          {auditQuery.isLoading ? (
            <p className="muted">Loading activity...</p>
          ) : auditQuery.data && auditQuery.data.length > 0 ? (
            <ul className="list">
              {auditQuery.data.map((entry) => (
                <li key={entry.id}>
                  <span>
                    {entry.action.replace(/[_.]/g, ' ')}
                    {entry.metadata ? (
                      <span className="muted"> — {JSON.stringify(entry.metadata)}</span>
                    ) : null}
                  </span>
                  <strong>{new Date(entry.created_at).toLocaleString()}</strong>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No recent activity yet.</p>
          )}
        </div>
      </section>

      <section className="grid two-columns">
        <div className="card">
          <div className="card-header">
            <h3>Billing</h3>
            <span className="chip">Providers</span>
          </div>
          <div className="stack">
            <label className="field">
              <span>Plan</span>
              <select
                value={billingPlan}
                onChange={(event) => {
                  setBillingPlan(event.target.value as BillingPlan)
                  setBillingPlanTouched(true)
                }}
              >
                <option value="starter">Starter ($10/mo)</option>
                <option value="pro">Pro ($29/mo)</option>
              </select>
            </label>
            <p className="muted">Choose a plan before starting checkout.</p>
          </div>
          <div className="grid">
            <div className="billing-provider">
              <p className="eyebrow">PayPal</p>
              <p className="muted">
                Status: {paypalStatusQuery.data?.status ? paypalStatusQuery.data.status : 'unknown'}
              </p>
              {paypalStatusQuery.data?.plan ? <p className="muted">Plan: {paypalStatusQuery.data.plan}</p> : null}
              {paypalStatusQuery.data?.current_period_end ? (
                <p className="muted">
                  Renews: {new Date(paypalStatusQuery.data.current_period_end).toLocaleDateString()}
                </p>
              ) : null}
              <div className="stack">
                <button
                  className="button button--primary"
                  type="button"
                  onClick={() => paypalCheckoutMutation.mutate()}
                >
                  {paypalCheckoutMutation.isPending ? 'Redirecting...' : 'Start PayPal checkout'}
                </button>
                <button
                  className="button button--ghost"
                  type="button"
                  onClick={() => paypalManageMutation.mutate()}
                >
                  {paypalManageMutation.isPending ? 'Opening...' : 'Manage PayPal subscription'}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Integrations</h3>
            <span className="chip">Google Calendar</span>
          </div>
          <div className="stack">
            <p className="muted">
              Status: {googleStatusQuery.data?.connected ? 'connected' : 'not connected'}
            </p>
            {googleStatusQuery.data?.last_sync_at ? (
              <p className="muted">
                Last sync: {new Date(googleStatusQuery.data.last_sync_at).toLocaleString()}
              </p>
            ) : null}
            {googleStatusQuery.data?.synced_from && googleStatusQuery.data?.synced_to ? (
              <p className="muted">
                Window: {new Date(googleStatusQuery.data.synced_from).toLocaleDateString()} to{' '}
                {new Date(googleStatusQuery.data.synced_to).toLocaleDateString()}
              </p>
            ) : null}
            <button
              className="button button--ghost"
              type="button"
              onClick={() => connectMutation.mutate()}
            >
              Connect Google Calendar
            </button>
            <button
              className="button button--ghost"
              type="button"
              onClick={() => syncMutation.mutate()}
            >
              Sync events
            </button>
            <button
              className="button button--ghost"
              type="button"
              onClick={() => disconnectMutation.mutate()}
            >
              Disconnect
            </button>
            <button
              className="button button--ghost"
              type="button"
              onClick={() => setShowCalendar((prev) => !prev)}
            >
              {showCalendar ? 'Hide events' : 'Show imported events'}
            </button>
            {calendarQuery.isFetching ? <p className="muted">Loading events...</p> : null}
            {showCalendar && calendarQuery.data ? (
              <ul className="list">
                {calendarQuery.data.slice(0, 5).map((event) => (
                  <li key={event.id}>
                    <span>{event.summary || 'Untitled event'}</span>
                    <strong>{new Date(event.start_at).toLocaleDateString()}</strong>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        </div>
      </section>

      <section className="grid">
        <div className="card">
          <div className="card-header">
            <h3>Referral program</h3>
            <span className="chip">Give 1 month</span>
          </div>
          <p className="muted">
            Share your invite code to give peers one month free. Referrals: {referralCount}
          </p>
          <div className="referral-row">
            <input type="text" readOnly value={referralCode || 'Generating...'} />
            <button
              className="button button--ghost"
              type="button"
              onClick={() => {
                if (referralCode && navigator.clipboard?.writeText) {
                  navigator.clipboard.writeText(referralCode)
                }
              }}
            >
              Copy code
            </button>
          </div>
          {referralRows.length > 0 ? (
            <ul className="list">
              {referralRows.slice(0, 5).map((referral) => (
                <li key={referral.id}>
                  <span>Referral {referral.code}</span>
                  <strong>{referral.status}</strong>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No referrals yet.</p>
          )}
        </div>
      </section>
    </div>
  )
}

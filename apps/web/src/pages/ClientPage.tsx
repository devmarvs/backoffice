import { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createPackage,
  fetchClient,
  fetchFollowUps,
  fetchInvoiceDrafts,
  fetchPackages,
  fetchWorkEvents,
  updateClient,
  usePackageSession,
} from '../api/client'
import { CURRENCIES } from '../data/currencies'

export function ClientPage() {
  const { id } = useParams()
  const clientId = Number(id)
  const queryClient = useQueryClient()
  const [clientForm, setClientForm] = useState({ name: '', email: '', phone: '' })
  const [packageForm, setPackageForm] = useState({
    title: '',
    totalSessions: '10',
    usedSessions: '0',
    priceCents: '',
    currency: 'EUR',
  })
  const [error, setError] = useState<string | null>(null)
  const [generatedMessage, setGeneratedMessage] = useState<string | null>(null)

  const clientQuery = useQuery({
    queryKey: ['clients', clientId],
    queryFn: () => fetchClient(clientId),
    enabled: Number.isFinite(clientId) && clientId > 0,
  })

  const workEventsQuery = useQuery({
    queryKey: ['work-events', clientId],
    queryFn: () => fetchWorkEvents({ clientId }),
    enabled: Number.isFinite(clientId) && clientId > 0,
  })

  const packagesQuery = useQuery({
    queryKey: ['packages', clientId],
    queryFn: () => fetchPackages(clientId),
    enabled: Number.isFinite(clientId) && clientId > 0,
  })

  const draftsQuery = useQuery({
    queryKey: ['invoice-drafts', 'draft'],
    queryFn: () => fetchInvoiceDrafts('draft'),
  })

  const followUpsQuery = useQuery({
    queryKey: ['follow-ups', 'open'],
    queryFn: () => fetchFollowUps('open'),
  })

  useEffect(() => {
    if (clientQuery.data) {
      setClientForm({
        name: clientQuery.data.name,
        email: clientQuery.data.email || '',
        phone: clientQuery.data.phone || '',
      })
    }
  }, [clientQuery.data])

  useEffect(() => {
    setGeneratedMessage(null)
  }, [clientId])

  const updateClientMutation = useMutation({
    mutationFn: (payload: { name?: string; email?: string; phone?: string }) =>
      updateClient(clientId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients', clientId] })
      queryClient.invalidateQueries({ queryKey: ['clients'] })
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not update client.')
    },
  })

  const createPackageMutation = useMutation({
    mutationFn: createPackage,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packages', clientId] })
      setPackageForm({
        title: '',
        totalSessions: '10',
        usedSessions: '0',
        priceCents: '',
        currency: packageForm.currency,
      })
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not create package.')
    },
  })

  const useSessionMutation = useMutation({
    mutationFn: usePackageSession,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['packages', clientId] })
      setError(null)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not update package usage.')
    },
  })

  const clientDrafts = useMemo(
    () => (draftsQuery.data ?? []).filter((draft) => draft.client_id === clientId),
    [draftsQuery.data, clientId]
  )
  const clientFollowUps = useMemo(
    () => (followUpsQuery.data ?? []).filter((followUp) => followUp.client_id === clientId),
    [followUpsQuery.data, clientId]
  )

  if (!Number.isFinite(clientId) || clientId <= 0) {
    return (
      <div className="page">
        <p className="muted">Invalid client selection.</p>
      </div>
    )
  }

  return (
    <div className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">Client overview</p>
          <h2>{clientQuery.data?.name || `Client #${id}`}</h2>
          <p className="muted">Timeline, packages, and drafts tied to this client.</p>
        </div>
        <button
          className="button button--ghost"
          type="button"
          onClick={() => {
            if (clientFollowUps.length > 0) {
              setGeneratedMessage(clientFollowUps[0].suggested_message)
              return
            }
            const name = clientQuery.data?.name || 'your client'
            setGeneratedMessage(`Follow up with ${name} about your recent session.`)
          }}
        >
          Generate message
        </button>
      </section>
      {generatedMessage ? (
        <section className="card">
          <div className="card-header">
            <h3>Suggested message</h3>
            <button
              className="button button--ghost"
              type="button"
              onClick={() => {
                if (navigator.clipboard?.writeText) {
                  navigator.clipboard.writeText(generatedMessage)
                }
              }}
            >
              Copy
            </button>
          </div>
          <p className="muted">{generatedMessage}</p>
        </section>
      ) : null}

      <section className="grid two-columns">
        <div className="card">
          <div className="card-header">
            <h3>Client details</h3>
            <span className="chip">Editable</span>
          </div>
          <div className="stack">
            <label className="field">
              <span>Name</span>
              <input
                type="text"
                value={clientForm.name}
                onChange={(event) =>
                  setClientForm((prev) => ({ ...prev, name: event.target.value }))
                }
              />
            </label>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                value={clientForm.email}
                onChange={(event) =>
                  setClientForm((prev) => ({ ...prev, email: event.target.value }))
                }
              />
            </label>
            <label className="field">
              <span>Phone</span>
              <input
                type="tel"
                value={clientForm.phone}
                onChange={(event) =>
                  setClientForm((prev) => ({ ...prev, phone: event.target.value }))
                }
              />
            </label>
            <button
              className="button button--primary"
              type="button"
              onClick={() => {
                if (clientForm.name.trim() === '') {
                  setError('Client name is required.')
                  return
                }
                updateClientMutation.mutate({
                  name: clientForm.name.trim(),
                  email: clientForm.email.trim() || undefined,
                  phone: clientForm.phone.trim() || undefined,
                })
              }}
            >
              Save client
            </button>
            {error ? <p className="form-error">{error}</p> : null}
          </div>
        </div>
        <div className="card">
          <div className="card-header">
            <h3>Work timeline</h3>
            <span className="chip">Latest activity</span>
          </div>
          {workEventsQuery.isLoading ? (
            <p className="muted">Loading sessions...</p>
          ) : workEventsQuery.data && workEventsQuery.data.length > 0 ? (
            <ul className="timeline">
              {workEventsQuery.data.slice(0, 6).map((event) => (
                <li key={event.id}>
                  <span className="timeline-dot" />
                  <div>
                    <strong>{event.type.replace('_', ' ')}</strong>
                    <p className="muted">
                      {new Date(event.start_at).toLocaleString()} - {event.duration_minutes}m -{' '}
                      {event.billable ? 'Billable' : 'Non-billable'}
                    </p>
                    {event.notes ? <p className="muted">{event.notes}</p> : null}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No work events yet.</p>
          )}
        </div>
      </section>

      <section className="grid two-columns">
        <div className="card">
          <div className="card-header">
            <h3>Packages</h3>
            <span className="chip">Sessions remaining</span>
          </div>
          {packagesQuery.isLoading ? (
            <p className="muted">Loading packages...</p>
          ) : packagesQuery.data && packagesQuery.data.length > 0 ? (
            <div className="stack">
              {packagesQuery.data.map((pkg) => {
                const remaining = pkg.total_sessions - pkg.used_sessions
                const ratio = pkg.total_sessions > 0 ? (remaining / pkg.total_sessions) * 100 : 0
                return (
                  <div key={pkg.id} className="package-row">
                    <div>
                      <strong>{pkg.title}</strong>
                      <p className="muted">
                        {remaining} of {pkg.total_sessions} remaining
                      </p>
                    </div>
                    <div className="meter">
                      <div className="meter-bar" style={{ width: `${Math.max(ratio, 5)}%` }} />
                    </div>
                    <button
                      className="button button--ghost"
                      type="button"
                      onClick={() => useSessionMutation.mutate(pkg.id)}
                    >
                      Use session
                    </button>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="muted">No packages yet for this client.</p>
          )}
        </div>
        <div className="card">
          <div className="card-header">
            <h3>Add package</h3>
          </div>
          <form
            className="stack"
            onSubmit={(event) => {
              event.preventDefault()
              if (packageForm.title.trim() === '') {
                setError('Package title is required.')
                return
              }
              const totalSessions = Number(packageForm.totalSessions)
              const usedSessions = Number(packageForm.usedSessions)
              if (!Number.isFinite(totalSessions) || totalSessions <= 0) {
                setError('Total sessions must be greater than 0.')
                return
              }
              if (!Number.isFinite(usedSessions) || usedSessions < 0) {
                setError('Used sessions must be 0 or more.')
                return
              }
              if (usedSessions > totalSessions) {
                setError('Used sessions cannot exceed total sessions.')
                return
              }
              const currency = packageForm.currency.trim().toUpperCase()
              if (currency === '') {
                setError('Select a currency.')
                return
              }
              createPackageMutation.mutate({
                client_id: clientId,
                title: packageForm.title.trim(),
                total_sessions: totalSessions,
                used_sessions: usedSessions,
                price_cents: packageForm.priceCents.trim()
                  ? Number(packageForm.priceCents)
                  : null,
                currency,
              })
            }}
          >
            <label className="field">
              <span>Title</span>
              <input
                type="text"
                value={packageForm.title}
                onChange={(event) =>
                  setPackageForm((prev) => ({ ...prev, title: event.target.value }))
                }
              />
            </label>
            <div className="grid two-columns">
              <label className="field">
                <span>Total sessions</span>
                <input
                  type="number"
                  min="1"
                  value={packageForm.totalSessions}
                  onChange={(event) =>
                    setPackageForm((prev) => ({ ...prev, totalSessions: event.target.value }))
                  }
                />
              </label>
              <label className="field">
                <span>Used sessions</span>
                <input
                  type="number"
                  min="0"
                  value={packageForm.usedSessions}
                  onChange={(event) =>
                    setPackageForm((prev) => ({ ...prev, usedSessions: event.target.value }))
                  }
                />
              </label>
            </div>
            <div className="grid two-columns">
              <label className="field">
                <span>Price (cents)</span>
                <input
                  type="number"
                  min="0"
                  value={packageForm.priceCents}
                  onChange={(event) =>
                    setPackageForm((prev) => ({ ...prev, priceCents: event.target.value }))
                  }
                />
              </label>
              <label className="field">
                <span>Currency</span>
                <select
                  value={packageForm.currency}
                  onChange={(event) =>
                    setPackageForm((prev) => ({ ...prev, currency: event.target.value }))
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
            <button className="button button--primary" type="submit" disabled={createPackageMutation.isPending}>
              {createPackageMutation.isPending ? 'Saving...' : 'Create package'}
            </button>
            {error ? <p className="form-error">{error}</p> : null}
          </form>
        </div>
      </section>

      <section className="grid">
        <div className="card">
          <div className="card-header">
            <h3>Outstanding drafts</h3>
          </div>
          {draftsQuery.isLoading ? (
            <p className="muted">Loading drafts...</p>
          ) : clientDrafts.length > 0 ? (
            <ul className="list">
              {clientDrafts.map((draft) => (
                <li key={draft.id}>
                  <span>Draft #{draft.id}</span>
                  <strong>
                    {draft.currency} {(draft.amount_cents / 100).toFixed(2)}
                  </strong>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">Draft invoices will appear here for quick review.</p>
          )}
        </div>
        <div className="card">
          <div className="card-header">
            <h3>Follow-ups</h3>
          </div>
          {followUpsQuery.isLoading ? (
            <p className="muted">Loading follow-ups...</p>
          ) : clientFollowUps.length > 0 ? (
            <ul className="list">
              {clientFollowUps.map((followUp) => (
                <li key={followUp.id}>
                  <span>{followUp.suggested_message}</span>
                  <strong>{new Date(followUp.due_at).toLocaleDateString()}</strong>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No follow-ups for this client.</p>
          )}
        </div>
      </section>
    </div>
  )
}

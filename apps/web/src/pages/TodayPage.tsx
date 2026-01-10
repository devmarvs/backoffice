import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  dismissFollowUp,
  fetchClients,
  fetchCalendarSuggestions,
  fetchFollowUps,
  fetchInvoiceDrafts,
  fetchPackages,
  fetchPayPalStatus,
  logWorkEventFromCalendar,
  markFollowUpDone,
  reopenFollowUp,
  sendFollowUpEmail,
  voiceLogWorkEvent,
} from '../api/client'
import { useQuickLogStore } from '../state/quickLogStore'

const toDateInput = (date: Date) => date.toISOString().slice(0, 10)

export function TodayPage() {
  const openQuickLog = useQuickLogStore((state) => state.open)
  const queryClient = useQueryClient()
  const [followUpStatus, setFollowUpStatus] = useState<'open' | 'done' | 'dismissed'>('open')
  const invoiceQuery = useQuery({
    queryKey: ['invoice-drafts'],
    queryFn: () => fetchInvoiceDrafts('draft'),
  })
  const followUpQuery = useQuery({
    queryKey: ['follow-ups', followUpStatus],
    queryFn: () => fetchFollowUps(followUpStatus),
  })
  const clientsQuery = useQuery({
    queryKey: ['clients', 'all'],
    queryFn: () => fetchClients(),
  })
  const billingStatusQuery = useQuery({
    queryKey: ['billing-status', 'paypal'],
    queryFn: fetchPayPalStatus,
  })
  const hasProAccess =
    billingStatusQuery.data?.status === 'active' && billingStatusQuery.data?.plan === 'pro'
  const [packageClientId, setPackageClientId] = useState<number | null>(null)
  const [voiceClientId, setVoiceClientId] = useState<number | null>(null)
  const [voiceTranscript, setVoiceTranscript] = useState('')
  const [voiceDuration, setVoiceDuration] = useState('')
  const [voiceError, setVoiceError] = useState<string | null>(null)
  const [followUpFrom, setFollowUpFrom] = useState('')
  const [followUpTo, setFollowUpTo] = useState('')
  const [suggestionClientId, setSuggestionClientId] = useState<number | null>(null)
  const [suggestionFrom, setSuggestionFrom] = useState(() => toDateInput(new Date()))
  const [suggestionTo, setSuggestionTo] = useState(() =>
    toDateInput(new Date(Date.now() + 7 * 86400000))
  )
  const [suggestionFilter, setSuggestionFilter] = useState('')

  useEffect(() => {
    if (!packageClientId && clientsQuery.data && clientsQuery.data.length > 0) {
      setPackageClientId(clientsQuery.data[0].id)
    }
    if (!voiceClientId && clientsQuery.data && clientsQuery.data.length > 0) {
      setVoiceClientId(clientsQuery.data[0].id)
    }
    if (!suggestionClientId && clientsQuery.data && clientsQuery.data.length > 0) {
      setSuggestionClientId(clientsQuery.data[0].id)
    }
  }, [clientsQuery.data, packageClientId, voiceClientId, suggestionClientId])

  const suggestionsQuery = useQuery({
    queryKey: ['calendar-suggestions', suggestionFrom, suggestionTo],
    queryFn: () => fetchCalendarSuggestions({ from: suggestionFrom, to: suggestionTo }),
    enabled: hasProAccess,
  })

  const packagesQuery = useQuery({
    queryKey: ['packages', packageClientId],
    queryFn: () => fetchPackages(packageClientId as number),
    enabled: packageClientId !== null,
  })

  const followUpDoneMutation = useMutation({
    mutationFn: markFollowUpDone,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
    },
  })

  const followUpDismissMutation = useMutation({
    mutationFn: dismissFollowUp,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
    },
  })

  const followUpReopenMutation = useMutation({
    mutationFn: reopenFollowUp,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
    },
  })

  const followUpEmailMutation = useMutation({
    mutationFn: ({ id, subject, message }: { id: number; subject?: string; message?: string }) =>
      sendFollowUpEmail(id, { subject, message }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
    },
  })

  const suggestionLogMutation = useMutation({
    mutationFn: ({ id, clientId }: { id: number; clientId: number }) =>
      logWorkEventFromCalendar(id, { client_id: clientId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['calendar-suggestions'] })
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
      queryClient.invalidateQueries({ queryKey: ['work-events'] })
    },
  })

  const voiceMutation = useMutation({
    mutationFn: voiceLogWorkEvent,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
      queryClient.invalidateQueries({ queryKey: ['work-events'] })
      setVoiceTranscript('')
      setVoiceDuration('')
      setVoiceError(null)
    },
    onError: (err) => {
      setVoiceError(err instanceof Error ? err.message : 'Could not log from transcript.')
    },
  })

  const clientNameById = useMemo(() => {
    const map = new Map<number, string>()
    clientsQuery.data?.forEach((client) => map.set(client.id, client.name))
    return map
  }, [clientsQuery.data])

  const suggestionRows = useMemo(() => {
    const suggestions = suggestionsQuery.data ?? []
    const clients = clientsQuery.data ?? []
    const filterText = suggestionFilter.trim().toLowerCase()

    return suggestions
      .map((event) => {
        const summary = (event.summary || '').toLowerCase()
        const matchedClient = clients.find((client) =>
          summary.includes(client.name.toLowerCase())
        )
        return { event, matchedClient }
      })
      .filter(({ event, matchedClient }) => {
        if (filterText === '') {
          return true
        }
        const summary = (event.summary || '').toLowerCase()
        const clientName = matchedClient?.name.toLowerCase() || ''
        return summary.includes(filterText) || clientName.includes(filterText)
      })
  }, [clientsQuery.data, suggestionFilter, suggestionsQuery.data])

  return (
    <div className="page">
      <section className="hero">
        <div>
          <p className="eyebrow">Log in 5 seconds</p>
          <h2>Everything starts with a Work Event.</h2>
          <p className="muted">
            Capture a session and let the autopilot draft invoices, follow-ups, and package
            updates.
          </p>
        </div>
        <div className="hero-actions">
          <button className="button button--primary" onClick={openQuickLog}>
            Log session
          </button>
          <button className="button button--ghost" onClick={openQuickLog}>
            Use template
          </button>
        </div>
      </section>

      <section className="grid">
        <div className="card">
          <div className="card-header">
            <h3>To invoice</h3>
            <span className="chip">Drafts</span>
          </div>
          {invoiceQuery.isLoading ? (
            <p className="muted">Loading drafts...</p>
          ) : invoiceQuery.data && invoiceQuery.data.length > 0 ? (
            <ul className="list">
              {invoiceQuery.data.slice(0, 3).map((draft) => (
                <li key={draft.id}>
                  <span>
                    Draft #{draft.id} -{' '}
                    {clientNameById.get(draft.client_id) ?? `Client ${draft.client_id}`}
                  </span>
                  <strong>
                    {draft.currency} {(draft.amount_cents / 100).toFixed(2)}
                  </strong>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No drafts yet. Log a session to start.</p>
          )}
        </div>
        <div className="card">
          <div className="card-header">
            <h3>Follow-ups due</h3>
            <div className="chip-group">
              {(['open', 'done', 'dismissed'] as const).map((value) => (
                <button
                  key={value}
                  type="button"
                  className={`chip-button${followUpStatus === value ? ' active' : ''}`}
                  onClick={() => setFollowUpStatus(value)}
                >
                  {value}
                </button>
              ))}
              <button
                className="button button--ghost"
                type="button"
                onClick={() => {
                  const params = new URLSearchParams({ status: followUpStatus })
                  if (followUpFrom) params.set('from', followUpFrom)
                  if (followUpTo) params.set('to', followUpTo)
                  window.open(`/api/follow-ups/export?${params.toString()}`, '_blank', 'noopener')
                }}
              >
                Export CSV
              </button>
            </div>
          </div>
          <div className="grid two-columns">
            <label className="field">
              <span>From</span>
              <input
                type="date"
                value={followUpFrom}
                onChange={(event) => setFollowUpFrom(event.target.value)}
              />
            </label>
            <label className="field">
              <span>To</span>
              <input
                type="date"
                value={followUpTo}
                onChange={(event) => setFollowUpTo(event.target.value)}
              />
            </label>
          </div>
          {followUpQuery.isLoading ? (
            <p className="muted">Loading follow-ups...</p>
          ) : followUpQuery.data && followUpQuery.data.length > 0 ? (
            <ul className="list">
              {followUpQuery.data.slice(0, 3).map((followUp) => (
                <li key={followUp.id} className="list-row">
                  <div className="list-row-main">
                    <span>{followUp.suggested_message}</span>
                    <span className="muted">
                      {clientNameById.get(followUp.client_id) ?? `Client ${followUp.client_id}`} -{' '}
                      {new Date(followUp.due_at).toLocaleDateString()}
                    </span>
                  </div>
                  <div className="list-actions">
                    {followUp.status === 'open' ? (
                      <>
                        <button
                          className="button button--ghost"
                          type="button"
                          onClick={() => followUpDoneMutation.mutate(followUp.id)}
                        >
                          Done
                        </button>
                        <button
                          className="button button--ghost"
                          type="button"
                          onClick={() => followUpDismissMutation.mutate(followUp.id)}
                        >
                          Dismiss
                        </button>
                        <button
                          className="button button--ghost"
                          type="button"
                          onClick={() => {
                            const clientName =
                              clientNameById.get(followUp.client_id) ?? 'your session'
                            const subject = window.prompt(
                              'Email subject',
                              `Follow-up for ${clientName}`
                            )
                            if (subject === null) {
                              return
                            }
                            const message = window.prompt(
                              'Email message',
                              followUp.suggested_message || ''
                            )
                            if (message === null) {
                              return
                            }
                            followUpEmailMutation.mutate({
                              id: followUp.id,
                              subject: subject.trim() || undefined,
                              message: message.trim() || undefined,
                            })
                          }}
                        >
                          Email
                        </button>
                      </>
                    ) : (
                      <button
                        className="button button--ghost"
                        type="button"
                        onClick={() => followUpReopenMutation.mutate(followUp.id)}
                      >
                        Reopen
                      </button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No follow-ups queued yet.</p>
          )}
        </div>
        <div className="card accent-card">
          <div className="card-header">
            <h3>Packages overview</h3>
            <span className="chip">Autopilot watch</span>
          </div>
          {clientsQuery.data && clientsQuery.data.length > 0 ? (
            <>
              <label className="field">
                <span>Client</span>
                <select
                  value={packageClientId ?? ''}
                  onChange={(event) => setPackageClientId(Number(event.target.value))}
                >
                  {clientsQuery.data.map((client) => (
                    <option key={client.id} value={client.id}>
                      {client.name}
                    </option>
                  ))}
                </select>
              </label>
              {packagesQuery.isLoading ? (
                <p className="muted">Loading packages...</p>
              ) : packagesQuery.data && packagesQuery.data.length > 0 ? (
                <ul className="list">
                  {packagesQuery.data.slice(0, 3).map((pkg) => {
                    const remaining = pkg.total_sessions - pkg.used_sessions
                    return (
                      <li key={pkg.id}>
                        <span>{pkg.title}</span>
                        <strong>
                          {remaining} of {pkg.total_sessions} left
                        </strong>
                      </li>
                    )
                  })}
                </ul>
              ) : (
                <p className="muted">No packages yet for this client.</p>
              )}
            </>
          ) : (
            <p className="muted">Add a client to start tracking packages.</p>
          )}
        </div>
        <div className="card">
          <div className="card-header">
            <h3>Calendar suggestions</h3>
            <span className="chip">Google Calendar</span>
          </div>
          {!hasProAccess ? (
            <p className="muted">Upgrade to Pro to unlock Google Calendar suggestions.</p>
          ) : clientsQuery.data && clientsQuery.data.length > 0 ? (
            <div className="stack">
              <label className="field">
                <span>Default client</span>
                <select
                  value={suggestionClientId ?? ''}
                  onChange={(event) => setSuggestionClientId(Number(event.target.value))}
                >
                  {clientsQuery.data.map((client) => (
                    <option key={client.id} value={client.id}>
                      {client.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Filter by name</span>
                <input
                  type="text"
                  value={suggestionFilter}
                  onChange={(event) => setSuggestionFilter(event.target.value)}
                  placeholder="Search summary or client"
                />
              </label>
              <label className="field">
                <span>From</span>
                <input
                  type="date"
                  value={suggestionFrom}
                  onChange={(event) => setSuggestionFrom(event.target.value)}
                />
              </label>
              <label className="field">
                <span>To</span>
                <input
                  type="date"
                  value={suggestionTo}
                  onChange={(event) => setSuggestionTo(event.target.value)}
                />
              </label>
              {suggestionsQuery.isLoading ? (
                <p className="muted">Loading suggestions...</p>
              ) : suggestionRows.length > 0 ? (
                <ul className="list">
                  {suggestionRows.slice(0, 3).map(({ event, matchedClient }) => (
                    <li key={event.id} className="list-row">
                      <div className="list-row-main">
                        <span>{event.summary || 'Untitled event'}</span>
                        <span className="muted">
                          {new Date(event.start_at).toLocaleString()}
                        </span>
                        {matchedClient ? (
                          <span className="muted">Matched client: {matchedClient.name}</span>
                        ) : null}
                      </div>
                      <div className="list-actions">
                        <button
                          className="button button--ghost"
                          type="button"
                          disabled={
                            (!suggestionClientId && !matchedClient) ||
                            suggestionLogMutation.isPending
                          }
                          onClick={() => {
                            const clientId = matchedClient?.id ?? suggestionClientId
                            if (clientId) {
                              suggestionLogMutation.mutate({ id: event.id, clientId })
                            }
                          }}
                        >
                          Create work event
                        </button>
                      </div>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="muted">No calendar suggestions in this window.</p>
              )}
            </div>
          ) : (
            <p className="muted">Add a client to enable calendar suggestions.</p>
          )}
        </div>
        <div className="card">
          <div className="card-header">
            <h3>Voice log</h3>
            <span className="chip">Beta</span>
          </div>
          {clientsQuery.data && clientsQuery.data.length > 0 ? (
            <div className="stack">
              <label className="field">
                <span>Client</span>
                <select
                  value={voiceClientId ?? ''}
                  onChange={(event) => setVoiceClientId(Number(event.target.value))}
                >
                  {clientsQuery.data.map((client) => (
                    <option key={client.id} value={client.id}>
                      {client.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Transcript</span>
                <textarea
                  rows={3}
                  value={voiceTranscript}
                  onChange={(event) => setVoiceTranscript(event.target.value)}
                  placeholder="Paste a short summary of the session."
                />
              </label>
              <label className="field">
                <span>Duration (minutes)</span>
                <input
                  type="number"
                  min="15"
                  step="15"
                  value={voiceDuration}
                  onChange={(event) => setVoiceDuration(event.target.value)}
                  placeholder="Optional"
                />
              </label>
              <button
                className="button button--primary"
                type="button"
                disabled={
                  voiceMutation.isPending ||
                  voiceTranscript.trim() === '' ||
                  !voiceClientId
                }
                onClick={() => {
                  const duration = voiceDuration.trim() ? Number(voiceDuration) : undefined
                  if (duration !== undefined && (!Number.isFinite(duration) || duration <= 0)) {
                    setVoiceError('Duration must be greater than 0.')
                    return
                  }
                  setVoiceError(null)
                  voiceMutation.mutate({
                    transcript: voiceTranscript.trim(),
                    client_id: voiceClientId ?? undefined,
                    duration_minutes: duration,
                  })
                }}
              >
                {voiceMutation.isPending ? 'Logging...' : 'Log from transcript'}
              </button>
              {voiceError ? <p className="form-error">{voiceError}</p> : null}
            </div>
          ) : (
            <p className="muted">Add a client to enable voice logging.</p>
          )}
        </div>
      </section>
    </div>
  )
}

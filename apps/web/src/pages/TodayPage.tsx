import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  dismissFollowUp,
  fetchClients,
  fetchFollowUps,
  fetchInvoiceDrafts,
  fetchPackages,
  markFollowUpDone,
  voiceLogWorkEvent,
} from '../api/client'
import { useQuickLogStore } from '../state/quickLogStore'

export function TodayPage() {
  const openQuickLog = useQuickLogStore((state) => state.open)
  const queryClient = useQueryClient()
  const invoiceQuery = useQuery({
    queryKey: ['invoice-drafts'],
    queryFn: () => fetchInvoiceDrafts('draft'),
  })
  const followUpQuery = useQuery({
    queryKey: ['follow-ups'],
    queryFn: () => fetchFollowUps('open'),
  })
  const clientsQuery = useQuery({
    queryKey: ['clients', 'all'],
    queryFn: () => fetchClients(),
  })
  const [packageClientId, setPackageClientId] = useState<number | null>(null)
  const [voiceClientId, setVoiceClientId] = useState<number | null>(null)
  const [voiceTranscript, setVoiceTranscript] = useState('')
  const [voiceDuration, setVoiceDuration] = useState('')
  const [voiceError, setVoiceError] = useState<string | null>(null)

  useEffect(() => {
    if (!packageClientId && clientsQuery.data && clientsQuery.data.length > 0) {
      setPackageClientId(clientsQuery.data[0].id)
    }
    if (!voiceClientId && clientsQuery.data && clientsQuery.data.length > 0) {
      setVoiceClientId(clientsQuery.data[0].id)
    }
  }, [clientsQuery.data, packageClientId, voiceClientId])

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
            <span className="chip">Next 3 days</span>
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

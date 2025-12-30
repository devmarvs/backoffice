import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { z } from 'zod'
import { createClient, fetchClients, fetchSettings, logWorkEvent } from '../api/client'
import type { Client } from '../api/client'
import { useQuickLogStore } from '../state/quickLogStore'
import { CURRENCIES } from '../data/currencies'

const quickLogSchema = z.object({
  client_id: z.number().int().positive(),
  type: z.enum(['session', 'no_show', 'admin']),
  start_at: z.string().datetime(),
  duration_minutes: z.number().int().positive(),
  billable: z.boolean(),
  notes: z.string().optional(),
  rate_cents: z.number().int().nonnegative().optional(),
  currency: z.string().length(3).optional(),
})

const defaultStartAt = () => {
  const now = new Date()
  const offsetMs = now.getTimezoneOffset() * 60_000
  return new Date(now.getTime() - offsetMs).toISOString().slice(0, 16)
}

export function QuickLogModal() {
  const { isOpen, close } = useQuickLogStore()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    clientQuery: '',
    type: 'session',
    startAt: defaultStartAt(),
    durationMinutes: '60',
    billable: true,
    rateCents: '',
    notes: '',
    currency: 'EUR',
  })
  const [error, setError] = useState<string | null>(null)
  const [selectedClient, setSelectedClient] = useState<Client | null>(null)
  const [defaultsApplied, setDefaultsApplied] = useState(false)

  const settingsQuery = useQuery({
    queryKey: ['settings'],
    queryFn: fetchSettings,
  })

  const clientsQuery = useQuery({
    queryKey: ['clients', form.clientQuery],
    queryFn: () => fetchClients(form.clientQuery.trim() || undefined),
    enabled: isOpen,
  })

  const mutation = useMutation({
    mutationFn: logWorkEvent,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      queryClient.invalidateQueries({ queryKey: ['follow-ups'] })
      queryClient.invalidateQueries({ queryKey: ['work-events'] })
      queryClient.invalidateQueries({ queryKey: ['packages'] })
      setError(null)
      close()
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Something went wrong.')
    },
  })

  useEffect(() => {
    if (!defaultsApplied && settingsQuery.data?.default_currency) {
      setForm((prev) => ({
        ...prev,
        currency: prev.currency || settingsQuery.data.default_currency || 'EUR',
      }))
      setDefaultsApplied(true)
    }
  }, [defaultsApplied, settingsQuery.data])

  const payload = useMemo(() => {
    const startAtDate = new Date(form.startAt)
    const startAtIso = Number.isNaN(startAtDate.getTime())
      ? new Date().toISOString()
      : startAtDate.toISOString()
    return {
      client_id: selectedClient?.id ?? 0,
      type: form.type as 'session' | 'no_show' | 'admin',
      start_at: startAtIso,
      duration_minutes: Number(form.durationMinutes),
      billable: form.billable,
      notes: form.notes.trim() || undefined,
      rate_cents: form.rateCents ? Number(form.rateCents) : undefined,
      currency: form.currency.trim().toUpperCase() || undefined,
    }
  }, [form, selectedClient])

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault()
    const parsed = quickLogSchema.safeParse(payload)
    if (!parsed.success) {
      setError(parsed.error.errors[0]?.message || 'Check the form fields.')
      return
    }

    mutation.mutate(parsed.data)
  }

  const createClientMutation = useMutation({
    mutationFn: createClient,
    onSuccess: (client) => {
      setSelectedClient(client)
      setForm((prev) => ({ ...prev, clientQuery: client.name }))
      queryClient.invalidateQueries({ queryKey: ['clients'] })
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not create client.')
    },
  })

  const clients = clientsQuery.data ?? []
  const normalizedQuery = form.clientQuery.trim().toLowerCase()
  const exactMatch = clients.find(
    (client) => client.name.toLowerCase() === normalizedQuery
  )
  const showCreateOption = normalizedQuery !== '' && !exactMatch

  if (!isOpen) {
    return null
  }

  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true">
      <div className="modal">
        <div className="modal-header">
          <div>
            <p className="eyebrow">Quick Log</p>
            <h2>Capture a session in seconds.</h2>
          </div>
          <button className="button button--ghost" onClick={close} type="button">
            Close
          </button>
        </div>
        <form className="modal-grid" onSubmit={handleSubmit}>
          <label className="field">
            <span>Client</span>
            <input
              type="text"
              value={form.clientQuery}
              onChange={(event) => {
                const value = event.target.value
                setForm((prev) => ({ ...prev, clientQuery: value }))
                setSelectedClient(null)
              }}
              placeholder="Search or create a client"
            />
            {selectedClient ? (
              <span className="input-hint">
                Selected: {selectedClient.name}
              </span>
            ) : null}
            {clientsQuery.isLoading ? (
              <span className="input-hint">Searching clients...</span>
            ) : clients.length > 0 ? (
              <div className="dropdown">
                {clients.slice(0, 5).map((client) => (
                  <button
                    key={client.id}
                    type="button"
                    className="dropdown-item"
                    onClick={() => {
                      setSelectedClient(client)
                      setForm((prev) => ({ ...prev, clientQuery: client.name }))
                    }}
                  >
                    <span>{client.name}</span>
                    {client.email ? <span className="muted">{client.email}</span> : null}
                  </button>
                ))}
                {showCreateOption ? (
                  <button
                    type="button"
                    className="dropdown-item dropdown-item--accent"
                    onClick={() =>
                      createClientMutation.mutate({ name: form.clientQuery.trim() })
                    }
                  >
                    Create "{form.clientQuery.trim()}"
                  </button>
                ) : null}
              </div>
            ) : showCreateOption ? (
              <div className="dropdown">
                <button
                  type="button"
                  className="dropdown-item dropdown-item--accent"
                  onClick={() => createClientMutation.mutate({ name: form.clientQuery.trim() })}
                >
                  Create "{form.clientQuery.trim()}"
                </button>
              </div>
            ) : null}
          </label>
          <label className="field">
            <span>Type</span>
            <select
              value={form.type}
              onChange={(event) => setForm((prev) => ({ ...prev, type: event.target.value }))}
            >
              <option value="session">Session</option>
              <option value="no_show">No-show</option>
              <option value="admin">Admin</option>
            </select>
          </label>
          <label className="field">
            <span>Start time</span>
            <input
              type="datetime-local"
              value={form.startAt}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, startAt: event.target.value }))
              }
            />
          </label>
          <label className="field">
            <span>Duration (minutes)</span>
            <div className="chip-group">
              {[30, 60, 90].map((minutes) => (
                <button
                  key={minutes}
                  type="button"
                  className={`chip-button${
                    Number(form.durationMinutes) === minutes ? ' active' : ''
                  }`}
                  onClick={() =>
                    setForm((prev) => ({ ...prev, durationMinutes: String(minutes) }))
                  }
                >
                  {minutes}m
                </button>
              ))}
              <input
                type="number"
                min="15"
                step="15"
                value={form.durationMinutes}
                onChange={(event) =>
                  setForm((prev) => ({ ...prev, durationMinutes: event.target.value }))
                }
              />
            </div>
          </label>
          <label className="field">
            <span>Rate (cents)</span>
            <input
              type="number"
              min="0"
              value={form.rateCents}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, rateCents: event.target.value }))
              }
              placeholder={
                settingsQuery.data?.default_rate_cents
                  ? `Default ${settingsQuery.data.default_rate_cents}`
                  : 'Optional'
              }
              disabled={!form.billable}
            />
          </label>
          <label className="field">
            <span>Currency</span>
            <select
              value={form.currency}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, currency: event.target.value }))
              }
              disabled={!form.billable}
            >
              {CURRENCIES.map((currencyOption) => (
                <option key={currencyOption.code} value={currencyOption.code}>
                  {currencyOption.label}
                </option>
              ))}
            </select>
          </label>
          <label className="field field--full">
            <span>Notes</span>
            <textarea
              rows={3}
              value={form.notes}
              onChange={(event) => setForm((prev) => ({ ...prev, notes: event.target.value }))}
              placeholder="Key takeaways, next steps, or admin notes."
            />
          </label>
          <label className="field field--checkbox">
            <input
              type="checkbox"
              checked={form.billable}
              onChange={(event) =>
                setForm((prev) => ({ ...prev, billable: event.target.checked }))
              }
            />
            <span>Billable session</span>
          </label>
          {error ? <p className="form-error">{error}</p> : null}
          <div className="modal-actions">
            <button className="button button--ghost" type="button" onClick={close}>
              Cancel
            </button>
            <button className="button button--primary" type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? 'Logging...' : 'Log session'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  bulkMarkInvoicesSent,
  fetchClients,
  fetchInvoiceDrafts,
  fetchInvoiceDraftsBulk,
  markInvoicePaid,
  markInvoiceSent,
  sendInvoiceEmail,
  voidInvoiceDraft,
} from '../api/client'

const toDateInput = (date: Date) => date.toISOString().slice(0, 10)

export function BillingPage() {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<'draft' | 'sent' | 'paid' | 'void'>('draft')
  const [bulkFrom, setBulkFrom] = useState(() => toDateInput(new Date(Date.now() - 6 * 86400000)))
  const [bulkTo, setBulkTo] = useState(() => toDateInput(new Date()))
  const [bulkSelection, setBulkSelection] = useState<number[]>([])
  const [error, setError] = useState<string | null>(null)

  const invoiceQuery = useQuery({
    queryKey: ['invoice-drafts', 'billing', status],
    queryFn: () => fetchInvoiceDrafts(status),
  })
  const clientsQuery = useQuery({
    queryKey: ['clients', 'all'],
    queryFn: () => fetchClients(),
  })
  const bulkQuery = useQuery({
    queryKey: ['invoice-drafts', 'bulk', bulkFrom, bulkTo],
    queryFn: () => fetchInvoiceDraftsBulk({ from: bulkFrom, to: bulkTo }),
  })

  const bulkDrafts = useMemo(
    () => (bulkQuery.data ?? []).filter((draft) => draft.status === 'draft'),
    [bulkQuery.data]
  )

  useEffect(() => {
    setBulkSelection((prev) => prev.filter((id) => bulkDrafts.some((draft) => draft.id === id)))
  }, [bulkDrafts])

  const clientNameById = useMemo(() => {
    const map = new Map<number, string>()
    clientsQuery.data?.forEach((client) => map.set(client.id, client.name))
    return map
  }, [clientsQuery.data])

  const markSentMutation = useMutation({
    mutationFn: markInvoiceSent,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      setError(null)
    },
    onError: (err) => setError(err instanceof Error ? err.message : 'Could not mark as sent.'),
  })

  const markPaidMutation = useMutation({
    mutationFn: markInvoicePaid,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      setError(null)
    },
    onError: (err) => setError(err instanceof Error ? err.message : 'Could not mark as paid.'),
  })

  const emailMutation = useMutation({
    mutationFn: ({ id, subject, message }: { id: number; subject?: string; message?: string }) =>
      sendInvoiceEmail(id, { subject, message }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      setError(null)
    },
    onError: (err) => setError(err instanceof Error ? err.message : 'Could not send invoice email.'),
  })

  const voidMutation = useMutation({
    mutationFn: voidInvoiceDraft,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      setError(null)
    },
    onError: (err) => setError(err instanceof Error ? err.message : 'Could not void invoice.'),
  })

  const bulkMarkMutation = useMutation({
    mutationFn: bulkMarkInvoicesSent,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts'] })
      queryClient.invalidateQueries({ queryKey: ['invoice-drafts', 'bulk'] })
      setBulkSelection([])
      setError(null)
    },
    onError: (err) => setError(err instanceof Error ? err.message : 'Bulk update failed.'),
  })

  const toggleBulkSelection = (id: number) => {
    setBulkSelection((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    )
  }

  return (
    <div className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">Billing</p>
          <h2>Draft invoices ready to send.</h2>
          <p className="muted">Send, mark as paid, or export PDFs when ready.</p>
        </div>
      </section>

      <section className="card">
        <div className="card-header">
          <h3>Invoice drafts</h3>
          <div className="chip-group">
            {(['draft', 'sent', 'paid', 'void'] as const).map((value) => (
              <button
                key={value}
                type="button"
                className={`chip-button${status === value ? ' active' : ''}`}
                onClick={() => setStatus(value)}
              >
                {value}
              </button>
            ))}
            <button
              className="button button--ghost"
              type="button"
              onClick={() =>
                window.open(
                  `/api/invoice-drafts/export?status=${status}&from=${bulkFrom}&to=${bulkTo}`,
                  '_blank',
                  'noopener'
                )
              }
            >
              Export CSV
            </button>
          </div>
        </div>
        {invoiceQuery.isLoading ? (
          <p className="muted">Loading drafts...</p>
        ) : invoiceQuery.data && invoiceQuery.data.length > 0 ? (
          <div className="table">
            {invoiceQuery.data.map((draft) => (
              <div key={draft.id} className="table-row">
                <span>
                  {draft.status.toUpperCase()} #{draft.id} -{' '}
                  {clientNameById.get(draft.client_id) ?? `Client ${draft.client_id}`}
                </span>
                <span>{draft.currency}</span>
                <strong>{(draft.amount_cents / 100).toFixed(2)}</strong>
                <div className="row-actions">
                  <button
                    className="button button--ghost"
                    type="button"
                    onClick={() =>
                      window.open(`/api/invoice-drafts/${draft.id}/pdf`, '_blank', 'noopener')
                    }
                  >
                    PDF
                  </button>
                  {draft.status === 'draft' ? (
                    <button
                      className="button button--ghost"
                      type="button"
                      onClick={() => markSentMutation.mutate(draft.id)}
                    >
                      Mark sent
                    </button>
                  ) : null}
                  {draft.status !== 'paid' && draft.status !== 'void' ? (
                    <button
                      className="button button--ghost"
                      type="button"
                      onClick={() => markPaidMutation.mutate(draft.id)}
                    >
                      Mark paid
                    </button>
                  ) : null}
                  {draft.status !== 'paid' && draft.status !== 'void' ? (
                    <button
                      className="button button--ghost"
                      type="button"
                      onClick={() => {
                        const subject = window.prompt('Email subject', `Invoice #${draft.id}`)
                        if (subject === null) {
                          return
                        }
                        const message = window.prompt('Email message', '')
                        if (message === null) {
                          return
                        }
                        emailMutation.mutate({
                          id: draft.id,
                          subject: subject.trim() || undefined,
                          message: message.trim() || undefined,
                        })
                      }}
                    >
                      Send email
                    </button>
                  ) : null}
                  {draft.status !== 'paid' && draft.status !== 'void' ? (
                    <button
                      className="button button--ghost"
                      type="button"
                      onClick={() => voidMutation.mutate(draft.id)}
                    >
                      Void
                    </button>
                  ) : null}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="muted">No drafts yet. Log a session to create one.</p>
        )}
        {error ? <p className="form-error">{error}</p> : null}
      </section>

      <section className="card">
        <div className="card-header">
          <h3>Weekly review</h3>
          <span className="chip">Bulk send</span>
        </div>
        <div className="grid two-columns">
          <label className="field">
            <span>From</span>
            <input type="date" value={bulkFrom} onChange={(event) => setBulkFrom(event.target.value)} />
          </label>
          <label className="field">
            <span>To</span>
            <input type="date" value={bulkTo} onChange={(event) => setBulkTo(event.target.value)} />
          </label>
        </div>
        {bulkQuery.isLoading ? (
          <p className="muted">Loading invoice range...</p>
        ) : bulkDrafts.length > 0 ? (
          <>
            <div className="table">
              {bulkDrafts.map((draft) => (
                <div key={draft.id} className="table-row table-row--dense">
                  <label className="checkbox">
                    <input
                      type="checkbox"
                      checked={bulkSelection.includes(draft.id)}
                      onChange={() => toggleBulkSelection(draft.id)}
                    />
                    <span>
                      #{draft.id} - {clientNameById.get(draft.client_id) ?? `Client ${draft.client_id}`}
                    </span>
                  </label>
                  <span>{draft.currency}</span>
                  <strong>{(draft.amount_cents / 100).toFixed(2)}</strong>
                  <span className="muted">{draft.lines.length} line items</span>
                </div>
              ))}
            </div>
            <button
              className="button button--primary"
              type="button"
              disabled={bulkSelection.length === 0 || bulkMarkMutation.isPending}
              onClick={() => bulkMarkMutation.mutate(bulkSelection)}
            >
              {bulkMarkMutation.isPending ? 'Updating...' : `Mark ${bulkSelection.length} sent`}
            </button>
          </>
        ) : (
          <p className="muted">No invoice drafts in this window.</p>
        )}
      </section>
    </div>
  )
}

import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchClients, fetchPayPalStatus, fetchReportSummary } from '../api/client'

const toDateInput = (date: Date) => date.toISOString().slice(0, 10)

const statusOrder = ['draft', 'sent', 'paid', 'void']

export function ReportsPage() {
  const [from, setFrom] = useState(() => toDateInput(new Date(Date.now() - 29 * 86400000)))
  const [to, setTo] = useState(() => toDateInput(new Date()))
  const [clientId, setClientId] = useState('all')

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

  const summaryQuery = useQuery({
    queryKey: ['reports-summary', from, to, clientId],
    queryFn: () =>
      fetchReportSummary({
        from,
        to,
        clientId: clientId === 'all' ? undefined : Number(clientId),
      }),
  })

  const totals = summaryQuery.data
  const summaryScope = totals?.scope ?? (hasProAccess ? 'full' : 'basic')
  const showFullSummary = summaryScope === 'full'

  const invoiceCards = useMemo(() => {
    if (!totals || !showFullSummary) {
      return []
    }

    return statusOrder.map((status) => ({
      status,
      totals: totals.invoice_totals[status] ?? { count: 0, amounts: {} },
    }))
  }, [totals])

  const formatAmounts = (amounts: Record<string, number>) => {
    const entries = Object.entries(amounts)
    if (entries.length === 0) {
      return 'n/a'
    }
    return entries
      .map(([currency, cents]) => `${currency} ${(cents / 100).toFixed(2)}`)
      .join(', ')
  }

  const minutesToHours = (minutes: number) => (minutes / 60).toFixed(1)

  return (
    <div className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">Reporting</p>
          <h2>Track sessions, invoices, and follow-through.</h2>
          <p className="muted">Filter by date range or client to understand your pipeline.</p>
        </div>
      </section>

      <section className="card">
        <div className="card-header">
          <h3>Filters</h3>
          {showFullSummary ? (
            <button
              className="button button--ghost"
              type="button"
              onClick={() => {
                const params = new URLSearchParams()
                if (from) params.set('from', from)
                if (to) params.set('to', to)
                if (clientId !== 'all') params.set('clientId', clientId)
                const suffix = params.toString() ? `?${params.toString()}` : ''
                window.open(`/api/reports/export${suffix}`, '_blank', 'noopener')
              }}
            >
              Export CSV
            </button>
          ) : (
            <span className="chip">Starter</span>
          )}
        </div>
        <div className="grid">
          <label className="field">
            <span>From</span>
            <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
          </label>
          <label className="field">
            <span>To</span>
            <input type="date" value={to} onChange={(event) => setTo(event.target.value)} />
          </label>
          <label className="field">
            <span>Client</span>
            <select value={clientId} onChange={(event) => setClientId(event.target.value)}>
              <option value="all">All clients</option>
              {clientsQuery.data?.map((client) => (
                <option key={client.id} value={String(client.id)}>
                  {client.name}
                </option>
              ))}
            </select>
          </label>
        </div>
      </section>

      {summaryQuery.isLoading ? <p className="muted">Loading summary...</p> : null}
      {summaryQuery.isError ? (
        <p className="form-error">Could not load reporting summary.</p>
      ) : null}

      {totals && !showFullSummary ? (
        <section className="grid">
          <div className="card">
            <div className="card-header">
              <h3>Basic summary</h3>
              <span className="chip">Starter</span>
            </div>
            <ul className="list">
              <li>
                <span>Total time</span>
                <strong>{minutesToHours(totals.work_events.total_minutes)}h</strong>
              </li>
              <li>
                <span>Total sessions</span>
                <strong>{totals.work_events.total_sessions}</strong>
              </li>
              <li>
                <span>Paid invoices</span>
                <strong>{totals.invoice_totals.paid?.count ?? 0}</strong>
              </li>
              <li>
                <span>Paid amount</span>
                <strong>{formatAmounts(totals.invoice_totals.paid?.amounts ?? {})}</strong>
              </li>
            </ul>
            <p className="muted">
              Upgrade to Pro for full reporting breakdowns and CSV export.
            </p>
          </div>
        </section>
      ) : null}

      {totals && showFullSummary ? (
        <>
          <section className="grid">
            <div className="card">
              <div className="card-header">
                <h3>Work sessions</h3>
              </div>
              <ul className="list">
                <li>
                  <span>Total time</span>
                  <strong>{minutesToHours(totals.work_events.total_minutes)}h</strong>
                </li>
                <li>
                  <span>Billable time</span>
                  <strong>{minutesToHours(totals.work_events.billable_minutes ?? 0)}h</strong>
                </li>
                <li>
                  <span>Non-billable time</span>
                  <strong>{minutesToHours(totals.work_events.non_billable_minutes ?? 0)}h</strong>
                </li>
                <li>
                  <span>Total sessions</span>
                  <strong>{totals.work_events.total_sessions}</strong>
                </li>
                <li>
                  <span>Billable sessions</span>
                  <strong>{totals.work_events.billable_sessions ?? 0}</strong>
                </li>
                <li>
                  <span>Non-billable sessions</span>
                  <strong>{totals.work_events.non_billable_sessions ?? 0}</strong>
                </li>
              </ul>
            </div>
          </section>

          <section className="grid">
            {invoiceCards.map((card) => (
              <div key={card.status} className="card">
                <div className="card-header">
                  <h3>{card.status} invoices</h3>
                </div>
                <p className="muted">Count: {card.totals.count}</p>
                <p className="muted">Amount: {formatAmounts(card.totals.amounts)}</p>
              </div>
            ))}
          </section>
        </>
      ) : null}
    </div>
  )
}

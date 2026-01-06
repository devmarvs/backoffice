import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchClients, fetchReportSummary } from '../api/client'

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

  const invoiceCards = useMemo(() => {
    if (!totals) {
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

      {totals ? (
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
                  <strong>{minutesToHours(totals.work_events.billable_minutes)}h</strong>
                </li>
                <li>
                  <span>Non-billable time</span>
                  <strong>{minutesToHours(totals.work_events.non_billable_minutes)}h</strong>
                </li>
                <li>
                  <span>Total sessions</span>
                  <strong>{totals.work_events.total_sessions}</strong>
                </li>
                <li>
                  <span>Billable sessions</span>
                  <strong>{totals.work_events.billable_sessions}</strong>
                </li>
                <li>
                  <span>Non-billable sessions</span>
                  <strong>{totals.work_events.non_billable_sessions}</strong>
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

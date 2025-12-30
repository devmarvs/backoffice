import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { createClient, fetchClients } from '../api/client'

export function ClientsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [form, setForm] = useState({ name: '', email: '', phone: '' })
  const [error, setError] = useState<string | null>(null)

  const clientsQuery = useQuery({
    queryKey: ['clients', search],
    queryFn: () => fetchClients(search.trim() || undefined),
  })

  const createMutation = useMutation({
    mutationFn: createClient,
    onSuccess: (client) => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
      setForm({ name: '', email: '', phone: '' })
      setError(null)
      navigate(`/app/clients/${client.id}`)
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Could not create client.')
    },
  })

  const handleCreate = (event: React.FormEvent) => {
    event.preventDefault()
    if (form.name.trim() === '') {
      setError('Client name is required.')
      return
    }
    setError(null)
    createMutation.mutate({
      name: form.name.trim(),
      email: form.email.trim() || undefined,
      phone: form.phone.trim() || undefined,
    })
  }

  return (
    <div className="page">
      <section className="hero compact">
        <div>
          <p className="eyebrow">Clients</p>
          <h2>Your client roster.</h2>
          <p className="muted">Search, add, and jump into a client overview.</p>
        </div>
      </section>

      <section className="grid two-columns">
        <div className="card">
          <div className="card-header">
            <h3>Add a client</h3>
          </div>
          <form className="stack" onSubmit={handleCreate}>
            <label className="field">
              <span>Name</span>
              <input
                type="text"
                value={form.name}
                onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
              />
            </label>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                value={form.email}
                onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))}
              />
            </label>
            <label className="field">
              <span>Phone</span>
              <input
                type="tel"
                value={form.phone}
                onChange={(event) => setForm((prev) => ({ ...prev, phone: event.target.value }))}
              />
            </label>
            {error ? <p className="form-error">{error}</p> : null}
            <button className="button button--primary" type="submit" disabled={createMutation.isPending}>
              {createMutation.isPending ? 'Saving...' : 'Create client'}
            </button>
          </form>
        </div>

        <div className="card">
          <div className="card-header">
            <h3>Search clients</h3>
          </div>
          <label className="field">
            <span>Search by name, email, or phone</span>
            <input
              type="text"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Start typing..."
            />
          </label>
          {clientsQuery.isLoading ? (
            <p className="muted">Loading clients...</p>
          ) : clientsQuery.data && clientsQuery.data.length > 0 ? (
            <div className="table">
              {clientsQuery.data.map((client) => (
                <button
                  key={client.id}
                  type="button"
                  className="table-row table-row--button"
                  onClick={() => navigate(`/app/clients/${client.id}`)}
                >
                  <span>{client.name}</span>
                  <span className="muted">{client.email || 'No email'}</span>
                  <span className="muted">{client.phone || ''}</span>
                  <span className="link-pill">Open</span>
                </button>
              ))}
            </div>
          ) : (
            <p className="muted">No clients yet. Add one to get started.</p>
          )}
        </div>
      </section>
    </div>
  )
}

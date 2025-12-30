export type ApiError = {
  code: string
  message: string
  details?: Record<string, unknown>
}

export async function apiFetch<T>(path: string, options: RequestInit = {}) {
  const response = await fetch(path, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  })

  const contentType = response.headers.get('content-type') || ''
  const hasJson = contentType.includes('application/json')
  const payload = hasJson ? await response.json() : null

  if (!response.ok) {
    const error = payload?.error as ApiError | undefined
    throw new Error(error?.message || 'Request failed')
  }

  return (payload?.data ?? null) as T
}

export type User = {
  id: number
  email: string
  created_at: string
}

export type Client = {
  id: number
  user_id?: number
  name: string
  email?: string | null
  phone?: string | null
  created_at: string
  updated_at?: string
}

export type WorkEventPayload = {
  client_id: number
  type: 'session' | 'no_show' | 'admin'
  start_at: string
  duration_minutes: number
  billable: boolean
  notes?: string
  rate_cents?: number
  currency?: string
}

export type WorkEvent = {
  id: number
  user_id?: number
  client_id: number
  type: 'session' | 'no_show' | 'admin'
  start_at: string
  duration_minutes: number
  billable: boolean
  notes?: string | null
  created_at: string
}

export type InvoiceDraft = {
  id: number
  client_id: number
  amount_cents: number
  currency: string
  status: string
  created_at: string
  updated_at: string
}

export type InvoiceLine = {
  id: number
  invoice_draft_id: number
  work_event_id?: number | null
  description: string
  quantity: string
  unit_price_cents: number
}

export type InvoiceDraftWithLines = InvoiceDraft & {
  lines: InvoiceLine[]
}

export type FollowUp = {
  id: number
  client_id: number
  due_at: string
  suggested_message: string
  status: string
  created_at: string
  updated_at?: string
}

export type Package = {
  id: number
  client_id: number
  title: string
  total_sessions: number
  used_sessions: number
  price_cents?: number | null
  currency: string
  created_at: string
}

export type UserSettings = {
  user_id: number
  business_type?: string | null
  charge_model?: string | null
  default_rate_cents?: number | null
  default_currency?: string | null
  follow_up_days?: number | null
  invoice_reminder_days?: number | null
  onboarding_note?: string | null
  created_at?: string
  updated_at?: string
}

export type MessageTemplate = {
  type: 'follow_up' | 'payment_reminder' | 'no_show'
  subject?: string | null
  body: string
}

export type BillingStatus = {
  status: string
  current_period_end?: string | null
}

export type PaymentLink = {
  id: number
  invoice_draft_id: number
  provider: string
  provider_id: string
  url: string
  status: string
  created_at: string
  updated_at: string
}

export type ReferralSummary = {
  code: {
    id: number
    user_id: number
    code: string
    created_at: string
  }
  referrals: Array<{
    id: number
    referrer_id: number
    referred_user_id?: number | null
    code: string
    status: string
    created_at: string
  }>
}

export type CalendarEvent = {
  id: number
  provider: string
  provider_event_id: string
  summary?: string | null
  start_at: string
  end_at?: string | null
  created_at: string
}

export async function logWorkEvent(payload: WorkEventPayload) {
  return apiFetch<{ work_event: Record<string, unknown>; autopilot: Record<string, unknown> }>(
    '/api/work-events',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    }
  )
}

export async function voiceLogWorkEvent(payload: {
  transcript: string
  client_id?: number
  client_name?: string
  duration_minutes?: number
  start_at?: string
  type?: 'session' | 'no_show' | 'admin'
  billable?: boolean
  rate_cents?: number
  currency?: string
}) {
  return apiFetch<{ work_event: Record<string, unknown>; autopilot: Record<string, unknown> }>(
    '/api/work-events/voice',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    }
  )
}

export async function login(email: string, password: string) {
  return apiFetch<User>(`/api/auth/login`, {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })
}

export async function register(email: string, password: string, referralCode?: string) {
  return apiFetch<User>(`/api/auth/register`, {
    method: 'POST',
    body: JSON.stringify({
      email,
      password,
      referral_code: referralCode || undefined,
    }),
  })
}

export async function completeOnboarding(payload: {
  business_type?: string
  charge_model?: string
  default_rate_cents?: number
  default_currency?: string
  follow_up_days?: number
  invoice_reminder_days?: number
  onboarding_note?: string
  first_client_name?: string
  first_client_email?: string
  first_client_phone?: string
  first_start_at?: string
  first_duration_minutes?: number
  first_billable?: boolean
  first_rate_cents?: number
  first_currency?: string
  first_notes?: string
  first_type?: 'session' | 'no_show' | 'admin'
}) {
  return apiFetch<{ completed: boolean }>(`/api/onboarding`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function fetchMe() {
  return apiFetch<User>(`/api/auth/me`)
}

export async function logout() {
  return apiFetch<{ logged_out: boolean }>(`/api/auth/logout`, { method: 'POST' })
}

export async function fetchClients(search?: string) {
  const query = search ? `?search=${encodeURIComponent(search)}` : ''
  return apiFetch<Client[]>(`/api/clients${query}`)
}

export async function createClient(payload: { name: string; email?: string; phone?: string }) {
  return apiFetch<Client>(`/api/clients`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function updateClient(
  id: number,
  payload: { name?: string; email?: string; phone?: string }
) {
  return apiFetch<Client>(`/api/clients/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export async function fetchClient(id: number) {
  return apiFetch<Client>(`/api/clients/${id}`)
}

export async function fetchWorkEvents(params: {
  from?: string
  to?: string
  clientId?: number
}) {
  const query = new URLSearchParams()
  if (params.from) query.set('from', params.from)
  if (params.to) query.set('to', params.to)
  if (params.clientId) query.set('clientId', String(params.clientId))
  const suffix = query.toString() ? `?${query.toString()}` : ''
  return apiFetch<WorkEvent[]>(`/api/work-events${suffix}`)
}

export async function fetchInvoiceDrafts(status = 'draft') {
  return apiFetch<InvoiceDraft[]>(`/api/invoice-drafts?status=${encodeURIComponent(status)}`)
}

export async function markInvoiceSent(id: number) {
  return apiFetch<InvoiceDraft>(`/api/invoice-drafts/${id}/send`, { method: 'POST' })
}

export async function markInvoicePaid(id: number) {
  return apiFetch<InvoiceDraft>(`/api/invoice-drafts/${id}/mark-paid`, { method: 'POST' })
}

export async function fetchInvoiceDraftsBulk(params: { from?: string; to?: string }) {
  const query = new URLSearchParams()
  if (params.from) query.set('from', params.from)
  if (params.to) query.set('to', params.to)
  const suffix = query.toString() ? `?${query.toString()}` : ''
  return apiFetch<InvoiceDraftWithLines[]>(`/api/invoice-drafts/bulk${suffix}`)
}

export async function bulkMarkInvoicesSent(ids: number[]) {
  return apiFetch<{ updated: number }>(`/api/invoice-drafts/bulk/mark-sent`, {
    method: 'POST',
    body: JSON.stringify({ ids }),
  })
}

export async function createPaymentLink(id: number) {
  return apiFetch<PaymentLink>(`/api/invoice-drafts/${id}/payment-link`, {
    method: 'POST',
  })
}

export async function fetchFollowUps(status = 'open') {
  return apiFetch<FollowUp[]>(`/api/follow-ups?status=${encodeURIComponent(status)}`)
}

export async function markFollowUpDone(id: number) {
  return apiFetch<FollowUp>(`/api/follow-ups/${id}/done`, { method: 'POST' })
}

export async function dismissFollowUp(id: number) {
  return apiFetch<FollowUp>(`/api/follow-ups/${id}/dismiss`, { method: 'POST' })
}

export async function fetchPackages(clientId: number) {
  return apiFetch<Package[]>(`/api/packages?clientId=${encodeURIComponent(String(clientId))}`)
}

export async function createPackage(payload: {
  client_id: number
  title: string
  total_sessions: number
  used_sessions?: number
  price_cents?: number | null
  currency: string
}) {
  return apiFetch<Package>(`/api/packages`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function updatePackage(
  id: number,
  payload: {
    title?: string
    total_sessions?: number
    used_sessions?: number
    price_cents?: number | null
    currency?: string
  }
) {
  return apiFetch<Package>(`/api/packages/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export async function usePackageSession(id: number) {
  return apiFetch<Package>(`/api/packages/${id}/use`, { method: 'POST' })
}

export async function fetchSettings() {
  return apiFetch<UserSettings>(`/api/settings`)
}

export async function updateSettings(payload: Partial<UserSettings>) {
  return apiFetch<UserSettings>(`/api/settings`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export async function fetchTemplates() {
  return apiFetch<MessageTemplate[]>(`/api/templates`)
}

export async function updateTemplate(
  type: MessageTemplate['type'],
  payload: { subject?: string | null; body: string }
) {
  return apiFetch<MessageTemplate>(`/api/templates/${type}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export async function runReminders() {
  return apiFetch<{ created: number }>(`/api/reminders/run`, { method: 'POST' })
}

export async function fetchBillingStatus() {
  return apiFetch<BillingStatus>(`/api/billing/status`)
}

export async function startCheckout() {
  return apiFetch<{ url: string; session_id: string }>(`/api/billing/checkout`, { method: 'POST' })
}

export async function startBillingPortal() {
  return apiFetch<{ url: string }>(`/api/billing/portal`, { method: 'POST' })
}

export async function connectGoogle() {
  return apiFetch<{ url: string }>(`/api/integrations/google/connect`)
}

export async function syncGoogleCalendar() {
  return apiFetch<{ imported: number }>(`/api/integrations/google/sync`, { method: 'POST' })
}

export async function disconnectGoogle() {
  return apiFetch<{ disconnected: boolean }>(`/api/integrations/google`, { method: 'DELETE' })
}

export async function fetchCalendarEvents(params: { from?: string; to?: string }) {
  const query = new URLSearchParams()
  if (params.from) query.set('from', params.from)
  if (params.to) query.set('to', params.to)
  const suffix = query.toString() ? `?${query.toString()}` : ''
  return apiFetch<CalendarEvent[]>(`/api/calendar/events${suffix}`)
}

export async function fetchReferrals() {
  return apiFetch<ReferralSummary>(`/api/referrals/me`)
}

# BackOffice Autopilot

BackOffice Autopilot is a session-first admin autopilot for independent tutors. Log a work event in seconds and the system drafts invoices, follow-ups, and package tracking automatically.

## Repo layout

- `apps/api` - Symfony 7 API (FrankenPHP)
- `apps/web` - React + Vite frontend
- `docker` - Docker and FrankenPHP/Caddy config

## Requirements

- Docker + Docker Compose
- A running PostgreSQL 18.x container (external)

## Configure

1. Copy the example env file and edit it:

```bash
cp .env.example .env
```

2. Point the API at your external PostgreSQL container:

- If PostgreSQL runs in another container and is reachable on the host network (macOS/Windows):
  - Set `DB_HOST=host.docker.internal` in `.env`
- If PostgreSQL runs in another Docker network:
  - Set `DB_HOST` to the container name and connect the API container to that network:
    ```bash
    docker network connect <postgres_network> $(docker compose ps -q api)
    ```

3. Start services:

```bash
docker compose up --build
```

If you did not run Composer locally, install PHP dependencies in the container:

```bash
docker compose exec api composer install
```

## Database

Run migrations:

```bash
docker compose exec api php bin/console doctrine:migrations:migrate
```

## Frontend

The Vite dev server proxies `/api` to the API container. Open:

- Web: http://localhost:5173
- API: http://localhost:8080

## Notes

- The API uses session cookies for auth. The Vite dev server proxies requests so no CORS setup is required for local dev.
- Default follow-up delay is controlled by `FOLLOW_UP_DAYS` in `.env` (default: `3`).
- Invoice reminder creation uses `INVOICE_REMINDER_DAYS` (default: `7`).
- The reminders worker runs via the `reminders` service in `docker-compose.yml`. Set `REMINDER_INTERVAL_SECONDS` (default: `3600`) to control how often it runs, or run `php bin/console app:reminders:run` manually.
- PHP 8.5 is not released yet; the Docker image defaults to the latest FrankenPHP tag. Override with `FRANKENPHP_IMAGE` in `.env` if you need a specific PHP version.
- If `rate_cents` is not provided when logging a billable session, invoice drafts are created with a 0 amount.

## Optional integrations

Stripe checkout (subscriptions + payment links):

- `STRIPE_SECRET_KEY`
- `STRIPE_PRICE_ID`
- `STRIPE_SUCCESS_URL`
- `STRIPE_CANCEL_URL`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_PORTAL_RETURN_URL`

PayPal subscriptions:

- `PAYPAL_CLIENT_ID`
- `PAYPAL_CLIENT_SECRET`
- `PAYPAL_PLAN_ID`
- `PAYPAL_ENV` (`sandbox` or `live`)
- `PAYPAL_SUCCESS_URL`
- `PAYPAL_CANCEL_URL`
- `PAYPAL_MANAGE_URL` (optional: defaults to the PayPal autopay page)
- `PAYPAL_BRAND_NAME` (optional)

Google Calendar (read-only import):

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URL`
- `GOOGLE_SCOPES` (default: calendar.readonly)
- `GOOGLE_CALENDAR_ID` (default: `primary`)
- `GOOGLE_SUCCESS_REDIRECT`
- `GOOGLE_FAILURE_REDIRECT`

Make sure `GOOGLE_REDIRECT_URL` matches your API host/port (for example `http://localhost:8080/api/integrations/google/callback`).

Email (system mailer):

- `MAIL_FROM`
- `MAIL_REPLY_TO` (optional)
- `MAIL_BCC` (optional)
- `MAIL_TRANSPORT` (`mail` or `smtp`)
- `MAIL_SMTP_HOST` (required for SMTP)
- `MAIL_SMTP_PORT` (default: 587)
- `MAIL_SMTP_USER` (optional)
- `MAIL_SMTP_PASSWORD` (optional)
- `MAIL_SMTP_ENCRYPTION` (`tls`, `ssl`, or empty)
- `MAIL_SMTP_TIMEOUT` (seconds)

Note: `MAIL_TRANSPORT=mail` uses PHP's `mail()` function. In Docker, configure a sendmail-compatible binary if you want delivery.

## Key API routes

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/clients?search=`
- `POST /api/clients`
- `GET /api/clients/{id}`
- `PATCH /api/clients/{id}`
- `POST /api/work-events`
- `GET /api/work-events?from=&to=&clientId=`
- `GET /api/work-events/export?from=&to=&clientId=`
- `GET /api/invoice-drafts?status=draft`
- `GET /api/invoice-drafts/export?status=&from=&to=`
- `POST /api/invoice-drafts/{id}/send`
- `POST /api/invoice-drafts/{id}/mark-paid`
- `POST /api/invoice-drafts/{id}/void`
- `POST /api/invoice-drafts/{id}/email`
- `POST /api/invoice-drafts/{id}/payment-link/refresh`
- `GET /api/follow-ups?status=open`
- `GET /api/follow-ups/export?status=&from=&to=`
- `POST /api/follow-ups/{id}/done`
- `POST /api/follow-ups/{id}/dismiss`
- `POST /api/follow-ups/{id}/reopen`
- `POST /api/follow-ups/{id}/email`
- `GET /api/calendar/suggestions?from=&to=`
- `POST /api/calendar/events/{id}/log`
- `GET /api/audit-logs?limit=`

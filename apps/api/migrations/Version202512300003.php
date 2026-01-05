<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202512300003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add billing webhook event log for idempotency and auditability.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE billing_webhook_events (
            id BIGSERIAL PRIMARY KEY,
            provider TEXT NOT NULL,
            event_id TEXT NOT NULL,
            event_type TEXT NOT NULL,
            payload JSONB NOT NULL,
            status TEXT NOT NULL DEFAULT \'received\',
            error_message TEXT NULL,
            received_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            processed_at TIMESTAMPTZ NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX billing_webhook_events_provider_event_idx ON billing_webhook_events(provider, event_id)');
        $this->addSql('CREATE INDEX billing_webhook_events_status_idx ON billing_webhook_events(status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS billing_webhook_events');
    }
}

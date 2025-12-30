<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202512300001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core BackOffice Autopilot tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id BIGSERIAL PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');

        $this->addSql('CREATE TABLE clients (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            email TEXT NULL,
            phone TEXT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX clients_user_id_idx ON clients(user_id)');

        $this->addSql('CREATE TABLE work_events (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
            type TEXT NOT NULL CHECK (type IN (\'session\', \'no_show\', \'admin\')),
            start_at TIMESTAMPTZ NOT NULL,
            duration_minutes INT NOT NULL,
            billable BOOLEAN NOT NULL DEFAULT TRUE,
            notes TEXT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX work_events_user_id_idx ON work_events(user_id)');
        $this->addSql('CREATE INDEX work_events_client_id_idx ON work_events(client_id)');
        $this->addSql('CREATE INDEX work_events_start_at_idx ON work_events(start_at)');

        $this->addSql('CREATE TABLE packages (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            total_sessions INT NOT NULL,
            used_sessions INT NOT NULL DEFAULT 0,
            price_cents INT NULL,
            currency TEXT NOT NULL DEFAULT \'EUR\',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX packages_user_client_idx ON packages(user_id, client_id)');

        $this->addSql('CREATE TABLE invoice_drafts (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
            period_start DATE NULL,
            period_end DATE NULL,
            amount_cents INT NOT NULL,
            currency TEXT NOT NULL DEFAULT \'EUR\',
            status TEXT NOT NULL CHECK (status IN (\'draft\', \'sent\', \'paid\', \'void\')) DEFAULT \'draft\',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX invoice_drafts_user_status_idx ON invoice_drafts(user_id, status)');

        $this->addSql('CREATE TABLE invoice_lines (
            id BIGSERIAL PRIMARY KEY,
            invoice_draft_id BIGINT NOT NULL REFERENCES invoice_drafts(id) ON DELETE CASCADE,
            work_event_id BIGINT NULL REFERENCES work_events(id) ON DELETE SET NULL,
            description TEXT NOT NULL,
            quantity NUMERIC(10,2) NOT NULL DEFAULT 1,
            unit_price_cents INT NOT NULL
        )');
        $this->addSql('CREATE INDEX invoice_lines_invoice_idx ON invoice_lines(invoice_draft_id)');

        $this->addSql('CREATE TABLE follow_ups (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
            due_at TIMESTAMPTZ NOT NULL,
            suggested_message TEXT NOT NULL,
            status TEXT NOT NULL CHECK (status IN (\'open\', \'done\', \'dismissed\')) DEFAULT \'open\',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX follow_ups_user_status_idx ON follow_ups(user_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS follow_ups');
        $this->addSql('DROP TABLE IF EXISTS invoice_lines');
        $this->addSql('DROP TABLE IF EXISTS invoice_drafts');
        $this->addSql('DROP TABLE IF EXISTS packages');
        $this->addSql('DROP TABLE IF EXISTS work_events');
        $this->addSql('DROP TABLE IF EXISTS clients');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}

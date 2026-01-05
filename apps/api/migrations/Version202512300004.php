<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202512300004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit log, work event source fields, and reminder run tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_logs (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id BIGINT NULL,
            metadata JSONB NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX audit_logs_user_created_idx ON audit_logs(user_id, created_at DESC)');

        $this->addSql('ALTER TABLE work_events ADD COLUMN source_type TEXT NULL');
        $this->addSql('ALTER TABLE work_events ADD COLUMN source_id BIGINT NULL');
        $this->addSql('CREATE INDEX work_events_source_idx ON work_events(user_id, source_type, source_id)');

        $this->addSql('ALTER TABLE user_settings ADD COLUMN last_reminder_run_at TIMESTAMPTZ NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_settings DROP COLUMN IF EXISTS last_reminder_run_at');

        $this->addSql('DROP INDEX IF EXISTS work_events_source_idx');
        $this->addSql('ALTER TABLE work_events DROP COLUMN IF EXISTS source_type');
        $this->addSql('ALTER TABLE work_events DROP COLUMN IF EXISTS source_id');

        $this->addSql('DROP TABLE IF EXISTS audit_logs');
    }
}

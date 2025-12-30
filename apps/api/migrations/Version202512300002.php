<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202512300002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add settings, templates, billing, integrations, calendar imports, referrals, and follow-up metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_settings (
            user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            business_type TEXT NULL,
            charge_model TEXT NULL CHECK (charge_model IN (\'per_session\', \'package\', \'monthly\')),
            default_rate_cents INT NULL,
            default_currency TEXT NOT NULL DEFAULT \'EUR\',
            follow_up_days INT NULL,
            invoice_reminder_days INT NULL,
            onboarding_note TEXT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');

        $this->addSql('CREATE TABLE message_templates (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            type TEXT NOT NULL CHECK (type IN (\'follow_up\', \'payment_reminder\', \'no_show\')),
            subject TEXT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE (user_id, type)
        )');
        $this->addSql('CREATE INDEX message_templates_user_type_idx ON message_templates(user_id, type)');

        $this->addSql('ALTER TABLE follow_ups ADD COLUMN source_type TEXT NULL');
        $this->addSql('ALTER TABLE follow_ups ADD COLUMN source_id BIGINT NULL');
        $this->addSql('ALTER TABLE follow_ups ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()');
        $this->addSql('CREATE INDEX follow_ups_source_idx ON follow_ups(user_id, source_type, source_id)');

        $this->addSql('CREATE TABLE billing_subscriptions (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            provider TEXT NOT NULL,
            customer_id TEXT NULL,
            subscription_id TEXT NULL,
            status TEXT NOT NULL,
            current_period_end TIMESTAMPTZ NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE (user_id, provider)
        )');
        $this->addSql('CREATE INDEX billing_subscriptions_provider_idx ON billing_subscriptions(provider)');

        $this->addSql('CREATE TABLE payment_links (
            id BIGSERIAL PRIMARY KEY,
            invoice_draft_id BIGINT NOT NULL REFERENCES invoice_drafts(id) ON DELETE CASCADE,
            provider TEXT NOT NULL,
            provider_id TEXT NOT NULL,
            url TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX payment_links_invoice_idx ON payment_links(invoice_draft_id)');

        $this->addSql('CREATE TABLE integrations (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            provider TEXT NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT NULL,
            expires_at TIMESTAMPTZ NULL,
            metadata JSONB NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE (user_id, provider)
        )');
        $this->addSql('CREATE INDEX integrations_provider_idx ON integrations(provider)');

        $this->addSql('CREATE TABLE calendar_events (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            provider TEXT NOT NULL,
            provider_event_id TEXT NOT NULL,
            summary TEXT NULL,
            start_at TIMESTAMPTZ NOT NULL,
            end_at TIMESTAMPTZ NULL,
            raw_payload JSONB NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE (user_id, provider, provider_event_id)
        )');
        $this->addSql('CREATE INDEX calendar_events_user_start_idx ON calendar_events(user_id, start_at)');

        $this->addSql('CREATE TABLE referral_codes (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            code TEXT NOT NULL UNIQUE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');

        $this->addSql('CREATE TABLE referrals (
            id BIGSERIAL PRIMARY KEY,
            referrer_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            referred_user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
            code TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )');
        $this->addSql('CREATE INDEX referrals_code_idx ON referrals(code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS referrals');
        $this->addSql('DROP TABLE IF EXISTS referral_codes');
        $this->addSql('DROP TABLE IF EXISTS calendar_events');
        $this->addSql('DROP TABLE IF EXISTS integrations');
        $this->addSql('DROP TABLE IF EXISTS payment_links');
        $this->addSql('DROP TABLE IF EXISTS billing_subscriptions');
        $this->addSql('DROP INDEX IF EXISTS follow_ups_source_idx');
        $this->addSql('ALTER TABLE follow_ups DROP COLUMN IF EXISTS source_type');
        $this->addSql('ALTER TABLE follow_ups DROP COLUMN IF EXISTS source_id');
        $this->addSql('ALTER TABLE follow_ups DROP COLUMN IF EXISTS updated_at');
        $this->addSql('DROP TABLE IF EXISTS message_templates');
        $this->addSql('DROP TABLE IF EXISTS user_settings');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202512300005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add plan column to billing subscriptions for pricing tiers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_subscriptions ADD COLUMN plan TEXT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_subscriptions DROP COLUMN IF EXISTS plan');
    }
}

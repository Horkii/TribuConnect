<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013User2FA extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add phoneNumber and 2FA fields to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_number VARCHAR(32) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE NOT NULL");
        $this->addSql("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_code VARCHAR(12) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        // no destructive rollback
    }
}


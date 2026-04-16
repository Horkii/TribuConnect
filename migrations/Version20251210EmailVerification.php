<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251210EmailVerification extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to users';
    }

    public function up(Schema $schema): void
    {
        // New accounts will be forced to verify; existing accounts are marked as verified.
        $this->addSql("ALTER TABLE users ADD email_verified BOOLEAN DEFAULT TRUE");
        $this->addSql("UPDATE users SET email_verified = TRUE WHERE email_verified IS NULL");
        $this->addSql("ALTER TABLE users ALTER COLUMN email_verified SET NOT NULL");

        $this->addSql("ALTER TABLE users ADD email_verify_token VARCHAR(100) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD email_verify_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP COLUMN email_verify_token");
        $this->addSql("ALTER TABLE users DROP COLUMN email_verify_expires_at");
        $this->addSql("ALTER TABLE users DROP COLUMN email_verified");
    }
}


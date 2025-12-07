<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251207ResetPassword extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reset password token and expiry fields to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD reset_password_token VARCHAR(100) DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD reset_password_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP COLUMN reset_password_token");
        $this->addSql("ALTER TABLE users DROP COLUMN reset_password_expires_at");
    }
}


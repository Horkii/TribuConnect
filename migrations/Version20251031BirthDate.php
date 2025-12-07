<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251031BirthDate extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace users.age with users.birth_date and enforce 18+ in application';
    }

    public function up(Schema $schema): void
    {
        // Add birth_date column and drop age
        $this->addSql("ALTER TABLE users ADD birth_date DATE DEFAULT NULL");
        $this->addSql("ALTER TABLE users DROP COLUMN age");
    }

    public function down(Schema $schema): void
    {
        // Recreate age and drop birth_date
        $this->addSql("ALTER TABLE users ADD age INT DEFAULT NULL");
        $this->addSql("ALTER TABLE users DROP COLUMN birth_date");
    }
}


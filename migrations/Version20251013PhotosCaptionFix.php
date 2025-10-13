<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013PhotosCaptionFix extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure photos.caption column exists and author_id is nullable';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL syntax; IF NOT EXISTS supported
        $this->addSql('ALTER TABLE photos ADD COLUMN IF NOT EXISTS caption TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE photos ALTER COLUMN author_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // No-op rollback for safety
    }
}


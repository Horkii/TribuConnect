<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013PhotosAuthorAdd extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photos.author_id (nullable), ensure caption exists, and FK to users with safety checks';
    }

    public function up(Schema $schema): void
    {
        // Add columns if missing
        $this->addSql('ALTER TABLE photos ADD COLUMN IF NOT EXISTS author_id INT NULL');
        $this->addSql('ALTER TABLE photos ADD COLUMN IF NOT EXISTS caption TEXT NULL');
        // Index author
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PHOTO_AUTHOR ON photos (author_id)');
        // Add FK with duplicate safety
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  ALTER TABLE photos
    ADD CONSTRAINT FK_PHOTO_AUTHOR FOREIGN KEY (author_id)
    REFERENCES users (id)
    ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
EXCEPTION
  WHEN duplicate_object THEN NULL;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        // No destructive rollback
    }
}


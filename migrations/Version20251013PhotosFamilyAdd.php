<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251013PhotosFamilyAdd extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photos.family_id (nullable for transition) with index and FK to families';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos ADD COLUMN IF NOT EXISTS family_id INT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_PHOTO_FAMILY ON photos (family_id)');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  ALTER TABLE photos
    ADD CONSTRAINT FK_PHOTO_FAMILY FOREIGN KEY (family_id)
    REFERENCES families (id)
    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
EXCEPTION
  WHEN duplicate_object THEN NULL;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        // Non-destructive
    }
}


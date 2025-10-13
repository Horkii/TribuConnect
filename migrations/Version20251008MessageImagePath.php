<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251008MessageImagePath extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_path column to messages table for chat images';
    }

    public function up(Schema $schema): void
    {
        // add column if it does not exist
        $this->addSql("ALTER TABLE messages ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages DROP COLUMN IF EXISTS image_path');
    }
}


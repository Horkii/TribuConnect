<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251008FamiliesManyToMany extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Switch User<->Family to ManyToMany via family_user; drop users.family_id';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('family_user')) {
            $this->addSql('CREATE TABLE family_user (family_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(family_id, user_id))');
            $this->addSql('CREATE INDEX IDX_FAMILY_USER_FAMILY ON family_user (family_id)');
            $this->addSql('CREATE INDEX IDX_FAMILY_USER_USER ON family_user (user_id)');
            $this->addSql('ALTER TABLE family_user ADD CONSTRAINT FK_FU_FAMILY FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE family_user ADD CONSTRAINT FK_FU_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        }
        // migrate existing one-to-one memberships if column exists
        try {
            $this->addSql('INSERT INTO family_user (family_id, user_id) SELECT family_id, id FROM users WHERE family_id IS NOT NULL');
            $this->addSql('ALTER TABLE users DROP CONSTRAINT IF EXISTS FK_USERS_FAMILY');
            $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS family_id');
        } catch (\Throwable $e) {
            // ignore if not applicable
        }
    }

    public function down(Schema $schema): void
    {
        // Cannot restore the previous column safely, just drop the pivot
        $this->addSql('DROP TABLE IF EXISTS family_user');
    }
}


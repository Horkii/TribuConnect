<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251008Init extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for users, families, invitations, events, messages, photos';
    }

    public function up(Schema $schema): void
    {
        // Users
        $this->addSql("CREATE TABLE users (
            id SERIAL NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            age INT DEFAULT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            city_or_region VARCHAR(120) DEFAULT NULL,
            family_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_USERS_EMAIL ON users (email)");

        // Families
        $this->addSql("CREATE TABLE families (
            id SERIAL NOT NULL,
            name VARCHAR(120) NOT NULL,
            owner_id INT DEFAULT NULL,
            PRIMARY KEY(id)
        )");

        // Invitations
        $this->addSql("CREATE TABLE invitations (
            id SERIAL NOT NULL,
            email VARCHAR(180) NOT NULL,
            token VARCHAR(64) NOT NULL,
            family_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_INVITATIONS_TOKEN ON invitations (token)");

        // Events
        $this->addSql("CREATE TABLE events (
            id SERIAL NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT DEFAULT NULL,
            start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_by_id INT DEFAULT NULL,
            family_id INT DEFAULT NULL,
            recurrence VARCHAR(16) NOT NULL,
            PRIMARY KEY(id)
        )");

        // Messages
        $this->addSql("CREATE TABLE messages (
            id SERIAL NOT NULL,
            content TEXT NOT NULL,
            author_id INT DEFAULT NULL,
            family_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");

        // Photos
        $this->addSql("CREATE TABLE photos (
            id SERIAL NOT NULL,
            path VARCHAR(255) NOT NULL,
            uploaded_by_id INT DEFAULT NULL,
            event_id INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");

        // Foreign keys
        $this->addSql("ALTER TABLE users ADD CONSTRAINT FK_USERS_FAMILY FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE families ADD CONSTRAINT FK_FAMILIES_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE invitations ADD CONSTRAINT FK_INVITATIONS_FAMILY FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE events ADD CONSTRAINT FK_EVENTS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE events ADD CONSTRAINT FK_EVENTS_FAMILY FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_AUTHOR FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_FAMILY FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE photos ADD CONSTRAINT FK_PHOTOS_UPLOADED_BY FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE photos ADD CONSTRAINT FK_PHOTOS_EVENT FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos DROP CONSTRAINT IF EXISTS FK_PHOTOS_EVENT');
        $this->addSql('ALTER TABLE photos DROP CONSTRAINT IF EXISTS FK_PHOTOS_UPLOADED_BY');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT IF EXISTS FK_MESSAGES_FAMILY');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT IF EXISTS FK_MESSAGES_AUTHOR');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT IF EXISTS FK_EVENTS_FAMILY');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT IF EXISTS FK_EVENTS_CREATED_BY');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT IF EXISTS FK_INVITATIONS_FAMILY');
        $this->addSql('ALTER TABLE families DROP CONSTRAINT IF EXISTS FK_FAMILIES_OWNER');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT IF EXISTS FK_USERS_FAMILY');

        $this->addSql('DROP TABLE IF EXISTS photos');
        $this->addSql('DROP TABLE IF EXISTS messages');
        $this->addSql('DROP TABLE IF EXISTS events');
        $this->addSql('DROP TABLE IF EXISTS invitations');
        $this->addSql('DROP TABLE IF EXISTS families');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}


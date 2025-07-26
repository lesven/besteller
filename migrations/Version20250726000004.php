<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250726000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sender_email column to email_settings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE email_settings ADD sender_email VARCHAR(255) NOT NULL DEFAULT 'noreply@besteller.local'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE email_settings DROP sender_email");
    }
}


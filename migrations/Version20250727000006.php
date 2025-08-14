<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250727000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add confirmation_email_template column to email_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE email_settings ADD confirmation_email_template LONGTEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE email_settings DROP confirmation_email_template");
    }
}

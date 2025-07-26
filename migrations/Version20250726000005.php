<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250726000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add link_email_template column to checklists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE checklists ADD link_email_template LONGTEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE checklists DROP link_email_template");
    }
}

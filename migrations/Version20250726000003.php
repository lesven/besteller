<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250726000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reply_email column to checklists table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE checklists ADD reply_email VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE checklists DROP reply_email");
    }
}

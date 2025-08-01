<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250725000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_settings table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_settings (
            id INT AUTO_INCREMENT NOT NULL,
            host VARCHAR(255) NOT NULL,
            port INT NOT NULL,
            username VARCHAR(255) DEFAULT NULL,
            password VARCHAR(255) DEFAULT NULL,
            ignore_ssl TINYINT(1) NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_settings');
    }
}

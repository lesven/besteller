<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250725000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial database schema for Besteller application';
    }

    public function up(Schema $schema): void
    {
        // Users table
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Checklists table
        $this->addSql('CREATE TABLE checklists (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            target_email VARCHAR(255) NOT NULL,
            email_template LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Checklist groups table
        $this->addSql('CREATE TABLE checklist_groups (
            id INT AUTO_INCREMENT NOT NULL,
            checklist_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            sort_order INT NOT NULL,
            INDEX IDX_CG_CHECKLIST (checklist_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Group items table
        $this->addSql('CREATE TABLE group_items (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            label VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            options LONGTEXT DEFAULT NULL,
            sort_order INT NOT NULL,
            INDEX IDX_GI_GROUP (group_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Submissions table
        $this->addSql('CREATE TABLE submissions (
            id INT AUTO_INCREMENT NOT NULL,
            checklist_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            mitarbeiter_id VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            data JSON NOT NULL,
            generated_email LONGTEXT DEFAULT NULL,
            submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_S_CHECKLIST (checklist_id),
            UNIQUE INDEX UNIQ_CHECKLIST_MITARBEITER (checklist_id, mitarbeiter_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Foreign keys
        $this->addSql('ALTER TABLE checklist_groups ADD CONSTRAINT FK_CG_CHECKLIST 
            FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE group_items ADD CONSTRAINT FK_GI_GROUP 
            FOREIGN KEY (group_id) REFERENCES checklist_groups (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE submissions ADD CONSTRAINT FK_S_CHECKLIST 
            FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checklist_groups DROP FOREIGN KEY FK_CG_CHECKLIST');
        $this->addSql('ALTER TABLE group_items DROP FOREIGN KEY FK_GI_GROUP');
        $this->addSql('ALTER TABLE submissions DROP FOREIGN KEY FK_S_CHECKLIST');
        
        $this->addSql('DROP TABLE submissions');
        $this->addSql('DROP TABLE group_items');
        $this->addSql('DROP TABLE checklist_groups');
        $this->addSql('DROP TABLE checklists');
        $this->addSql('DROP TABLE users');
    }
}

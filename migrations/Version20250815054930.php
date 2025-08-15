<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250815054930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checklist_groups DROP FOREIGN KEY FK_CG_CHECKLIST');
        $this->addSql('ALTER TABLE checklist_groups ADD CONSTRAINT FK_91382E24B16D08A7 FOREIGN KEY (checklist_id) REFERENCES checklists (id)');
        $this->addSql('ALTER TABLE checklist_groups RENAME INDEX idx_cg_checklist TO IDX_91382E24B16D08A7');
        $this->addSql('ALTER TABLE checklists ADD confirmation_email_template LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE email_settings CHANGE sender_email sender_email VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE group_items DROP FOREIGN KEY FK_GI_GROUP');
        $this->addSql('ALTER TABLE group_items ADD CONSTRAINT FK_B132C22AFE54D947 FOREIGN KEY (group_id) REFERENCES checklist_groups (id)');
        $this->addSql('ALTER TABLE group_items RENAME INDEX idx_gi_group TO IDX_B132C22AFE54D947');
        $this->addSql('ALTER TABLE submissions DROP FOREIGN KEY FK_S_CHECKLIST');
        $this->addSql('DROP INDEX UNIQ_CHECKLIST_MITARBEITER ON submissions');
        $this->addSql('ALTER TABLE submissions ADD CONSTRAINT FK_3F6169F7B16D08A7 FOREIGN KEY (checklist_id) REFERENCES checklists (id)');
        $this->addSql('ALTER TABLE submissions RENAME INDEX idx_s_checklist TO IDX_3F6169F7B16D08A7');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checklists DROP confirmation_email_template');
        $this->addSql('ALTER TABLE submissions DROP FOREIGN KEY FK_3F6169F7B16D08A7');
        $this->addSql('ALTER TABLE submissions ADD CONSTRAINT FK_S_CHECKLIST FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CHECKLIST_MITARBEITER ON submissions (checklist_id, mitarbeiter_id)');
        $this->addSql('ALTER TABLE submissions RENAME INDEX idx_3f6169f7b16d08a7 TO IDX_S_CHECKLIST');
        $this->addSql('ALTER TABLE checklist_groups DROP FOREIGN KEY FK_91382E24B16D08A7');
        $this->addSql('ALTER TABLE checklist_groups ADD CONSTRAINT FK_CG_CHECKLIST FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE checklist_groups RENAME INDEX idx_91382e24b16d08a7 TO IDX_CG_CHECKLIST');
        $this->addSql('ALTER TABLE group_items DROP FOREIGN KEY FK_B132C22AFE54D947');
        $this->addSql('ALTER TABLE group_items ADD CONSTRAINT FK_GI_GROUP FOREIGN KEY (group_id) REFERENCES checklist_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_items RENAME INDEX idx_b132c22afe54d947 TO IDX_GI_GROUP');
        $this->addSql('ALTER TABLE email_settings CHANGE sender_email sender_email VARCHAR(255) DEFAULT \'noreply@besteller.local\' NOT NULL');
    }
}

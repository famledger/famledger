<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231219032027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return <<<EOT
- Add statement_id, is_related, tax_payment_id, capture_line and comment to document
- Add foreign keys to document

EOT;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD statement_id INT DEFAULT NULL, ADD is_related TINYINT(1) DEFAULT NULL, ADD tax_payment_id INT DEFAULT NULL, ADD capture_line VARCHAR(24) DEFAULT NULL, ADD comment VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76DF545367 FOREIGN KEY (tax_payment_id) REFERENCES document (id)');
        $this->addSql('CREATE INDEX IDX_D8698A76DF545367 ON document (tax_payment_id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76849CB65B FOREIGN KEY (statement_id) REFERENCES statement (id)');
        $this->addSql('CREATE INDEX IDX_D8698A76849CB65B ON document (statement_id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76849CB65B');
        $this->addSql('DROP INDEX IDX_D8698A76849CB65B ON document');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76DF545367');
        $this->addSql('DROP INDEX IDX_D8698A76DF545367 ON document');
        $this->addSql('ALTER TABLE document DROP statement_id, DROP is_related, DROP tax_payment_id, DROP capture_line, DROP comment');
    }
}

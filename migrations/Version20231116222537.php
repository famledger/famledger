<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231116222537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice substitution';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_task ADD substitutes_invoice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_task ADD CONSTRAINT FK_50BBB9409D5A0651 FOREIGN KEY (substitutes_invoice_id) REFERENCES invoice (id)');
        $this->addSql('CREATE INDEX IDX_50BBB9409D5A0651 ON invoice_task (substitutes_invoice_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_task DROP FOREIGN KEY FK_50BBB9409D5A0651');
        $this->addSql('DROP INDEX IDX_50BBB9409D5A0651 ON invoice_task');
        $this->addSql('ALTER TABLE invoice_task DROP substitutes_invoice_id');
    }
}

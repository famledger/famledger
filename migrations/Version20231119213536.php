<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231119213536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate invoice with attachment and document and revert the association';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD document_id INT DEFAULT NULL, ADD attachment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744C33F7837 FOREIGN KEY (document_id) REFERENCES document (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744464E68B FOREIGN KEY (attachment_id) REFERENCES document (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_90651744C33F7837 ON invoice (document_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_90651744464E68B ON invoice (attachment_id)');
        $this->addSql('UPDATE invoice SET document_id = (SELECT id FROM document WHERE invoice_id = invoice.id AND document.type = \'income\')');
        $this->addSql('UPDATE invoice SET attachment_id = (SELECT id FROM document WHERE invoice_id = invoice.id AND document.type = \'attachment\')');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A762989F1FD');
        $this->addSql('DROP INDEX IDX_D8698A762989F1FD ON document');
        $this->addSql('ALTER TABLE document DROP invoice_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD invoice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A762989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_D8698A762989F1FD ON document (invoice_id)');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744C33F7837');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744464E68B');
        $this->addSql('DROP INDEX UNIQ_90651744C33F7837 ON invoice');
        $this->addSql('DROP INDEX UNIQ_90651744464E68B ON invoice');
        $this->addSql('ALTER TABLE invoice DROP document_id, DROP attachment_id');
    }
}

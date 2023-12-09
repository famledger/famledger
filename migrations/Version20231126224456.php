<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231126224456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add support for email logs and customer emails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, email VARCHAR(255) NOT NULL, category VARCHAR(32) NOT NULL, description VARCHAR(1024) NOT NULL, INDEX IDX_E7927C749395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE email_log (id INT AUTO_INCREMENT NOT NULL, invoice_id INT NOT NULL, emails JSON NOT NULL, message VARCHAR(1024) DEFAULT NULL, status VARCHAR(255) NOT NULL, date_sent DATETIME DEFAULT NULL, document_status VARCHAR(255) DEFAULT NULL, INDEX IDX_6FB48832989F1FD (invoice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C749395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT FK_6FB48832989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');

        $this->addSql('ALTER TABLE customer ADD color VARCHAR(16) DEFAULT NULL');

        $this->addSql('ALTER TABLE account ADD customer_id INT DEFAULT NULL, ADD bank_rfc VARCHAR(15) NOT NULL, ADD bank_name VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A49395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('CREATE INDEX IDX_7D3656A49395C3F3 ON account (customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP FOREIGN KEY FK_7D3656A49395C3F3');
        $this->addSql('DROP INDEX IDX_7D3656A49395C3F3 ON account');
        $this->addSql('ALTER TABLE account DROP customer_id, DROP bank_rfc, DROP bank_name');

        $this->addSql('ALTER TABLE customer DROP color');

        $this->addSql('ALTER TABLE email DROP FOREIGN KEY FK_E7927C749395C3F3');
        $this->addSql('ALTER TABLE email_log DROP FOREIGN KEY FK_6FB48832989F1FD');
        $this->addSql('DROP TABLE email');
        $this->addSql('DROP TABLE email_log');
    }
}
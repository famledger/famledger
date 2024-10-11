<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241011154451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate transactions with accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer CHANGE tenant_id tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE edoc CHANGE tenant_id tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE invoice_schedule CHANGE tenant_id tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE invoice_task CHANGE tenant_id tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE receipt_task CHANGE tenant_id tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE series CHANGE tenant_id tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD account_id INT NOT NULL, ADD tenant_id INT NOT NULL');
        $this->addSql('UPDATE transaction t INNER JOIN statement s on t.statement_id = s.id SET t.account_id = s.account_id');
        $this->addSql('UPDATE transaction t INNER JOIN statement s on t.statement_id = s.id SET t.tenant_id = s.tenant_id');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D19B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D19033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('CREATE INDEX IDX_723705D19B6B5FBA ON transaction (account_id)');
        $this->addSql('CREATE INDEX IDX_723705D19033212A ON transaction (tenant_id)');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer CHANGE tenant_id tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE edoc CHANGE tenant_id tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_schedule CHANGE tenant_id tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_task CHANGE tenant_id tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE receipt_task CHANGE tenant_id tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE series CHANGE tenant_id tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D19B6B5FBA');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D19033212A');
        $this->addSql('DROP INDEX IDX_723705D19B6B5FBA ON transaction');
        $this->addSql('DROP INDEX IDX_723705D19033212A ON transaction');
        $this->addSql('ALTER TABLE transaction DROP account_id, DROP tenant_id');

    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231209234623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return <<<EOT
Multiple changes:
    - Add receipt_task table
    - Add year, month and sub_type columns to document table
    - Add payment_id, receipt_task_id, task_id, cfdi and discr columns to invoice table
    - Add type column to series table
    - Add discr column to transaction table
    - Update document table with new values for sub_type column
    - Update invoice table with new values for discr column
    - Update series table with new values for source column
    - Update transaction table with new values for discr column
EOT;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE receipt_task (id INT AUTO_INCREMENT NOT NULL, substitutes_invoice_id INT DEFAULT NULL, beneficiary_account_id INT NOT NULL, originator_account_id INT NOT NULL, customer_id INT NOT NULL, tenant_id INT DEFAULT NULL, series_id INT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, concept VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, last_executed DATETIME DEFAULT NULL, live_mode TINYINT(1) DEFAULT NULL, receipt_template LONGTEXT DEFAULT NULL, amount INT NOT NULL, request_data JSON DEFAULT NULL, created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_C405E0A99D5A0651 (substitutes_invoice_id), INDEX IDX_C405E0A9774744B0 (beneficiary_account_id), INDEX IDX_C405E0A9A9DDCE69 (originator_account_id), INDEX IDX_C405E0A99395C3F3 (customer_id), INDEX IDX_C405E0A99033212A (tenant_id), INDEX IDX_C405E0A95278319C (series_id), INDEX IDX_C405E0A9DE12AB56 (created_by), INDEX IDX_C405E0A916FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A99D5A0651 FOREIGN KEY (substitutes_invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A9774744B0 FOREIGN KEY (beneficiary_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A9A9DDCE69 FOREIGN KEY (originator_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A99395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A99033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A95278319C FOREIGN KEY (series_id) REFERENCES series (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A9DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE receipt_task ADD CONSTRAINT FK_C405E0A916FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');

        $this->addSql('ALTER TABLE document ADD year SMALLINT DEFAULT NULL, ADD month SMALLINT DEFAULT NULL, ADD sub_type VARCHAR(32) DEFAULT NULL');
        $this->addSql(<<<EOT
UPDATE document
SET
    sub_type = 'tax-notice',
    year = IF(specs->>'$.year' != 'null', CAST(specs->>'$.year' AS UNSIGNED), NULL),
    month = IF(specs->>'$.month' != 'null', CAST(specs->>'$.month' AS UNSIGNED), NULL)
WHERE type='attachment'
  AND filename LIKE '%acuse%';

UPDATE document
SET
    sub_type = 'tax-calculus',
    year = IF(specs->>'$.year' != 'null', CAST(specs->>'$.year' AS UNSIGNED), NULL),
    month = IF(specs->>'$.month' != 'null', CAST(specs->>'$.month' AS UNSIGNED), NULL)
WHERE type='attachment'
  AND filename LIKE '%declaracion%' 
  AND filename NOT LIKE '%acuse%';
EOT
        );

        $this->addSql('ALTER TABLE invoice DROP INDEX UNIQ_906517449D5A0651, ADD INDEX IDX_906517449D5A0651 (substitutes_invoice_id)');
        $this->addSql('ALTER TABLE invoice DROP INDEX UNIQ_90651744C33F7837, ADD INDEX IDX_90651744C33F7837 (document_id)');
        $this->addSql('ALTER TABLE invoice DROP INDEX UNIQ_90651744464E68B, ADD INDEX IDX_90651744464E68B (attachment_id)');
        $this->addSql('ALTER TABLE invoice ADD payment_id INT DEFAULT NULL, ADD receipt_task_id INT DEFAULT NULL, ADD task_id INT DEFAULT NULL, ADD cfdi LONGTEXT DEFAULT NULL, ADD discr VARCHAR(20) NOT NULL, CHANGE currency currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517444C3A3BB FOREIGN KEY (payment_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517449EFCA478 FOREIGN KEY (receipt_task_id) REFERENCES receipt_task (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517448DB60186 FOREIGN KEY (task_id) REFERENCES receipt_task (id)');
        $this->addSql('CREATE INDEX IDX_906517444C3A3BB ON invoice (payment_id)');
        $this->addSql('CREATE INDEX IDX_906517449EFCA478 ON invoice (receipt_task_id)');
        $this->addSql('CREATE INDEX IDX_906517448DB60186 ON invoice (task_id)');
        $this->addSql('ALTER TABLE series ADD type VARCHAR(16) DEFAULT NULL, CHANGE source source VARCHAR(16) DEFAULT NULL');
        $this->addSql('UPDATE series SET source = \'RECEIPT\' WHERE code IN (\'REP\',\'RP\')');
        $this->addSql(<<<EOT
UPDATE series SET type='invoice';
UPDATE series SET type='payment' WHERE LENGTH(code) > 1;
UPDATE invoice SET discr='invoice';
UPDATE invoice i 
INNER JOIN series s ON i.tenant_id = s.tenant_id AND i.series = s.code
SET i.discr = s.type;
EOT
        );

        $this->addSql('ALTER TABLE transaction ADD discr VARCHAR(20) NOT NULL');
        $this->addSql('UPDATE transaction SET discr=\'transaction\'');
        $this->addSql('UPDATE transaction SET discr=\'payment-transaction\' WHERE amount >0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517449EFCA478');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517448DB60186');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A99D5A0651');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A9774744B0');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A9A9DDCE69');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A99395C3F3');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A99033212A');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A95278319C');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A9DE12AB56');
        $this->addSql('ALTER TABLE receipt_task DROP FOREIGN KEY FK_C405E0A916FE72E1');
        $this->addSql('DROP TABLE receipt_task');
        $this->addSql('ALTER TABLE document DROP year, DROP month, DROP sub_type');
        $this->addSql('ALTER TABLE invoice DROP INDEX IDX_90651744C33F7837, ADD UNIQUE INDEX UNIQ_90651744C33F7837 (document_id)');
        $this->addSql('ALTER TABLE invoice DROP INDEX IDX_906517449D5A0651, ADD UNIQUE INDEX UNIQ_906517449D5A0651 (substitutes_invoice_id)');
        $this->addSql('ALTER TABLE invoice DROP INDEX IDX_90651744464E68B, ADD UNIQUE INDEX UNIQ_90651744464E68B (attachment_id)');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517444C3A3BB');
        $this->addSql('DROP INDEX IDX_906517444C3A3BB ON invoice');
        $this->addSql('DROP INDEX IDX_906517449EFCA478 ON invoice');
        $this->addSql('DROP INDEX IDX_906517448DB60186 ON invoice');
        $this->addSql('ALTER TABLE invoice DROP payment_id, DROP receipt_task_id, DROP task_id, DROP cfdi, DROP discr, CHANGE currency currency VARCHAR(3) NOT NULL');
        $this->addSql('UPDATE series SET source = \'WEB\' WHERE code IN (\'REP\',\'RP\')');
        $this->addSql('ALTER TABLE series DROP type, CHANGE source source VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction DROP discr');
    }
}

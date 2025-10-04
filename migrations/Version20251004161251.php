<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251004161251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contract entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, customer_id INT NOT NULL, property_id INT NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, signing_date DATE DEFAULT NULL, monthly_amount NUMERIC(10, 2) NOT NULL, notary_name VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_E98F28599033212A (tenant_id), INDEX IDX_E98F28599395C3F3 (customer_id), INDEX IDX_E98F2859549213EC (property_id), INDEX IDX_E98F2859DE12AB56 (created_by), INDEX IDX_E98F285916FE72E1 (updated_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28599033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28599395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859549213EC FOREIGN KEY (property_id) REFERENCES property (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F285916FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28599033212A');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28599395C3F3');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859549213EC');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859DE12AB56');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285916FE72E1');
        $this->addSql('DROP TABLE contract');
    }
}

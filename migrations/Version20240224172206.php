<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240224172206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account and statement type fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD type VARCHAR(16) NOT NULL, ADD is_active TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE statement ADD type VARCHAR(16) NOT NULL');

        $this->addSql("UPDATE account SET type = 'bank-account', is_active = 1");
        $this->addSql("UPDATE account SET type = 'credit-card' WHERE id=7");

        $this->addSql("UPDATE statement s INNER JOIN account a on s.account_id = a.id SET s.type = a.type");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP type, DROP is_active');
        $this->addSql('ALTER TABLE statement DROP type');
    }
}

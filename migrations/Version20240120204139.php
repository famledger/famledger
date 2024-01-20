<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240120204139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Assert unique statement per account and month';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX statement_year_month ON statement (account_id, year, month)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX statement_year_month ON statement');
    }
}

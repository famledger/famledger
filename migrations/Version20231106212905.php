<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231106212905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make scheduled_date and next_issue_date nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_schedule CHANGE scheduled_date scheduled_date DATE DEFAULT NULL, CHANGE next_issue_date next_issue_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_schedule CHANGE scheduled_date scheduled_date DATE NOT NULL, CHANGE next_issue_date next_issue_date DATE NOT NULL');
    }
}

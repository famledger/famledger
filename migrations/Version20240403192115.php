<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240403192115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add regime_type to invoice_schedule and invoice_task';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_schedule ADD regime_type VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_task ADD regime_type VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_schedule DROP regime_type');
        $this->addSql('ALTER TABLE invoice_task DROP regime_type');
    }
}

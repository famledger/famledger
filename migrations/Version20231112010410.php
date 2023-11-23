<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231112010410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds invoice_series and invoice_number to document table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD invoice_series VARCHAR(16) DEFAULT NULL, ADD invoice_number INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP invoice_series, DROP invoice_number');
    }
}

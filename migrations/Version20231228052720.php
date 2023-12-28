<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231228052720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active to customer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD is_active TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP is_active');
    }
}

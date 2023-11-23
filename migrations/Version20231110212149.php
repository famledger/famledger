<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231110212149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add comment to customer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer ADD comment LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer DROP comment');
    }
}

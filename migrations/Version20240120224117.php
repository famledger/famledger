<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240120224117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Assert unique eDoc per checksum';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX edoc_checksum ON edoc (checksum)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX edoc_checksum ON edoc');
    }
}

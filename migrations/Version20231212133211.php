<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231212133211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return ' Adds uuid to invoice';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD uuid VARCHAR(64) DEFAULT NULL');
        $this->addSql(<<<EOT
UPDATE invoice i
SET i.uuid = data->>'$.folioFiscalUUID'
WHERE i.uuid IS NULL
EOT
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP uuid');
    }
}

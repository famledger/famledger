<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231210051736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return <<<EOT
- Add unique index to invoice url_pdf, url_xml and tenant_id, series, number, live_mode
- Change url_pdf and url_xml to VARCHAR(512)'
EOT;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_906517445BCB0B1E ON invoice');
        $this->addSql('DROP INDEX UNIQ_9065174464CF08F1 ON invoice');
        $this->addSql('ALTER TABLE invoice CHANGE url_pdf url_pdf VARCHAR(512) DEFAULT NULL, CHANGE url_xml url_xml VARCHAR(512) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX invoice_url_pdf ON invoice (url_pdf, tenant_id, live_mode)');
        $this->addSql('CREATE UNIQUE INDEX invoice_url_xml ON invoice (url_xml, tenant_id, live_mode)');
        $this->addSql('CREATE UNIQUE INDEX invoice_tenant_series_number ON invoice (tenant_id, series, number, live_mode)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX invoice_url_pdf ON invoice');
        $this->addSql('DROP INDEX invoice_url_xml ON invoice');
        $this->addSql('DROP INDEX invoice_tenant_series_number ON invoice');
        $this->addSql('ALTER TABLE invoice CHANGE url_pdf url_pdf VARCHAR(1024) DEFAULT NULL, CHANGE url_xml url_xml VARCHAR(1024) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_906517445BCB0B1E ON invoice (url_pdf)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9065174464CF08F1 ON invoice (url_xml)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230123172719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove siteType and centrifugeType properties from NphSite entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_sites DROP site_type, DROP centrifuge_type');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_sites ADD site_type VARCHAR(100) DEFAULT NULL, ADD centrifuge_type VARCHAR(50) DEFAULT NULL');
    }
}

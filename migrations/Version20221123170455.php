<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221123170455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds volume & units property. Rename type to aliquotCode';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_aliquots ADD volume DOUBLE PRECISION DEFAULT NULL, ADD units VARCHAR(10) DEFAULT NULL, CHANGE type aliquot_code VARCHAR(100) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_aliquots DROP volume, DROP units, CHANGE aliquot_code type VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}

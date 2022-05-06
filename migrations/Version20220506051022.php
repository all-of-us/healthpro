<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220506051022 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create IncentiveImport Entity';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE incentive_import (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, site VARCHAR(50) NOT NULL, created_ts DATETIME NOT NULL, import_status TINYINT DEFAULT 0 NOT NULL, confirm TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_4650598A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE incentive_import');
    }
}

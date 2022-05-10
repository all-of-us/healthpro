<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220506051022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates IncentiveImport, IncentiveImportRow entities and update Incentive entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE incentive_import (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, site VARCHAR(50) NOT NULL, created_ts DATETIME NOT NULL, import_status TINYINT DEFAULT 0 NOT NULL, confirm TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE incentive_import_row (id INT AUTO_INCREMENT NOT NULL, import_id INT NOT NULL, participant_id VARCHAR(50) NOT NULL, incentive_date_given DATETIME DEFAULT NULL, incentive_type VARCHAR(50) DEFAULT NULL, other_incentive_type VARCHAR(255) DEFAULT NULL, incentive_occurrence VARCHAR(50) DEFAULT NULL, other_incentive_occurrence VARCHAR(255) DEFAULT NULL, incentive_amount INT DEFAULT NULL, gift_card_type VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, declined TINYINT DEFAULT 0 NOT NULL, rdr_status TINYINT DEFAULT 0 NOT NULL, INDEX IDX_A883BF7AB6A263D9 (import_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE incentive ADD import_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE incentive_import');
        $this->addSql('DROP TABLE incentive_import_row');
        $this->addSql('ALTER TABLE incentive DROP COLUMN `import_id`');
    }
}

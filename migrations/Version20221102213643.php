<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221102213643 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Creates NphOrder, NphSample, and NphAliquot entities';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE nph_orders (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, cancelled_user_id INT DEFAULT NULL, order_id VARCHAR(100) NOT NULL, participant_id VARCHAR(50) NOT NULL, timepoint VARCHAR(50) NOT NULL, visit_type VARCHAR(50) NOT NULL, site VARCHAR(50) NOT NULL, created_ts DATETIME NOT NULL, cancelled_ts DATETIME DEFAULT NULL, cancelled_site VARCHAR(50) DEFAULT NULL, UNIQUE INDEX order_id (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nph_samples (id INT AUTO_INCREMENT NOT NULL, nph_order_id INT NOT NULL, collected_user_id INT DEFAULT NULL, finalized_user_id INT DEFAULT NULL, sample_id VARCHAR(100) NOT NULL, sample_code VARCHAR(50) NOT NULL, sample_metadata LONGTEXT DEFAULT NULL, collected_site VARCHAR(50) DEFAULT NULL, collected_ts DATETIME DEFAULT NULL, collected_notes LONGTEXT DEFAULT NULL, finalized_site VARCHAR(50) DEFAULT NULL, finalized_ts DATETIME DEFAULT NULL, finalized_notes LONGTEXT DEFAULT NULL, rdr_id VARCHAR(50) DEFAULT NULL, UNIQUE INDEX sample_id (sample_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nph_aliquots (id INT AUTO_INCREMENT NOT NULL, nph_sample_id INT NOT NULL, aliquot_id VARCHAR(100) NOT NULL, aliquot_ts DATETIME NOT NULL, type VARCHAR(100) NOT NULL, UNIQUE INDEX aliquot_id (aliquot_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE nph_orders');
        $this->addSql('DROP TABLE nph_samples');
        $this->addSql('DROP TABLE nph_aliquots');
    }
}

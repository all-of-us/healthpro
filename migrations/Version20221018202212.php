<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221018202212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create NphSite Entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE nph_sites (id INT AUTO_INCREMENT NOT NULL, status TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, google_group VARCHAR(255) NOT NULL, organization_id VARCHAR(255) DEFAULT NULL, awardee_id VARCHAR(255) DEFAULT NULL, type VARCHAR(100) DEFAULT NULL, site_type VARCHAR(100) DEFAULT NULL, centrifuge_type VARCHAR(50) DEFAULT NULL, email VARCHAR(512) DEFAULT NULL, deleted TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE nph_sites');
    }
}

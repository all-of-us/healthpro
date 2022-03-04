<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220304191506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE incentive (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, amended_user_id INT DEFAULT NULL, cancelled_user_id INT DEFAULT NULL, participant_id VARCHAR(50) NOT NULL, site VARCHAR(50) NOT NULL, incentive_date_given DATETIME NOT NULL, incentive_type VARCHAR(50) NOT NULL, other_incentive_type VARCHAR(255) DEFAULT NULL, incentive_occurance VARCHAR(50) NOT NULL, other_incentive_occurance VARCHAR(255) DEFAULT NULL, incentive_amount VARCHAR(50) NOT NULL, other_incentive_amount INT DEFAULT NULL, gift_card_type VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_ts DATETIME NOT NULL, amended_ts DATETIME DEFAULT NULL, cancelled_ts DATETIME DEFAULT NULL, rdr_ts DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE incentive');
    }
}

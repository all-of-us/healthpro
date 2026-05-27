<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pediatric assent table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE pediatric_assent (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, measurement_id INT DEFAULT NULL, order_id INT DEFAULT NULL, participant_id VARCHAR(50) NOT NULL, created_by VARCHAR(255) NOT NULL, site VARCHAR(50) NOT NULL, assent_type VARCHAR(50) NOT NULL, assent_response VARCHAR(50) NOT NULL, created_ts DATETIME NOT NULL, created_timezone_id INT NOT NULL, api_assent_id VARCHAR(100) DEFAULT NULL, api_status VARCHAR(20) NOT NULL, api_error LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE pediatric_assent');
    }
}

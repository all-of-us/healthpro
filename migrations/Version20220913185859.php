<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220913185859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates IdVerification entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE id_verification (id INT AUTO_INCREMENT NOT NULL, participant_id VARCHAR(50) NOT NULL, user_id INT NOT NULL, site VARCHAR(50) NOT NULL, verified_date DATETIME NOT NULL, verification_type VARCHAR(255) DEFAULT NULL, visit_type VARCHAR(255) DEFAULT NULL, created_ts DATETIME NOT NULL, import_id INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE id_verification');
    }
}

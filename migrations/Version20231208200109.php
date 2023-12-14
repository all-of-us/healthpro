<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231208200109 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create IdVerificationRdr Entity';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE id_verification_rdr (id INT AUTO_INCREMENT NOT NULL, participant_id VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, user_id INT NOT NULL, site_name VARCHAR(255) NOT NULL, site_id VARCHAR(255) NOT NULL, verified_date DATETIME NOT NULL, verification_type VARCHAR(255) NOT NULL, visit_type VARCHAR(255) NOT NULL, created_ts DATETIME NOT NULL, insert_id INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE id_verification_rdr');
    }
}

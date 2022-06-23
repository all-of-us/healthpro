<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220607160140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates IdVerificationImport, IdVerificationImportRow entities';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE id_verification_import (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, site VARCHAR(255) NOT NULL, created_ts DATETIME NOT NULL, import_status TINYINT DEFAULT 0 NOT NULL, confirm TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE id_verification_import_row (id INT AUTO_INCREMENT NOT NULL, import_id INT NOT NULL, participant_id VARCHAR(50) NOT NULL, user_email VARCHAR(255) DEFAULT NULL, verified_date DATETIME NOT NULL, verification_type VARCHAR(255) DEFAULT NULL, visit_type VARCHAR(255) DEFAULT NULL, rdr_status TINYINT NOT NULL, INDEX IDX_E5A10B05B6A263D9 (import_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE id_verification_import');
        $this->addSql('DROP TABLE id_verification_import_row');
    }
}

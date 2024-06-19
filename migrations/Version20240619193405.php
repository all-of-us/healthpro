<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240619193405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Cron NPH Sample Processing Status entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE cron_nph_sample_processing_status_log (id INT AUTO_INCREMENT NOT NULL, participant_id VARCHAR(50) NOT NULL, module VARCHAR(10) NOT NULL, period VARCHAR(10) NOT NULL, status INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE cron_nph_sample_processing_status_log');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211016014035 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds SiteSync entity.';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE site_sync (id INT AUTO_INCREMENT NOT NULL, site_id INT NOT NULL, admin_emails_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_17B14883F6BD1646 (site_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE site_sync ADD CONSTRAINT FK_17B14883F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE site_sync');
    }
}

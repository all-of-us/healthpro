<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230906173509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds user for NPH DLW Dosages for finalization records';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE nph_dlw ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE nph_dlw ADD CONSTRAINT FK_3F1C65B7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_3F1C65B7A76ED395 ON nph_dlw (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE nph_dlw DROP FOREIGN KEY FK_3F1C65B7A76ED395');
        $this->addSql('DROP INDEX IDX_3F1C65B7A76ED395 ON nph_dlw');
        $this->addSql('ALTER TABLE nph_dlw DROP user_id');
    }
}

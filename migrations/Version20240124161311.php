<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240124161311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visit period property';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_dlw ADD visit_period VARCHAR(50) DEFAULT NULL');

        $this->addSql('ALTER TABLE nph_dlw MODIFY COLUMN visit VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_dlw DROP visit_period');

        $this->addSql('ALTER TABLE nph_dlw MODIFY COLUMN visit VARCHAR(50) NOT NULL');
    }
}

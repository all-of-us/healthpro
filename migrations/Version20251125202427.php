<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251125202427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename table height_for_age_24months_to_6years to height_for_age_24months_and_up';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE height_for_age_24months_to_6years RENAME TO height_for_age_24months_and_up');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE height_for_age_24months_and_up RENAME TO height_for_age_24months_to_6years');
    }
}

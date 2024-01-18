<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240112183055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visit period property';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders ADD visit_period VARCHAR(50) DEFAULT NULL');

        $this->addSql('ALTER TABLE nph_orders MODIFY COLUMN visit_type VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders DROP visit_period');

        $this->addSql('ALTER TABLE nph_orders MODIFY COLUMN visit_type VARCHAR(50) NOT NULL');
    }
}

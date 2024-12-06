<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241205215727 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Increase site column size in orders and evaluations table';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE orders MODIFY COLUMN site VARCHAR(255), MODIFY COLUMN collected_site VARCHAR(255), MODIFY COLUMN processed_site VARCHAR(255), MODIFY COLUMN finalized_site VARCHAR(255)');
        $this->addSql('ALTER TABLE orders_history MODIFY COLUMN site VARCHAR(255)');
        $this->addSql('ALTER TABLE evaluations MODIFY COLUMN site VARCHAR(255), MODIFY COLUMN finalized_site VARCHAR(255)');
        $this->addSql('ALTER TABLE evaluations_history MODIFY COLUMN site VARCHAR(255)');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE orders MODIFY COLUMN site VARCHAR(50), MODIFY COLUMN collected_site VARCHAR(50), MODIFY COLUMN processed_site VARCHAR(50), MODIFY COLUMN finalized_site VARCHAR(50)');
        $this->addSql('ALTER TABLE orders_history MODIFY COLUMN site VARCHAR(50)');
        $this->addSql('ALTER TABLE evaluations MODIFY COLUMN site VARCHAR(50), MODIFY COLUMN finalized_site VARCHAR(50)');
        $this->addSql('ALTER TABLE evaluations_history MODIFY COLUMN site VARCHAR(50)');
    }
}

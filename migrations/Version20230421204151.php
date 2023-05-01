<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230420195952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter orders table add timezone fields';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE orders ADD created_timezone_id INT DEFAULT NULL, ADD collected_timezone_id INT DEFAULT NULL, ADD processed_timezone_id INT DEFAULT NULL, ADD finalized_timezone_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders_history ADD created_timezone_id INT DEFAULT NULL');

    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE orders DROP created_timezone_id, DROP collected_timezone_id, DROP processed_timezone_id, DROP finalized_timezone_id');
        $this->addSql('ALTER TABLE orders_history DROP created_timezone_id');
    }
}

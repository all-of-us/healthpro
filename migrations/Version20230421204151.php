<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230420195952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter orders table add timezone fields';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE orders ADD created_timezone VARCHAR(100) DEFAULT NULL, collected_timezone VARCHAR(100) DEFAULT NULL, processed_timezone VARCHAR(100) DEFAULT NULL, finalized_timezone VARCHAR(100) DEFAULT NULL');

    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE orders DROP created_timezone, DROP collected_timezone, DROP processed_timezone, DROP finalized_timezone');

    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220810214346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change incentive table participant_id and site column collation';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE incentive MODIFY participant_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->addSql('ALTER TABLE incentive MODIFY site VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE incentive MODIFY participant_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE incentive MODIFY site VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
}

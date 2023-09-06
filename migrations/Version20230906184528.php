<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230906184528 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create WeightForLength23MonthsTo5Years Entity';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE weight_for_length_24months_to_5years (sex TINYINT(1) NOT NULL, length DOUBLE NOT NULL, L DOUBLE NOT NULL, M DOUBLE NOT NULL, S DOUBLE NOT NULL, INDEX (sex)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE weight_for_length_24months_to_5years');
    }
}

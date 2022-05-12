<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220504033557 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Update Incentive Entity';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE incentive MODIFY `incentive_date_given` DATETIME DEFAULT NULL, MODIFY `incentive_type` VARCHAR(50) DEFAULT NULL, MODIFY `incentive_occurrence` VARCHAR(50) DEFAULT NULL, MODIFY `incentive_amount` INT DEFAULT NULL');
        $this->addSql('ALTER TABLE incentive ADD COLUMN `declined` TINYINT(1) DEFAULT \'0\' NOT NULL');

    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE incentive MODIFY `incentive_date_given` DATETIME NOT NULL, MODIFY `incentive_type` VARCHAR(50) NOT NULL, MODIFY `ncentive_occurrence` VARCHAR(50) NOT NULL, MODIFY `incentive_amount` INT NOT NULL');
        $this->addSql('ALTER TABLE incentive DROP COLUMN `declined`');
    }
}

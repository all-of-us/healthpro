<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230906202000 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create HeightForAge0To23Months Entity';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE height_for_age_0_to_23_months (id INT AUTO_INCREMENT NOT NULL, sex TINYINT(1) NOT NULL, month INT NOT NULL, L DOUBLE NOT NULL, M DOUBLE NOT NULL, S DOUBLE NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE height_for_age_0_to_23_months');
    }
}

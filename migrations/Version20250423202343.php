<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250423202343 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create NPH admin order edit log entity';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE nph_admin_order_edit_log (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, order_id VARCHAR(100) NOT NULL, original_order_generation_ts DATETIME NOT NULL, original_order_generation_timezone_id INT DEFAULT NULL, updated_order_generation_ts DATETIME NOT NULL, updated_order_generation_timezone_id INT NOT NULL, created_ts DATETIME NOT NULL, created_timezone_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE nph_admin_order_edit_log');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230508162708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter nph_orders, nph_samples tables add timezone fields';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE nph_orders ADD created_timezone_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nph_samples ADD collected_timezone_id INT DEFAULT NULL, ADD finalized_timezone_id INT DEFAULT NULL, ADD modified_timezone_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE nph_orders DROP created_timezone_id');
        $this->addSql('ALTER TABLE nph_samples DROP collected_timezone_id, DROP finalized_timezone_id, DROP modified_timezone_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221220192629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change cancelled_ts, cancelled_user, cancelled_site properties to modified_ts, modified_user, modified_site in NphOrder entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders CHANGE cancelled_user_id modified_user_id INT DEFAULT NULL, CHANGE cancelled_ts modified_ts DATETIME DEFAULT NULL, CHANGE cancelled_site modified_site VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders CHANGE modified_user_id cancelled_user_id INT DEFAULT NULL, CHANGE modified_ts cancelled_ts DATETIME DEFAULT NULL, CHANGE modified_site cancelled_site VARCHAR(50) DEFAULT NULL');
    }
}

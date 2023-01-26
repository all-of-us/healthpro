<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230105215332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove modifiedTs, modifiedUser, modifiedSite, modifyReason, and modifyType properties from NphOrder entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders DROP modified_user_id, DROP modified_ts, DROP modified_site, DROP modify_reason, DROP modify_type');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders ADD modified_user_id INT DEFAULT NULL, ADD modified_ts DATETIME DEFAULT NULL, ADD modified_site VARCHAR(50) DEFAULT NULL, ADD modify_reason LONGTEXT DEFAULT NULL, ADD modify_type VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL');
    }
}

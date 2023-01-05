<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221227172800 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add modifiedTs, modifiedUser, modifiedSite, modifyReason, and modifyType properties to NphSample entity';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_samples ADD modified_user_id INT DEFAULT NULL, ADD modified_site VARCHAR(50) DEFAULT NULL, ADD modified_ts DATETIME DEFAULT NULL, ADD modify_reason LONGTEXT DEFAULT NULL, ADD modify_type VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_samples DROP modified_user_id, DROP modified_site, DROP modified_ts, DROP modify_reason, DROP modify_type');
    }
}

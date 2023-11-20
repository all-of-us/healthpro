<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231120043125 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds relation between downtime_generated_user_id and users.id in nph_orders table as well as downtime_generated column to flag orders as generated during downtime.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE nph_orders ADD downtime_generated_user_id INT DEFAULT NULL, ADD downtime_generated TINYINT(1) NOT NULL, ADD downtime_generated_ts DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE nph_orders ADD CONSTRAINT FK_5E79E17DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE nph_orders ADD CONSTRAINT FK_5E79E17D8B333355 FOREIGN KEY (downtime_generated_user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_5E79E17DA76ED395 ON nph_orders (user_id)');
        $this->addSql('CREATE INDEX IDX_5E79E17D8B333355 ON nph_orders (downtime_generated_user_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_orders DROP FOREIGN KEY FK_5E79E17DA76ED395');
        $this->addSql('ALTER TABLE nph_orders DROP FOREIGN KEY FK_5E79E17D8B333355');
        $this->addSql('DROP INDEX IDX_5E79E17DA76ED395 ON nph_orders');
        $this->addSql('DROP INDEX IDX_5E79E17D8B333355 ON nph_orders');
        $this->addSql('ALTER TABLE nph_orders DROP downtime_generated_user_id, DROP downtime_generated, DROP downtime_generated_ts');
    }
}

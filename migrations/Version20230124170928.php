<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230124170928 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create a schema to hold Henry Ford Repair Data';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("create table henry_ford_repair (id int auto_increment not null primary key, participant_id varchar(100), awardee_id varchar(100), organization_id varchar(100), current_pairing_site varchar(100), repair_site varchar(100))");
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE henry_ford_repair');
    }
}

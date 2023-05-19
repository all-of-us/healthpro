<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230519135344 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds biobank finalized field to the nph_samples table to show when a sample has been finalized by the biobank';
    }

    public function up(Schema $schema) : void
    {
       $this->addSql('ALTER TABLE nph_samples ADD biobank_finalized boolean default false');
    }

    public function down(Schema $schema) : void
    {
       $this->addSql('ALTER TABLE nph_samples DROP biobank_finalized');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240516141832 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds incomplete_samples column to nph_sample_processing_status, and nph_generate_order_warning_log tables to track number of incomplete samples and previous_status column to track the previous status';
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_sample_processing_status ADD incomplete_samples INT DEFAULT NULL, ADD previous_status INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nph_generate_order_warning_log ADD incomplete_samples INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nph_sample_processing_status DROP incomplete_samples, DROP previous_status');
        $this->addSql('ALTER TABLE nph_generate_order_warning_log DROP incomplete_samples');
    }
}

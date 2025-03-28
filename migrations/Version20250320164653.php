<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250320164653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sex at birth field to evaluations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evaluations ADD sex_at_birth TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evaluations DROP sex_at_birth');
    }
}

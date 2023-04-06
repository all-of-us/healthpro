<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230406141013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('UPDATE nph_field_sort SET sort_order = sort_order + 1 WHERE field_value in (\'240min\', \'postLMT\')');
        $this->addSql('INSERT INTO nph_field_sort (field_value, sort_order) VALUES (\'180min\', 9)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('UPDATE nph_field_sort SET sort_order = 9 WHERE field_value = \'240min\'');
        $this->addSql('UPDATE nph_field_sort SET sort_order = 10 WHERE field_value = \'postLMT\'');
        $this->addSql('DELETE FROM nph_field_sort WHERE field_value = \'180min\'');
    }
}

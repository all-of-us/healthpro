<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230210142836 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE nph_field_sort (id int auto_increment not null primary key, field_value varchar(100) not null, sort_order int not null)');
        $this->addSql("INSERT INTO nph_field_sort (field_value, sort_order)
                            VALUES ('preLMT', 1), ('minus15min', 2), ('minus5min', 3), ('15min', 4), ('30min', 5), ('60min', 6), ('90min', 7), ('120min', 8),
                                   ('240min', 9), ('postLMT', 10), ('orangeDiet', 1), ('orangeDLW', 2), ('orangeDSMT', 3), ('orangeLMT', 4), ('blueDiet', 5),
                                   ('blueDLW', 6), ('blueDSMT', 7), ('blueLMT', 8), ('purpleDiet', 9), ('purpleDLW', 10), ('purpleDSMT', 11), ('purpleLMT', 12);");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE nph_field_sort');
    }
}

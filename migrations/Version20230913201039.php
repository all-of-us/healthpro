<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230913201039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Created HeartRateAge entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE heart_rate_age (id INT AUTO_INCREMENT NOT NULL, start_age INT NOT NULL, end_age INT NOT NULL, centile1 INT NOT NULL, centile99 INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO heart_rate_age (start_age, end_age, centile1, centile99) VALUES
                        ('0', '3', '107', '181'),
                        ('3', '6', '104', '175'),
                        ('6', '9', '98', '168'),
                        ('9', '12', '93', '161'),
                        ('12', '18', '88', '156'),
                        ('18', '24', '82', '149'),
                        ('24', '36', '76', '142'),
                        ('36', '48', '70', '136'),
                        ('48', '72', '65', '131'),
                        ('72', '96', '59', '123'),
                        ('96', '144', '52', '115'),
                        ('144', '180', '47', '108'),
                        ('180', '216', '43', '104')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE heart_rate_age');
    }
}

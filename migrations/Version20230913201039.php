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

        $this->addSql('CREATE TABLE heart_rate_age (id INT AUTO_INCREMENT NOT NULL, age_type VARCHAR(10) NOT NULL, start_age INT NOT NULL, end_age INT NOT NULL, centile1 INT NOT NULL, centile99 INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO heart_rate_age (age_type, start_age, end_age, centile1, centile99) VALUES
                        ('M', '0', '3', '107', '181'),
                        ('M', '3', '6', '104', '175'),
                        ('M', '6', '9', '98', '168'),
                        ('M', '9', '12', '93', '161'),
                        ('M', '12', '18', '88', '156'),
                        ('M', '18', '24', '82', '149'),
                        ('Y', '2', '3', '76', '142'),
                        ('Y', '3', '4', '70', '136'),
                        ('Y', '4', '6', '65', '131'),
                        ('Y', '6', '8', '59', '123'),
                        ('Y', '8', '12', '52', '115'),
                        ('Y', '12', '15', '47', '108'),
                        ('Y', '15', '18', '43', '104')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE heart_rate_age');
    }
}

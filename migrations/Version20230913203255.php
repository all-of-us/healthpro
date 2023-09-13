<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230913203255 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Creates BloodPressureSystolicHeightPercentile entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE blood_pressure_systolic_height_percentile (id INT AUTO_INCREMENT NOT NULL, sex TINYINT(1) NOT NULL, age_year INT NOT NULL, bp_centile INT NOT NULL, height_per_5 INT NOT NULL, height_per_10 INT NOT NULL, height_per_25 INT NOT NULL, height_per_50 INT NOT NULL, height_per_75 INT NOT NULL, height_per_90 INT NOT NULL, height_per_95 INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO blood_pressure_systolic_height_percentile (sex, age_year, bp_centile, height_per_5, height_per_10, height_per_25, height_per_50, height_per_75, height_per_90, height_per_95) VALUES
                        ('1', '1', '50', '85', '85', '86', '86', '87', '88', '88'),
                        ('1', '1', '90', '98', '99', '99', '100', '100', '101', '101'),
                        ('1', '1', '95', '102', '102', '103', '103', '104', '105', '105'),
                        ('1', '2', '50', '87', '87', '88', '89', '89', '90', '91'),
                        ('1', '2', '90', '100', '100', '101', '102', '103', '103', '104'),
                        ('1', '2', '95', '104', '105', '105', '106', '107', '107', '108'),
                        ('1', '3', '50', '88', '89', '89', '90', '91', '92', '92'),
                        ('1', '3', '90', '101', '102', '102', '103', '104', '105', '105'),
                        ('1', '3', '95', '106', '106', '107', '107', '108', '109', '109'),
                        ('1', '4', '50', '90', '90', '91', '92', '93', '94', '94'),
                        ('1', '4', '90', '102', '103', '104', '105', '105', '106', '107'),
                        ('1', '4', '95', '107', '107', '108', '108', '109', '110', '110'),
                        ('1', '5', '50', '91', '92', '93', '94', '95', '96', '96'),
                        ('1', '5', '90', '103', '104', '105', '106', '107', '108', '108'),
                        ('1', '5', '95', '107', '108', '109', '109', '110', '111', '112'),
                        ('1', '6', '50', '93', '93', '94', '95', '96', '97', '98'),
                        ('1', '6', '90', '105', '105', '106', '107', '109', '110', '110'),
                        ('1', '6', '95', '108', '109', '110', '111', '112', '113', '114'),
                        ('1', '7', '50', '94', '94', '95', '97', '98', '98', '99'),
                        ('1', '7', '90', '106', '107', '108', '109', '110', '111', '111'),
                        ('1', '7', '95', '110', '110', '111', '112', '114', '115', '116'),
                        ('1', '8', '50', '95', '96', '97', '98', '99', '99', '100'),
                        ('1', '8', '90', '107', '108', '109', '110', '111', '112', '112'),
                        ('1', '8', '95', '111', '112', '112', '114', '115', '116', '117'),
                        ('1', '9', '50', '96', '97', '98', '99', '100', '101', '101'),
                        ('1', '9', '90', '107', '108', '109', '110', '112', '113', '114'),
                        ('1', '9', '95', '112', '112', '113', '115', '116', '118', '119'),
                        ('1', '10', '50', '97', '98', '99', '100', '101', '102', '103'),
                        ('1', '10', '90', '108', '109', '111', '112', '113', '115', '116'),
                        ('1', '10', '95', '112', '113', '114', '116', '118', '120', '121'),
                        ('1', '11', '50', '99', '99', '101', '102', '103', '104', '106'),
                        ('1', '11', '90', '110', '111', '112', '114', '116', '117', '118'),
                        ('1', '11', '95', '114', '114', '116', '118', '120', '123', '124'),
                        ('1', '12', '50', '101', '101', '102', '104', '106', '108', '109'),
                        ('1', '12', '90', '113', '114', '115', '117', '119', '121', '122'),
                        ('1', '12', '95', '116', '117', '118', '121', '124', '126', '128'),
                        ('1', '13', '50', '103', '104', '105', '108', '110', '111', '112'),
                        ('1', '13', '90', '115', '116', '118', '121', '124', '126', '126'),
                        ('1', '13', '95', '119', '120', '122', '125', '128', '130', '131'),
                        ('1', '14', '50', '105', '106', '109', '111', '112', '113', '113'),
                        ('1', '14', '90', '119', '120', '123', '126', '127', '128', '129'),
                        ('1', '14', '95', '123', '125', '127', '130', '132', '133', '134'),
                        ('1', '15', '50', '108', '110', '112', '113', '114', '114', '114'),
                        ('1', '15', '90', '123', '124', '126', '128', '129', '130', '130'),
                        ('1', '15', '95', '127', '129', '131', '132', '134', '135', '135'),
                        ('1', '16', '50', '111', '112', '114', '115', '115', '116', '116'),
                        ('1', '16', '90', '126', '127', '128', '129', '131', '131', '132'),
                        ('1', '16', '95', '130', '131', '133', '134', '135', '136', '137'),
                        ('1', '17', '50', '114', '115', '116', '117', '117', '118', '118'),
                        ('1', '17', '90', '128', '129', '130', '131', '132', '133', '134'),
                        ('1', '17', '95', '132', '133', '134', '135', '137', '138', '138'),
                        ('2', '1', '50', '84', '85', '86', '86', '87', '88', '88'),
                        ('2', '1', '90', '98', '99', '99', '100', '101', '102', '102'),
                        ('2', '1', '95', '101', '102', '102', '103', '104', '105', '105'),
                        ('2', '2', '50', '87', '87', '88', '89', '90', '91', '91'),
                        ('2', '2', '90', '101', '101', '102', '103', '104', '105', '106'),
                        ('2', '2', '95', '104', '105', '106', '106', '107', '108', '109'),
                        ('2', '3', '50', '88', '89', '89', '90', '91', '92', '93'),
                        ('2', '3', '90', '102', '103', '104', '104', '105', '106', '107'),
                        ('2', '3', '95', '106', '106', '107', '108', '109', '110', '110'),
                        ('2', '4', '50', '89', '90', '91', '92', '93', '94', '94'),
                        ('2', '4', '90', '103', '104', '105', '106', '107', '108', '108'),
                        ('2', '4', '95', '107', '108', '109', '109', '110', '111', '112'),
                        ('2', '5', '50', '90', '91', '92', '93', '94', '95', '96'),
                        ('2', '5', '90', '104', '105', '106', '107', '108', '109', '110'),
                        ('2', '5', '95', '108', '109', '109', '110', '111', '112', '113'),
                        ('2', '6', '50', '92', '92', '93', '94', '96', '97', '97'),
                        ('2', '6', '90', '105', '106', '107', '108', '109', '110', '111'),
                        ('2', '6', '95', '109', '109', '110', '111', '112', '113', '114'),
                        ('2', '7', '50', '92', '93', '94', '95', '97', '98', '99'),
                        ('2', '7', '90', '106', '106', '107', '109', '110', '111', '112'),
                        ('2', '7', '95', '109', '110', '111', '112', '113', '114', '115'),
                        ('2', '8', '50', '93', '94', '95', '97', '98', '99', '100'),
                        ('2', '8', '90', '107', '107', '108', '110', '111', '112', '113'),
                        ('2', '8', '95', '110', '111', '112', '113', '115', '116', '117'),
                        ('2', '9', '50', '95', '95', '97', '98', '99', '100', '101'),
                        ('2', '9', '90', '108', '108', '109', '111', '112', '113', '114'),
                        ('2', '9', '95', '112', '112', '113', '114', '116', '117', '118'),
                        ('2', '10', '50', '96', '97', '98', '99', '101', '102', '103'),
                        ('2', '10', '90', '109', '110', '111', '112', '113', '115', '116'),
                        ('2', '10', '95', '113', '114', '114', '116', '117', '119', '120'),
                        ('2', '11', '50', '98', '99', '101', '102', '104', '105', '106'),
                        ('2', '11', '90', '111', '112', '113', '114', '116', '118', '120'),
                        ('2', '11', '95', '115', '116', '117', '118', '120', '123', '124'),
                        ('2', '12', '50', '102', '102', '104', '105', '107', '108', '108'),
                        ('2', '12', '90', '114', '115', '116', '118', '120', '122', '122'),
                        ('2', '12', '95', '118', '119', '120', '122', '124', '125', '126'),
                        ('2', '13', '50', '104', '105', '106', '107', '108', '108', '109'),
                        ('2', '13', '90', '116', '117', '119', '121', '122', '123', '123'),
                        ('2', '13', '95', '121', '122', '123', '124', '126', '126', '127'),
                        ('2', '14', '50', '105', '106', '107', '108', '109', '109', '109'),
                        ('2', '14', '90', '118', '118', '120', '122', '123', '123', '123'),
                        ('2', '14', '95', '123', '123', '124', '125', '126', '127', '127'),
                        ('2', '15', '50', '105', '106', '107', '108', '109', '109', '109'),
                        ('2', '15', '90', '118', '119', '121', '122', '123', '123', '124'),
                        ('2', '15', '95', '124', '124', '125', '126', '127', '127', '128'),
                        ('2', '16', '50', '106', '107', '108', '109', '109', '110', '110'),
                        ('2', '16', '90', '119', '120', '122', '123', '124', '124', '124'),
                        ('2', '16', '95', '124', '125', '125', '127', '127', '128', '128'),
                        ('2', '17', '50', '107', '108', '109', '110', '110', '110', '111'),
                        ('2', '17', '90', '120', '121', '123', '124', '124', '125', '125'),
                        ('2', '17', '95', '125', '125', '126', '127', '128', '128', '128')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE blood_pressure_systolic_height_percentile');
    }
}

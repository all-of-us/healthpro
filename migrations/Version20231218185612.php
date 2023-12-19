<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231218185612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update z-score table add -ve 0 row and update 0 row with new values';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DELETE FROM z_scores WHERE Z = 0 and Z_01 = 0.49601');
        $this->addSql("INSERT INTO z_scores (Z, Z_0, Z_01, Z_02, Z_03, Z_04, Z_05, Z_06, Z_07, Z_08, Z_09) VALUES
                        ('-0', '0.5', '0.49601', '0.49202', '0.48803', '0.48405', '0.48006', '0.47608', '0.4721', '0.46812', '0.46414'),
                        ('0', '0.5', '0.50399', '0.50798', '0.51197', '0.51595', '0.51994', '0.52392', '0.5279', '0.53188', '0.53586')");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DELETE FROM z_scores WHERE Z = -0 and Z_01 = 0.49601');
        $this->addSql('DELETE FROM z_scores WHERE Z = 0 and Z_01 = 0.50399');
        $this->addSql("INSERT INTO z_scores (Z, Z_0, Z_01, Z_02, Z_03, Z_04, Z_05, Z_06, Z_07, Z_08, Z_09) VALUES
                        ('0', '0.5', '0.49601', '0.49202', '0.48803', '0.48405', '0.48006', '0.47608', '0.4721', '0.46812', '0.46414')");
    }
}

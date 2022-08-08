<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220808153750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates FeatureNotificationUser entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE feature_notification_user_map (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, feature_notification_id INT NOT NULL, created_ts DATETIME NOT NULL, INDEX IDX_E13DA014A76ED395 (user_id), INDEX IDX_E13DA0149F08077C (feature_notification_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DROP TABLE feature_notification_user_map');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user time zone audit log entity';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE user_timezone_audit_log (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, previous_timezone VARCHAR(255) DEFAULT NULL, current_timezone VARCHAR(255) NOT NULL, client_timezone VARCHAR(255) DEFAULT NULL, modified_ts DATETIME NOT NULL, INDEX idx_user_timezone_audit_log_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_timezone_audit_log ADD CONSTRAINT fk_user_timezone_audit_log_user FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user_timezone_audit_log DROP FOREIGN KEY fk_user_timezone_audit_log_user');
        $this->addSql('DROP TABLE user_timezone_audit_log');
    }
}

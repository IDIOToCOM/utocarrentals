<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FCM device tokens for SAMSON push notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE device_token (id INT AUTO_INCREMENT NOT NULL, login_id INT NOT NULL, fcm_token VARCHAR(512) NOT NULL, platform VARCHAR(16) NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_fcm_token (fcm_token), INDEX idx_device_token_login (login_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE device_token ADD CONSTRAINT FK_device_token_login FOREIGN KEY (login_id) REFERENCES login (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_token DROP FOREIGN KEY FK_device_token_login');
        $this->addSql('DROP TABLE device_token');
    }
}

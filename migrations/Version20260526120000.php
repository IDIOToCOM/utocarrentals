<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'In-app user notifications for bookings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_notification (id INT AUTO_INCREMENT NOT NULL, recipient_id INT NOT NULL, booking_id INT DEFAULT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, link_route VARCHAR(128) DEFAULT NULL, link_params JSON DEFAULT NULL, read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_NOTIF_RECIPIENT_READ (recipient_id, read_at), INDEX IDX_NOTIF_BOOKING_TYPE (booking_id, type), INDEX IDX_NOTIF_CREATED (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_notification ADD CONSTRAINT FK_NOTIF_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES login (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_notification ADD CONSTRAINT FK_NOTIF_BOOKING FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_notification DROP FOREIGN KEY FK_NOTIF_RECIPIENT');
        $this->addSql('ALTER TABLE app_notification DROP FOREIGN KEY FK_NOTIF_BOOKING');
        $this->addSql('DROP TABLE app_notification');
    }
}

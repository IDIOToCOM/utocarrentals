<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link payments to bookings; store amount due, amount paid, and paid timestamp';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD booking_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD amount_due INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE payment ADD amount_paid INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD paid_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6D28840D3301C60 ON payment (booking_id)');
        $this->addSql("UPDATE payment p INNER JOIN booking b ON p.name LIKE CONCAT('Payment for Booking #', b.id, '%') SET p.booking_id = b.id WHERE p.booking_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D3301C60');
        $this->addSql('DROP INDEX IDX_6D28840D3301C60 ON payment');
        $this->addSql('ALTER TABLE payment DROP booking_id, DROP amount_due, DROP amount_paid, DROP paid_at');
    }
}

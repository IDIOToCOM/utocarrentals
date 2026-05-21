<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add description, transmission, and passenger_seats to car_inventory';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car_inventory ADD description LONGTEXT DEFAULT NULL, ADD transmission VARCHAR(20) DEFAULT NULL, ADD passenger_seats INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car_inventory DROP description, DROP transmission, DROP passenger_seats');
    }
}

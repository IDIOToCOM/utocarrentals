<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map legacy Rented car status to Available; fleet uses Available / Out of service';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE car_inventory SET Status = 'Available' WHERE Status = 'Rented' OR LOWER(Status) = 'rented'");
    }

    public function down(Schema $schema): void
    {
        // Cannot restore previous Rented values
    }
}

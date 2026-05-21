<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog trust fields: rent highlights, manual rating and review count per vehicle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car_inventory ADD rent_highlights LONGTEXT DEFAULT NULL, ADD average_rating NUMERIC(2, 1) DEFAULT NULL, ADD review_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car_inventory DROP rent_highlights, DROP average_rating, DROP review_count');
    }
}

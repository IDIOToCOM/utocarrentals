<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to booking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking ADD status VARCHAR(20) NOT NULL DEFAULT 'Pending'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP status');
    }
}

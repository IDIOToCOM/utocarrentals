<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer profile fields and password reset token to login table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login ADD display_name VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(32) DEFAULT NULL, ADD reset_token VARCHAR(64) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_LOGIN_RESET_TOKEN ON login (reset_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_LOGIN_RESET_TOKEN ON login');
        $this->addSql('ALTER TABLE login DROP display_name, DROP phone, DROP reset_token, DROP reset_token_expires_at');
    }
}

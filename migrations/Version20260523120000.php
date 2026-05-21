<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer saved vehicles (favorites) while browsing catalog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE car_favorite (id INT AUTO_INCREMENT NOT NULL, login_id INT NOT NULL, car_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CAR_FAVORITE_LOGIN (login_id), INDEX IDX_CAR_FAVORITE_CAR (car_id), UNIQUE INDEX UNIQ_FAVORITE_USER_CAR (login_id, car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE car_favorite ADD CONSTRAINT FK_CAR_FAVORITE_LOGIN FOREIGN KEY (login_id) REFERENCES login (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE car_favorite ADD CONSTRAINT FK_CAR_FAVORITE_CAR FOREIGN KEY (car_id) REFERENCES car_inventory (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car_favorite DROP FOREIGN KEY FK_CAR_FAVORITE_LOGIN');
        $this->addSql('ALTER TABLE car_favorite DROP FOREIGN KEY FK_CAR_FAVORITE_CAR');
        $this->addSql('DROP TABLE car_favorite');
    }
}

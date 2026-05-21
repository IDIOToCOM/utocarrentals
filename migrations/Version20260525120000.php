<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Customer car reviews; remove admin manual catalog trust fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE car_review (id INT AUTO_INCREMENT NOT NULL, login_id INT NOT NULL, car_id INT NOT NULL, rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CAR_REVIEW_LOGIN (login_id), INDEX IDX_CAR_REVIEW_CAR (car_id), UNIQUE INDEX UNIQ_REVIEW_USER_CAR (login_id, car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE car_review ADD CONSTRAINT FK_CAR_REVIEW_LOGIN FOREIGN KEY (login_id) REFERENCES login (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE car_review ADD CONSTRAINT FK_CAR_REVIEW_CAR FOREIGN KEY (car_id) REFERENCES car_inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE car_inventory DROP rent_highlights, DROP average_rating, DROP review_count');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car_inventory ADD rent_highlights LONGTEXT DEFAULT NULL, ADD average_rating NUMERIC(2, 1) DEFAULT NULL, ADD review_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE car_review DROP FOREIGN KEY FK_CAR_REVIEW_LOGIN');
        $this->addSql('ALTER TABLE car_review DROP FOREIGN KEY FK_CAR_REVIEW_CAR');
        $this->addSql('DROP TABLE car_review');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261119131120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pickup_schedule (id INT AUTO_INCREMENT NOT NULL, pickup_date DATE NOT NULL, pickup_time TIME NOT NULL, status VARCHAR(30) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, customer_id INT NOT NULL, laundry_transaction_id INT DEFAULT NULL, INDEX IDX_F230ECA89395C3F3 (customer_id), INDEX IDX_F230ECA892CC309C (laundry_transaction_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE pickup_schedule ADD CONSTRAINT FK_F230ECA89395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE pickup_schedule ADD CONSTRAINT FK_F230ECA892CC309C FOREIGN KEY (laundry_transaction_id) REFERENCES laundry_transaction (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pickup_schedule DROP FOREIGN KEY FK_F230ECA89395C3F3');
        $this->addSql('ALTER TABLE pickup_schedule DROP FOREIGN KEY FK_F230ECA892CC309C');
        $this->addSql('DROP TABLE pickup_schedule');
    }
}

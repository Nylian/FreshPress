<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117142841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, payment_number VARCHAR(30) NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_method VARCHAR(50) NOT NULL, payment_status VARCHAR(30) NOT NULL, payment_date DATETIME NOT NULL, reference_number VARCHAR(100) DEFAULT NULL, laundry_transaction_id INT NOT NULL, INDEX IDX_6D28840D92CC309C (laundry_transaction_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D92CC309C FOREIGN KEY (laundry_transaction_id) REFERENCES laundry_transaction (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D92CC309C');
        $this->addSql('DROP TABLE payment');
    }
}

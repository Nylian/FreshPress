<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117131403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE laundry_transaction (
        id INT AUTO_INCREMENT NOT NULL,
        transaction_number VARCHAR(30) NOT NULL,
        service_type VARCHAR(100) NOT NULL,
        weight NUMERIC(8, 2) NOT NULL,
        price_per_kg NUMERIC(10, 2) NOT NULL,
        total_amount NUMERIC(10, 2) NOT NULL,
        status VARCHAR(30) NOT NULL,
        date_received DATETIME NOT NULL,
        expected_pickup_date DATE NOT NULL,
        customer_id INT NOT NULL,
        UNIQUE INDEX UNIQ_TRANSACTION_NUMBER (transaction_number),
        INDEX IDX_797034129395C3F3 (customer_id),
        PRIMARY KEY (id)
    ) DEFAULT CHARACTER SET utf8mb4');

    $this->addSql('ALTER TABLE laundry_transaction ADD CONSTRAINT FK_797034129395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
}

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE laundry_transaction DROP FOREIGN KEY FK_797034129395C3F3');
        $this->addSql('DROP TABLE laundry_transaction');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220408134819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscriber (id INT AUTO_INCREMENT NOT NULL, follow_id INT NOT NULL, follower_id INT NOT NULL, INDEX IDX_AD005B698711D3BC (follow_id), INDEX IDX_AD005B69AC24F853 (follower_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subscriber ADD CONSTRAINT FK_AD005B698711D3BC FOREIGN KEY (follow_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE subscriber ADD CONSTRAINT FK_AD005B69AC24F853 FOREIGN KEY (follower_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD follow_number INT NOT NULL, ADD follower_number INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE subscriber');
        $this->addSql('ALTER TABLE user DROP follow_number, DROP follower_number');
    }
}

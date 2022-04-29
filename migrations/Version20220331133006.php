<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220331133006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE archiver (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, configuration LONGTEXT NOT NULL, enabled TINYINT(1) NOT NULL, last_run_at DATETIME DEFAULT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exception_log_entry (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', hidden TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE get_organized_case (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', archiver_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', case_id VARCHAR(255) NOT NULL, data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', share_file_item_id VARCHAR(255) NOT NULL, share_file_item_stream_id VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_5118EECDA430C03C (archiver_id), INDEX casefile_idx (case_id, share_file_item_id, archiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE get_organized_document (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', archiver_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', doc_id VARCHAR(255) NOT NULL, data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', share_file_item_id VARCHAR(255) NOT NULL, share_file_item_stream_id VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_8AE2DF12A430C03C (archiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE get_organized_case ADD CONSTRAINT FK_5118EECDA430C03C FOREIGN KEY (archiver_id) REFERENCES archiver (id)');
        $this->addSql('ALTER TABLE get_organized_document ADD CONSTRAINT FK_8AE2DF12A430C03C FOREIGN KEY (archiver_id) REFERENCES archiver (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE get_organized_case DROP FOREIGN KEY FK_5118EECDA430C03C');
        $this->addSql('ALTER TABLE get_organized_document DROP FOREIGN KEY FK_8AE2DF12A430C03C');
        $this->addSql('DROP TABLE archiver');
        $this->addSql('DROP TABLE exception_log_entry');
        $this->addSql('DROP TABLE get_organized_case');
        $this->addSql('DROP TABLE get_organized_document');
    }
}

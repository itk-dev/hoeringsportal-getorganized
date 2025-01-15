<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250115204318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archiver CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE exception_log_entry CHANGE data data JSON NOT NULL');

        // Convert PHP serialized data to JSON.
        $this->addSql('ALTER TABLE ext_log_entries ADD data_json JSON DEFAULT NULL');
        $result = $this->connection->executeQuery('SELECT id, data FROM ext_log_entries');
        while ($row = $result->fetchAssociative()) {
            $this->addSql(
                'UPDATE ext_log_entries SET data_json = :data WHERE id = :id',
                ['id' => $row['id'], 'data' => json_encode(unserialize($row['data']))],
                ['id' => Types::INTEGER, 'data' => Types::JSON]
            );
        }
        $this->addSql('ALTER TABLE ext_log_entries DROP data, CHANGE data_json data JSON DEFAULT NULL');

        $this->addSql('ALTER TABLE get_organized_document CHANGE id id BINARY(16) NOT NULL, CHANGE archiver_id archiver_id BINARY(16) NOT NULL, CHANGE data data JSON NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id BINARY(16) NOT NULL, CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE get_organized_document CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE data data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', CHANGE archiver_id archiver_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE roles roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');

        // Convert JSON to PHP serialized data.
        $this->addSql('ALTER TABLE ext_log_entries ADD data_serialized LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
        $result = $this->connection->executeQuery('SELECT id, data FROM ext_log_entries');
        while ($row = $result->fetchAssociative()) {
            $this->addSql(
                'UPDATE ext_log_entries SET data_serialized = :data WHERE id = :id',
                ['id' => $row['id'], 'data' => serialize(json_decode($row['data'], true))],
                ['id' => Types::INTEGER, 'data' => Types::STRING]
            );
        }
        $this->addSql('ALTER TABLE ext_log_entries DROP data, CHANGE data_serialized data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');

        $this->addSql('ALTER TABLE exception_log_entry CHANGE data data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE archiver CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}

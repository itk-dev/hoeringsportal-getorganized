<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250115224307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX exception_log_entry_id ON exception_log_entry (id)');
        $this->addSql('CREATE INDEX exception_log_entry_created_at ON exception_log_entry (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX exception_log_entry_id ON exception_log_entry');
        $this->addSql('DROP INDEX exception_log_entry_created_at ON exception_log_entry');
    }
}

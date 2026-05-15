<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515074421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE employee (id UUID NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE ranking (id UUID NOT NULL, score SMALLINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, employee_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_80B839D0A76ED395 ON ranking (user_id)');
        $this->addSql('CREATE INDEX IDX_80B839D08C03F15C ON ranking (employee_id)');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, account_number VARCHAR(12) NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649B1A4D127 ON "user" (account_number)');
        $this->addSql('ALTER TABLE ranking ADD CONSTRAINT FK_80B839D0A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ranking ADD CONSTRAINT FK_80B839D08C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ranking DROP CONSTRAINT FK_80B839D0A76ED395');
        $this->addSql('ALTER TABLE ranking DROP CONSTRAINT FK_80B839D08C03F15C');
        $this->addSql('DROP TABLE employee');
        $this->addSql('DROP TABLE ranking');
        $this->addSql('DROP TABLE "user"');
    }
}

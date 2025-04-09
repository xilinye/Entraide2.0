<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250409155541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE conversation_deletion (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, other_user_id INT NOT NULL, deleted_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_CD936635A76ED395 (user_id), INDEX IDX_CD936635B4334DF9 (other_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conversation_deletion ADD CONSTRAINT FK_CD936635A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conversation_deletion ADD CONSTRAINT FK_CD936635B4334DF9 FOREIGN KEY (other_user_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE conversation_deletion DROP FOREIGN KEY FK_CD936635A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conversation_deletion DROP FOREIGN KEY FK_CD936635B4334DF9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE conversation_deletion
        SQL);
    }
}

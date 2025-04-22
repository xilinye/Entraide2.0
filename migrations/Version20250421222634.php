<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250421222634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, rater_id INT NOT NULL, rated_user_id INT NOT NULL, blog_post_id INT DEFAULT NULL, event_id INT DEFAULT NULL, forum_response_id INT DEFAULT NULL, score INT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_D88926223FC1CD0A (rater_id), INDEX IDX_D8892622A8957C46 (rated_user_id), INDEX IDX_D8892622A77FBEAF (blog_post_id), INDEX IDX_D889262271F7E88B (event_id), INDEX IDX_D8892622FDC7ABE2 (forum_response_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D88926223FC1CD0A FOREIGN KEY (rater_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D8892622A8957C46 FOREIGN KEY (rated_user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D8892622A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D889262271F7E88B FOREIGN KEY (event_id) REFERENCES event (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D8892622FDC7ABE2 FOREIGN KEY (forum_response_id) REFERENCES forum_response (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D88926223FC1CD0A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D8892622A8957C46
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D8892622A77FBEAF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D889262271F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D8892622FDC7ABE2
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE rating
        SQL);
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250611082710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE achievement (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(50) NOT NULL, description TEXT NOT NULL, icon VARCHAR(100) DEFAULT NULL, points INT NOT NULL, criteria JSON NOT NULL, level VARCHAR(20) NOT NULL, category_code VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_secret BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_96737FF177153098 ON achievement (code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE category (id SERIAL NOT NULL, name VARCHAR(50) NOT NULL, code VARCHAR(50) NOT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, description TEXT DEFAULT NULL, is_active BOOLEAN NOT NULL, display_order INT NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_64C19C177153098 ON category (code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE goal (id SERIAL NOT NULL, category_id INT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, frequency_type VARCHAR(20) NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(10) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCDCEB2E12469DE2 ON goal (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FCDCEB2EA76ED395 ON goal (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE media (id SERIAL NOT NULL, real_name VARCHAR(255) NOT NULL, real_path VARCHAR(255) NOT NULL, public_path VARCHAR(255) NOT NULL, mime VARCHAR(255) NOT NULL, status VARCHAR(25) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE metric (id SERIAL NOT NULL, goal_id INT NOT NULL, name VARCHAR(255) NOT NULL, unit VARCHAR(50) NOT NULL, evolution_type VARCHAR(20) NOT NULL, initial_value DOUBLE PRECISION NOT NULL, target_value DOUBLE PRECISION NOT NULL, is_primary BOOLEAN NOT NULL, color VARCHAR(7) DEFAULT NULL, display_order INT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_87D62EE3667D1AFE ON metric (goal_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pool (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(10) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE progress (id SERIAL NOT NULL, goal_id INT NOT NULL, metric_id INT NOT NULL, session_id INT DEFAULT NULL, value DOUBLE PRECISION NOT NULL, date DATE NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, metadata JSON DEFAULT NULL, difficulty_rating INT DEFAULT NULL, satisfaction_rating INT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2201F246667D1AFE ON progress (goal_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2201F246A952D583 ON progress (metric_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2201F246613FECDF ON progress (session_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_progress_date ON progress (date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_progress_goal_date ON progress (goal_id, date)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (id SERIAL NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens (refresh_token)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE session (id SERIAL NOT NULL, goal_id INT NOT NULL, start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, duration INT DEFAULT NULL, completed BOOLEAN NOT NULL, notes TEXT DEFAULT NULL, session_data JSON DEFAULT NULL, intensity_rating INT DEFAULT NULL, satisfaction_rating INT DEFAULT NULL, difficulty_rating INT DEFAULT NULL, location VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_session_start_time ON session (start_time)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_session_goal ON session (goal_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE song (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, artiste VARCHAR(55) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(10) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE song_pool (song_id INT NOT NULL, pool_id INT NOT NULL, PRIMARY KEY(song_id, pool_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC027F73A0BDB2F3 ON song_pool (song_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DC027F737B3406DF ON song_pool (pool_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (id SERIAL NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, level INT NOT NULL, total_points INT NOT NULL, current_streak INT NOT NULL, longest_streak INT NOT NULL, last_activity_date DATE DEFAULT NULL, unit_system VARCHAR(10) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, preferences JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(10) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "user" (username)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_achievement (id SERIAL NOT NULL, user_id INT NOT NULL, achievement_id INT NOT NULL, unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, unlock_data JSON DEFAULT NULL, is_notified BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3F68B664B3EC99FE ON user_achievement (achievement_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_user_achievement_user ON user_achievement (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_user_achievement_unlocked_at ON user_achievement (unlocked_at)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE goal ADD CONSTRAINT FK_FCDCEB2E12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE goal ADD CONSTRAINT FK_FCDCEB2EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE metric ADD CONSTRAINT FK_87D62EE3667D1AFE FOREIGN KEY (goal_id) REFERENCES goal (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress ADD CONSTRAINT FK_2201F246667D1AFE FOREIGN KEY (goal_id) REFERENCES goal (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress ADD CONSTRAINT FK_2201F246A952D583 FOREIGN KEY (metric_id) REFERENCES metric (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress ADD CONSTRAINT FK_2201F246613FECDF FOREIGN KEY (session_id) REFERENCES session (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session ADD CONSTRAINT FK_D044D5D4667D1AFE FOREIGN KEY (goal_id) REFERENCES goal (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE song_pool ADD CONSTRAINT FK_DC027F73A0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE song_pool ADD CONSTRAINT FK_DC027F737B3406DF FOREIGN KEY (pool_id) REFERENCES pool (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_achievement ADD CONSTRAINT FK_3F68B664A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_achievement ADD CONSTRAINT FK_3F68B664B3EC99FE FOREIGN KEY (achievement_id) REFERENCES achievement (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE goal DROP CONSTRAINT FK_FCDCEB2E12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE goal DROP CONSTRAINT FK_FCDCEB2EA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE metric DROP CONSTRAINT FK_87D62EE3667D1AFE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress DROP CONSTRAINT FK_2201F246667D1AFE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress DROP CONSTRAINT FK_2201F246A952D583
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE progress DROP CONSTRAINT FK_2201F246613FECDF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE session DROP CONSTRAINT FK_D044D5D4667D1AFE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE song_pool DROP CONSTRAINT FK_DC027F73A0BDB2F3
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE song_pool DROP CONSTRAINT FK_DC027F737B3406DF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_achievement DROP CONSTRAINT FK_3F68B664A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_achievement DROP CONSTRAINT FK_3F68B664B3EC99FE
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE achievement
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE goal
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE media
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE metric
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE pool
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE progress
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE refresh_tokens
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE session
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE song
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE song_pool
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "user"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_achievement
        SQL);
    }
}

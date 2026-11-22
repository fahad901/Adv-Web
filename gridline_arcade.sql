-- =====================================================================
-- Gridline Arcade - Project Part 3 database (MySQL / MariaDB)
-- 10 tables. Every table has the three required audit columns:
--   id          unique ID (AUTO_INCREMENT primary key)
--   created_at  record date/time (set automatically by MySQL)
--   created_by  who created the record (username, 'guest' or 'system')
-- Safe to re-import: tables use IF NOT EXISTS and seed rows are skipped
-- when they already exist.
-- =====================================================================
CREATE DATABASE IF NOT EXISTS gridline_arcade
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE gridline_arcade;

-- 1. users (common table) ---------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
    bio           VARCHAR(500) NOT NULL DEFAULT '',
    last_login_at DATETIME NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    VARCHAR(50) NOT NULL DEFAULT 'system'
) ENGINE=InnoDB;

-- 2. admin_settings (common table) ------------------------------------
CREATE TABLE IF NOT EXISTS admin_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(60)  NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL DEFAULT '',
    description   VARCHAR(255) NOT NULL DEFAULT '',
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    VARCHAR(50) NOT NULL DEFAULT 'system'
) ENGINE=InnoDB;

-- 3. games (game catalogue shown on Home / Game library) ---------------
CREATE TABLE IF NOT EXISTS games (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(20)  NOT NULL UNIQUE,
    title       VARCHAR(80)  NOT NULL,
    category    VARCHAR(40)  NOT NULL,
    symbol      VARCHAR(8)   NOT NULL,
    theme       VARCHAR(30)  NOT NULL,
    page_url    VARCHAR(60)  NOT NULL,
    description VARCHAR(255) NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  VARCHAR(50) NOT NULL DEFAULT 'system'
) ENGINE=InnoDB;

-- 4. scores (one row per finished game) --------------------------------
CREATE TABLE IF NOT EXISTS scores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    game_id    INT NOT NULL,
    score      INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(50) NOT NULL DEFAULT 'system',
    CONSTRAINT fk_scores_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scores_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    INDEX idx_scores_user (user_id),
    INDEX idx_scores_game (game_id)
) ENGINE=InnoDB;

-- 5. question_categories -----------------------------------------------
CREATE TABLE IF NOT EXISTS question_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60)  NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  VARCHAR(50) NOT NULL DEFAULT 'system'
) ENGINE=InnoDB;

-- 6. questions (quiz bank) ---------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_id   INT NULL,
    question      VARCHAR(500) NOT NULL,
    option_a      VARCHAR(255) NOT NULL,
    option_b      VARCHAR(255) NOT NULL,
    option_c      VARCHAR(255) NOT NULL,
    option_d      VARCHAR(255) NOT NULL,
    correct_index TINYINT NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    VARCHAR(50) NOT NULL DEFAULT 'system',
    CONSTRAINT fk_questions_category FOREIGN KEY (category_id)
        REFERENCES question_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. password_resets ---------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(50) NOT NULL DEFAULT 'system',
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. contact_messages (About & contact form) ----------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    name       VARCHAR(80)  NOT NULL,
    email      VARCHAR(190) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('new','read') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(50) NOT NULL DEFAULT 'guest',
    CONSTRAINT fk_contact_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. login_logs (session / security audit trail) ------------------------
CREATE TABLE IF NOT EXISTS login_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,
    email_attempted VARCHAR(190) NOT NULL DEFAULT '',
    event           ENUM('register','login_success','login_failed','logout','session_timeout') NOT NULL,
    ip_address      VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent      VARCHAR(255) NOT NULL DEFAULT '',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      VARCHAR(50) NOT NULL DEFAULT 'guest',
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_email_event (email_attempted, event)
) ENGINE=InnoDB;

-- 10. favorites (a player's starred games) ------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    game_id    INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(50) NOT NULL DEFAULT 'system',
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY uq_fav (user_id, game_id)
) ENGINE=InnoDB;

-- =====================================================================
-- Seed data
-- =====================================================================

-- Admin settings (editable from admin.php -> Settings)
INSERT INTO admin_settings(setting_key, setting_value, description, created_by) VALUES
 ('site_name',              'Gridline Arcade', 'Name shown in the page title', 'system'),
 ('welcome_banner',         'Welcome to Gridline Arcade. Log in to save your scores.', 'Banner on the home page (leave empty to hide)', 'system'),
 ('session_timeout_minutes','30',  'Log a user out after this many idle minutes', 'system'),
 ('registration_open',      '1',   '1 = new accounts allowed, 0 = registration closed', 'system'),
 ('quiz_question_count',    '5',   'Questions per quiz round', 'system'),
 ('max_login_attempts',     '5',   'Failed logins allowed before a temporary lockout', 'system'),
 ('lockout_minutes',        '10',  'Length of the lockout window in minutes', 'system'),
 ('leaderboard_size',       '20',  'Rows shown on the leaderboard', 'system')
ON DUPLICATE KEY UPDATE id = id;

-- Games catalogue (slug values are the ones games.js sends to save-score.php)
INSERT INTO games(slug, title, category, symbol, theme, page_url, description, sort_order, created_by) VALUES
 ('tic',    'Tic-Tac-Toe',   'Strategy', '×', 'tic-tac-toe',  'tictactoe.php', 'Play a local two-player match on a three-by-three board.', 1, 'system'),
 ('puzzle', 'Slide Puzzle',  'Logic',    '▦', 'slide-puzzle', 'puzzle.php',    'Slide the tiles into order in as few moves as possible.',  2, 'system'),
 ('quiz',   'Quiz Challenge','Trivia',   '?', 'quiz',         'quiz.php',      'Answer a round of questions and see how you score.',       3, 'system')
ON DUPLICATE KEY UPDATE id = id;

-- Quiz categories
INSERT INTO question_categories(name, description, created_by) VALUES
 ('Web Development', 'HTML, CSS, JavaScript, PHP and HTTP', 'system'),
 ('Databases',       'SQL and MySQL basics',                'system'),
 ('Math',            'Quick mental arithmetic',             'system')
ON DUPLICATE KEY UPDATE id = id;

-- Quiz questions (skipped if a question with the same text already exists)
INSERT INTO questions(category_id, question, option_a, option_b, option_c, option_d, correct_index, created_by)
SELECT c.id, seed.question, seed.a, seed.b, seed.c, seed.d, seed.correct, 'system'
FROM (
    SELECT 'Web Development' AS cat, 'Which language runs in a browser?' AS question,
           'PHP' AS a, 'JavaScript' AS b, 'MySQL' AS c, 'Python' AS d, 1 AS correct
    UNION ALL SELECT 'Web Development', 'What does HTML stand for?',
           'HyperText Markup Language', 'High Tech Modern Language', 'Home Tool Markup Language', 'Hyper Transfer Main Link', 0
    UNION ALL SELECT 'Web Development', 'Which CSS property changes text color?',
           'background', 'font-style', 'padding', 'color', 3
    UNION ALL SELECT 'Web Development', 'Which HTTP method should a login form normally use?',
           'GET', 'POST', 'TRACE', 'HEAD', 1
    UNION ALL SELECT 'Web Development', 'Which PHP superglobal holds session data?',
           '$_GET', '$_POST', '$_SESSION', '$_SERVER', 2
    UNION ALL SELECT 'Databases', 'Which database is used in this project?',
           'SQLite', 'MongoDB', 'MySQL', 'Oracle', 2
    UNION ALL SELECT 'Databases', 'Which SQL keyword retrieves rows from a table?',
           'INSERT', 'SELECT', 'DROP', 'ALTER', 1
    UNION ALL SELECT 'Math', 'What is 5 + 7?',
           '10', '12', '13', '15', 1
    UNION ALL SELECT 'Math', 'What is 9 x 6?',
           '45', '52', '54', '56', 2
) AS seed
JOIN question_categories c ON c.name = seed.cat
WHERE NOT EXISTS (SELECT 1 FROM questions q WHERE q.question = seed.question);

-- Demo accounts (bcrypt hashes)
--   Player: player@gridline.test / GridlineDemo!2026
--   Admin : admin@gridline.test  / GridlineAdmin!2026
INSERT INTO users(username, email, password_hash, role, created_by) VALUES
 ('GridlineDemo',  'player@gridline.test', '$2y$10$BffYPVjrtTj5qCimsuKm.uFCRazAi.U5pHeVrUbWtREc2BRkpkjqy', 'user',  'system'),
 ('GridlineAdmin', 'admin@gridline.test',  '$2y$12$1/oKj.3ZItU0AStFh3jNcuXyBfrfI2BrRjfaa7yLcR658IiVH0jse', 'admin', 'system')
ON DUPLICATE KEY UPDATE id = id;

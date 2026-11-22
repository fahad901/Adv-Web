CREATE DATABASE IF NOT EXISTS neoarcade
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE neoarcade;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    bio VARCHAR(500) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game ENUM('tic','puzzle','quiz') NOT NULL,
    score INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_index TINYINT NOT NULL
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- Reimporting preserves existing questions, accounts, passwords, and scores.
INSERT INTO questions(question,option_a,option_b,option_c,option_d,correct_index)
SELECT seed.question, seed.option_a, seed.option_b, seed.option_c, seed.option_d, seed.correct_index
FROM (
    SELECT
        'Which language runs in a browser?' AS question,
        'PHP' AS option_a,
        'JavaScript' AS option_b,
        'MySQL' AS option_c,
        'Python' AS option_d,
        1 AS correct_index
    UNION ALL SELECT 'What does HTML stand for?',
        'HyperText Markup Language', 'High Tech Modern Language',
        'Home Tool Markup Language', 'Hyper Transfer Main Link', 0
    UNION ALL SELECT 'Which database is used in this project?','SQLite','MongoDB','MySQL','Oracle',2
    UNION ALL SELECT 'Which CSS property changes text color?','background','font-style','padding','color',3
    UNION ALL SELECT 'What is 5 + 7?','10','12','13','15',1
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM questions AS existing WHERE existing.question = seed.question
);

-- The classroom demo login is a regular player with a bcrypt password hash.
INSERT INTO users(username,email,password_hash)
VALUES ('GridlineDemo','player@gridline.test','$2y$10$BffYPVjrtTj5qCimsuKm.uFCRazAi.U5pHeVrUbWtREc2BRkpkjqy')
ON DUPLICATE KEY UPDATE id = id;

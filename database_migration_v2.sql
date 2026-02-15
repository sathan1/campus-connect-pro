-- Campus Connect Pro - Event Management System v2
-- Run this migration in phpMyAdmin

-- 1. Event Types Table
CREATE TABLE IF NOT EXISTS event_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT '📅'
);

INSERT IGNORE INTO event_types (id, name, icon) VALUES 
(1, 'quiz', '📝'),
(2, 'photography', '📷'),
(3, 'presentation', '📊');

-- 2. Modify events table - Add new columns
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS event_type_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS description TEXT,
ADD COLUMN IF NOT EXISTS photo_limit INT DEFAULT 5,
ADD COLUMN IF NOT EXISTS ppt_size_limit INT DEFAULT 2,
ADD COLUMN IF NOT EXISTS registration_limit INT DEFAULT 100,
ADD COLUMN IF NOT EXISTS last_submission_date DATE,
ADD COLUMN IF NOT EXISTS result_date DATE,
ADD COLUMN IF NOT EXISTS status ENUM('draft', 'open', 'ongoing', 'finished') DEFAULT 'open';

-- 3. Photo Submissions Table
CREATE TABLE IF NOT EXISTS photo_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. PPT Submissions Table
CREATE TABLE IF NOT EXISTS ppt_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    ppt_path VARCHAR(255) NOT NULL,
    presentation_order INT DEFAULT 0,
    status ENUM('pending', 'called', 'presenting', 'completed', 'not_attended', 'skipped') DEFAULT 'pending',
    marks INT DEFAULT 0,
    notes TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Event Results Table
CREATE TABLE IF NOT EXISTS event_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    position INT NOT NULL,
    points INT DEFAULT 0,
    prize_description VARCHAR(255),
    announced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_position (event_id, position)
);

-- 6. Update existing events to quiz type
UPDATE events SET event_type_id = 1 WHERE event_type_id IS NULL OR event_type_id = 0;

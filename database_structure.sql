-- ============================================
-- Campus Connect Pro — Database Schema
-- Interactive Quiz & Events Platform
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- Create Database
-- ============================================
CREATE DATABASE IF NOT EXISTS `campus_connect`;
USE `campus_connect`;

-- ============================================
-- Table: users
-- ============================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `department` varchar(100) DEFAULT NULL,
  `year_of_study` varchar(20) DEFAULT NULL,
  `college` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: departments
-- ============================================
CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- Table: department_suggestions
-- ============================================
CREATE TABLE `department_suggestions` (
  `id` int(11) NOT NULL,
  `suggested_name` varchar(100) NOT NULL,
  `suggested_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- Table: study_years
-- ============================================
CREATE TABLE `study_years` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- Table: event_types
-- ============================================
CREATE TABLE `event_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: events
-- ============================================
CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `question_limit` int(11) DEFAULT 10,
  `event_type_id` int(11) DEFAULT 1,
  `description` text DEFAULT NULL,
  `photo_limit` int(11) DEFAULT 5,
  `ppt_size_limit` int(11) DEFAULT 2,
  `registration_limit` int(11) DEFAULT 100,
  `last_submission_date` date DEFAULT NULL,
  `result_date` date DEFAULT NULL,
  `status` enum('draft','open','ongoing','finished') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: registrations
-- ============================================
CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: quiz
-- ============================================
CREATE TABLE `quiz` (
  `id` int(11) NOT NULL,
  `question` text DEFAULT NULL,
  `option_a` varchar(255) DEFAULT NULL,
  `option_b` varchar(255) DEFAULT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `answer` varchar(1) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: quiz_results
-- ============================================
CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Table: event_results
-- ============================================
CREATE TABLE `event_results` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `prize_description` varchar(255) DEFAULT NULL,
  `announced_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- Table: photo_submissions
-- ============================================
CREATE TABLE `photo_submissions` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- Table: ppt_submissions
-- ============================================
CREATE TABLE `ppt_submissions` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `ppt_path` varchar(255) NOT NULL,
  `presentation_order` int(11) DEFAULT 0,
  `status` enum('pending','called','presenting','completed','not_attended','skipped') DEFAULT 'pending',
  `marks` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ============================================
-- Indexes
-- ============================================

ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `department_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suggested_by` (`suggested_by`);

ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `event_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_position` (`event_id`,`position`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `event_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `photo_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `ppt_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `quiz`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reg` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

ALTER TABLE `study_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

-- ============================================
-- AUTO_INCREMENT
-- ============================================

ALTER TABLE `departments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `department_suggestions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `events` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `event_results` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `event_types` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `photo_submissions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ppt_submissions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `quiz` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `quiz_results` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `registrations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `study_years` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- ============================================
-- Foreign Key Constraints
-- ============================================

ALTER TABLE `quiz`
  ADD CONSTRAINT `quiz_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_results`
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

-- ============================================
-- Sample Data (for testing)
-- ============================================

INSERT INTO `event_types` (`id`, `name`, `icon`) VALUES
(1, 'quiz', '📝'),
(2, 'photography', '📷'),
(3, 'presentation', '📊');

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Admin', 'admin@campus.edu', '$2y$10$dummyhashedpasswordforadmin', 'admin');

COMMIT;

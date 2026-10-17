-- ============================================================================
-- Ocayur - Doctor & Patient Chat System Database Schema
-- Table: chat_messages
-- Database: jewrzsmy_panchved
-- ============================================================================

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` INT(11) NOT NULL,
  `patient_id` INT(11) NOT NULL,
  `sender_type` ENUM('doctor', 'patient') NOT NULL,
  `message` TEXT NOT NULL,
  `is_seen` TINYINT(1) NOT NULL DEFAULT 0,
  `seen_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_pair` (`doctor_id`, `patient_id`, `created_at`),
  KEY `idx_unread` (`doctor_id`, `patient_id`, `sender_type`, `is_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

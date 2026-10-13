-- ============================================================================
-- Ocayur - Ayurveda Lifestyle
-- Comprehensive Database Schema SQL Script
-- Database: jewrzsmy_panchved / jcwrzsmy_panchved
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ----------------------------------------------------------------------------
-- Table: users (Admin, Doctors & Staff Authentication)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `full_name` VARCHAR(150) DEFAULT 'Admin User',
  `email` VARCHAR(150) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'Admin',
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  UNIQUE KEY `idx_user_email` (`email`),
  UNIQUE KEY `idx_user_phone` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Users (Password: admin123)
-- Valid bcrypt hash for 'admin123': $2y$10$d417Xs.WV8vdff3JpnWuVu42pdRs5ES09vSQFFQzhJzoCXDHsrchi
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `phone_number`, `password_hash`, `role`, `status`) VALUES
(1, 'admin', 'John Doe', 'admin@ocayur.com', '9876543210', '$2y$10$d417Xs.WV8vdff3JpnWuVu42pdRs5ES09vSQFFQzhJzoCXDHsrchi', 'Admin', 'Active'),
(2, 'drnidhi', 'Dr. Nidhi Jha', 'drnidhi@gmail.com', '9876543211', '$2y$10$d417Xs.WV8vdff3JpnWuVu42pdRs5ES09vSQFFQzhJzoCXDHsrchi', 'Doctor', 'Active'),
(3, 'staff', 'Reception Desk', 'staff@ocayur.com', '9876543212', '$2y$10$d417Xs.WV8vdff3JpnWuVu42pdRs5ES09vSQFFQzhJzoCXDHsrchi', 'Staff', 'Active')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `password_hash` = VALUES(`password_hash`);


-- ----------------------------------------------------------------------------
-- Table: doctors
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `doctorid` VARCHAR(50) DEFAULT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male',
  `email` VARCHAR(150) NOT NULL,
  `years_of_experience` INT(11) NOT NULL DEFAULT 0,
  `expertise` VARCHAR(150) NOT NULL,
  `area` VARCHAR(150) NOT NULL,
  `registration_number` VARCHAR(100) NOT NULL,
  `hpr_registration_number` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` VARCHAR(45) NOT NULL DEFAULT '123456',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_doctorid` (`doctorid`),
  UNIQUE KEY `idx_email` (`email`),
  UNIQUE KEY `idx_phone` (`phone_number`),
  UNIQUE KEY `idx_reg_number` (`registration_number`),
  KEY `idx_doc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Doctors
INSERT INTO `doctors` (`id`, `doctorid`, `full_name`, `date_of_birth`, `phone_number`, `gender`, `email`, `years_of_experience`, `expertise`, `area`, `registration_number`, `hpr_registration_number`, `status`, `password`) VALUES
(1, 'DOC000001', 'Dr. Nidhi Jha', '1986-06-15', '9876543210', 'Female', 'drnidhi@gmail.com', 12, 'Ayurveda Physician', 'Pediatrics & Gynecology', 'AYU1234', 'HPR1234', 'Active', '123456'),
(2, 'DOC000002', 'Dr. Rohit Mehra', '1988-04-20', '9823456789', 'Male', 'rohitmehra@gmail.com', 10, 'Physiotherapist', 'Orthopedic & Sports Rehab', 'PHY5678', 'HPR5678', 'Active', '123456'),
(3, 'DOC000003', 'Dr. Priya Patel', '1990-11-12', '9811223344', 'Female', 'priyapatel@gmail.com', 8, 'Panchakarma Specialist', 'Detox & Rejuvenation', 'AYU9012', 'HPR9012', 'Active', '123456'),
(4, 'DOC000004', 'Dr. Ankit Verma', '1982-02-28', '9833445566', 'Male', 'ankitverma@gmail.com', 16, 'Ayurvedic Consultant', 'Chronic Disease Management', 'AYU3456', 'HPR3456', 'Active', '123456'),
(5, 'DOC000005', 'Dr. Sneha Kulkarni', '1992-09-05', '9877889900', 'Female', 'snehak@gmail.com', 6, 'Neuro-Physiotherapist', 'Neurological Rehab', 'PHY7890', 'HPR7890', 'Inactive', '123456')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `expertise` = VALUES(`expertise`), `password` = VALUES(`password`);


-- ----------------------------------------------------------------------------
-- Table: patients
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(11) NOT NULL DEFAULT '30',
  `phone_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Male',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blood_group` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'O+',
  `emergency_contact` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `package_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'Stress Management',
  `total_appointments` int(11) NOT NULL DEFAULT '0',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Ongoing',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_patient_id` (`patient_id`),
  KEY `idx_patient_phone` (`phone_number`),
  KEY `idx_patient_name` (`full_name`),
  KEY `idx_patient_package` (`package_name`),
  KEY `idx_patient_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Patients (matching My Patients UI & directory)
INSERT INTO `patients` (`id`, `patient_id`, `full_name`, `dob`, `age`, `phone_number`, `gender`, `email`, `package_name`, `total_appointments`, `status`) VALUES
(1, 'P001', 'Rahul Sharma', '1990-05-14', 34, '9876543210', 'Male', 'rahulsharma@gmail.com', 'Stresscare', 18, 'Ongoing'),
(2, 'P002', 'Pooja Deshmukh', '1996-08-22', 28, '9812345678', 'Female', 'poojad@gmail.com', 'Reset Your Hormones', 12, 'Ongoing'),
(3, 'P003', 'Vikram Malhotra', '1982-11-03', 42, '9823456781', 'Male', 'vikram.m@gmail.com', 'Gut Healing Package', 24, 'Completed'),
(4, 'P004', 'Ananya Sengupta', '1995-02-18', 29, '9834567892', 'Female', 'ananya.s@gmail.com', 'Work On Metabolism', 15, 'Ongoing'),
(5, 'P005', 'Suresh Iyer', '1974-09-30', 50, '9845678903', 'Male', 'sureshiyer@gmail.com', 'Stresscare', 20, 'Ongoing'),
(6, 'P006', 'Meera Nair', '1989-12-10', 35, '9856789014', 'Female', 'meera.nair@gmail.com', 'Reset Your Hormones', 8, 'Completed'),
(7, 'P007', 'Amit Patel', '1987-04-15', 37, '9867890125', 'Male', 'amit.patel@gmail.com', 'Gut Healing Package', 14, 'Ongoing'),
(8, 'P008', 'Sneha Joshi', '1993-07-25', 31, '9878901236', 'Female', 'sneha.joshi@gmail.com', 'Reset Your Hormones', 10, 'Ongoing'),
(9, 'P009', 'Rajesh Kulkarni', '1980-01-19', 44, '9889012347', 'Male', 'rajesh.k@gmail.com', 'Stresscare', 22, 'Completed'),
(10, 'P010', 'Kavita Reddy', '1991-09-08', 33, '9890123458', 'Female', 'kavita.reddy@gmail.com', 'Work On Metabolism', 16, 'Ongoing'),
(11, 'P011', 'Nitin Gadkari', '1985-03-30', 39, '9801234569', 'Male', 'nitin.g@gmail.com', 'Gut Healing Package', 19, 'Ongoing'),
(12, 'P012', 'Priya Sharma', '1994-06-12', 30, '9811234570', 'Female', 'priya.sharma@gmail.com', 'Stresscare', 11, 'Ongoing'),
(13, 'P013', 'Deepak Verma', '1988-10-05', 36, '9822345681', 'Male', 'deepak.v@gmail.com', 'Reset Your Hormones', 17, 'Completed'),
(14, 'P014', 'Sunita Rao', '1979-12-21', 45, '9833456792', 'Female', 'sunita.rao@gmail.com', 'Work On Metabolism', 25, 'Ongoing'),
(15, 'P015', 'Rohan Mehta', '1992-02-14', 32, '9844567803', 'Male', 'rohan.mehta@gmail.com', 'Gut Healing Package', 13, 'Ongoing'),
(16, 'P016', 'Divya Sen', '1997-08-17', 27, '9855678914', 'Female', 'divya.sen@gmail.com', 'Stresscare', 9, 'Ongoing'),
(17, 'P017', 'Manoj Tiwari', '1976-03-11', 48, '9866789025', 'Male', 'manoj.tiwari@gmail.com', 'Gut Healing Package', 21, 'Completed'),
(18, 'P018', 'Rekha Gupta', '1983-07-29', 41, '9877890136', 'Female', 'rekha.gupta@gmail.com', 'Reset Your Hormones', 14, 'Ongoing'),
(19, 'P019', 'Alok Nath', '1971-11-04', 53, '9888901247', 'Male', 'alok.nath@gmail.com', 'Stresscare', 19, 'Ongoing'),
(20, 'P020', 'Swati Saxena', '1998-04-18', 26, '9899012358', 'Female', 'swati.saxena@gmail.com', 'Work On Metabolism', 7, 'Ongoing')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `package_name` = VALUES(`package_name`), `patient_id` = VALUES(`patient_id`);


-- ----------------------------------------------------------------------------
-- Table: packages
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `package_id` VARCHAR(50) DEFAULT NULL,
  `package_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `duration` VARCHAR(50) NOT NULL DEFAULT '4 Weeks',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `enrollments` INT(11) NOT NULL DEFAULT 0,
  `protocol_status` VARCHAR(50) NOT NULL DEFAULT 'Added',
  `image_url` VARCHAR(255) DEFAULT 'assets/package-thumb.jpg',
  `short_description` TEXT DEFAULT NULL,
  `overview` TEXT DEFAULT NULL,
  `benefits` TEXT DEFAULT NULL,
  `included` TEXT DEFAULT NULL,
  `diet_hydration` TEXT DEFAULT NULL,
  `yoga_physio` TEXT DEFAULT NULL,
  `ayurveda_dinacharya` TEXT DEFAULT NULL,
  `daily_activity` TEXT DEFAULT NULL,
  `patient_monitoring` TEXT DEFAULT NULL,
  `followup_review` TEXT DEFAULT NULL,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_pkg_code` (`package_id`),
  KEY `idx_pkg_name` (`package_name`),
  KEY `idx_pkg_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Packages
INSERT INTO `packages` (`id`, `package_id`, `package_name`, `category`, `duration`, `price`, `enrollments`, `protocol_status`, `short_description`, `overview`, `benefits`, `included`, `diet_hydration`, `yoga_physio`, `ayurveda_dinacharya`, `daily_activity`, `patient_monitoring`, `followup_review`, `status`) VALUES
(1, 'PKG-001', 'Gut Reset Workshop', 'Category A', '4 Weeks', 5000.00, 10, 'Added', 'A comprehensive 4-week holistic gut microbiome reset program.', 'Restores digestive fire (Agni) and strengthens gastrointestinal lining with bespoke herbal protocols.', 'Relieves bloating, improves digestion, boosts vitality and enhances nutrient absorption.', 'Weekly Ayurvedic consultations, personalized meal plan, herbal formulations, and lifestyle chart.', 'Warm herbal water, dosha-specific digestive kichadi, and prebiotic fiber additions.', 'Pawanmuktasana series, Vajrasana post meals, and core strengthening physiotherapy.', 'Morning warm water with ghee, tongue scraping, and early dinner routine before 7 PM.', '30 minutes brisk walking in morning sunlight and 10 minutes deep belly breathing.', 'Bi-weekly symptom score check-in via mobile portal and weight tracking.', 'Weekly review with senior Ayurveda physician and dietary adjustments.', 'Active'),
(2, 'PKG-002', 'Reset Your Hormones', 'Hormonal Health', '6 Weeks', 7500.00, 14, 'Added', 'Balance endocrine health and manage PCOS/Thyroid symptoms naturally.', 'Ayurvedic holistic therapies combined with therapeutic yoga to balance endocrine glands.', 'Regulates cycles, minimizes fatigue, and alleviates hormonal mood fluctuations.', 'Doctor consultations, custom herbal decoctions, and guided yoga sessions.', 'Phytoestrogen-rich nutrition, anti-inflammatory seed cycling, and herbal infusions.', 'Surya Namaskar, butterfly posture, and restorative pelvic floor exercises.', 'Abhyanga self-massage with warm sesame oil and soothing evening meditation.', 'Daily 45 minutes mixed aerobic movement and yoga nidra for restful sleep.', 'Monthly cycle tracker and hormone biomarker progression audits.', 'Fortnightly medical consultation and herbal formulation updates.', 'Active'),
(3, 'PKG-003', 'Stresscare & Sleep Optimization', 'Mental Wellness', '4 Weeks', 4500.00, 12, 'Added', 'Deep rejuvenation therapy designed to reduce cortisol and restore sleep architecture.', 'Integrates Panchakarma Shirodhara, herbal nervine tonics, and pranayama.', 'Lowers stress levels, relieves anxiety, and enhances sleep quality.', 'Weekly stress assessment, Brahmi herbal teas, and relaxation audio guides.', 'Soothing warm almond milk with nutmeg before bed, low-caffeine diet.', 'Pranayama (Anulom Vilom, Bhramari) and gentle stretching.', 'Nasya therapy with Anu Taila and foot massage (Padabhyanga) before sleep.', 'Daily evening nature walk without electronic devices.', 'Sleep diary monitoring and heart-rate variability (HRV) metrics.', 'Weekly wellness counseling and progress feedback session.', 'Active'),
(4, 'PKG-004', 'Work On Metabolism & Weight Rehab', 'Metabolic Care', '8 Weeks', 9000.00, 9, 'Added', 'Kickstart basal metabolic rate with classical Ayurveda and physical conditioning.', 'Accelerates fat metabolism through Medohar formulations and tailored physiotherapy.', 'Healthy sustainable weight loss, improved lipid profiles, and boundless energy.', '1-on-1 diet chart, metabolic booster herbal formulas, and weekly body composition checks.', 'Warm spiced digestive teas (ginger, cumin, coriander) and timed eating intervals.', 'Dynamic metabolic yoga drills and resistance band physiotherapy routines.', 'Dry herbal powder massage (Udvartana) to stimulate lymphatic flow.', '10,000 steps daily target with interval pacing.', 'Weekly inch loss tracking and metabolic health review.', 'Bi-weekly doctor consultations and custom recipe guides.', 'Active')
ON DUPLICATE KEY UPDATE `package_name` = VALUES(`package_name`), `price` = VALUES(`price`);


-- ----------------------------------------------------------------------------
-- Table: recommended_packages (Packages specifically recommended by doctor for patient)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recommended_packages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) DEFAULT NULL,
  `patient_phone` VARCHAR(20) DEFAULT NULL,
  `package_id` INT(11) DEFAULT NULL,
  `package_name` VARCHAR(150) NOT NULL,
  `doctor_id` INT(11) DEFAULT NULL,
  `doctor_name` VARCHAR(150) NOT NULL DEFAULT 'Dr. Nidhi Jha',
  `recommendation_note` TEXT DEFAULT NULL,
  `status` ENUM('Recommended', 'Accepted', 'Dismissed') NOT NULL DEFAULT 'Recommended',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_patient` (`patient_id`),
  KEY `idx_rec_phone` (`patient_phone`),
  KEY `idx_rec_pkg` (`package_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Recommended Packages
INSERT INTO `recommended_packages` (`id`, `patient_id`, `patient_phone`, `package_id`, `package_name`, `doctor_id`, `doctor_name`, `recommendation_note`, `status`) VALUES
(1, 1, '9876543210', 2, 'Reset Your Hormones', 1, 'Dr. Nidhi Jha', 'Recommended based on your recent metabolic and hormonal assessment to restore endocrine balance.', 'Recommended'),
(2, 1, '9876543210', 4, 'Work On Metabolism & Weight Rehab', 2, 'Dr. Rohit Mehra', 'Recommended for improving digestive fire (Agni) and postural core strengthening.', 'Recommended')
ON DUPLICATE KEY UPDATE `recommendation_note` = VALUES(`recommendation_note`);


-- ----------------------------------------------------------------------------
-- Table: appointments
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` VARCHAR(50) DEFAULT NULL,
  `patient_id` INT(11) DEFAULT NULL,
  `patient_name` VARCHAR(150) NOT NULL,
  `doctor_id` INT(11) DEFAULT NULL,
  `doctor_name` VARCHAR(150) NOT NULL,
  `package_name` VARCHAR(150) DEFAULT 'Stress Management',
  `appointment_date` DATE NOT NULL,
  `appointment_time` VARCHAR(50) NOT NULL DEFAULT '8:00 AM',
  `duration` VARCHAR(30) NOT NULL DEFAULT '45 min',
  `service_type` VARCHAR(150) DEFAULT 'Consultation & Rehab',
  `agenda` TEXT DEFAULT NULL,
  `prescription` TEXT DEFAULT NULL,
  `status` ENUM('Scheduled', 'Upcoming', 'Completed', 'Cancelled', 'Pending') NOT NULL DEFAULT 'Scheduled',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_appointment_code` (`appointment_id`),
  KEY `fk_appt_patient` (`patient_id`),
  KEY `fk_appt_doctor` (`doctor_id`),
  KEY `idx_appt_date` (`appointment_date`),
  KEY `idx_appt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Appointments
INSERT INTO `appointments` (`id`, `appointment_id`, `patient_id`, `patient_name`, `doctor_id`, `doctor_name`, `package_name`, `appointment_date`, `appointment_time`, `duration`, `agenda`, `prescription`, `status`) VALUES
(1, 'ABC-001', 1, 'Rahul Mishra', 1, 'Dr. Nidhi Jha', 'Stress Management', CURDATE(), '8:00 AM - 8:45 AM', '45 mins', 'Follow-up consultation for stress care protocol and sleep quality check.', 'Ashwagandha Churna 3g twice daily with warm milk, Brahmi Vati 1 tablet before bed.', 'Scheduled'),
(2, 'ABC-002', 2, 'Pooja Deshmukh', 1, 'Dr. Nidhi Jha', 'Reset Your Hormones', CURDATE(), '9:30 AM - 10:15 AM', '45 mins', 'Hormonal balance progress review and diet adherence monitoring.', 'Shatavari Ghruta 1 tsp morning empty stomach, Kanchnar Guggulu 2 tablets twice daily.', 'Scheduled'),
(3, 'ABC-003', 3, 'Vikram Malhotra', 2, 'Dr. Rohit Mehra', 'Gut Healing Package', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '11:00 AM - 11:45 AM', '45 mins', 'Musculoskeletal assessment and lower back rehab physiotherapy exercise review.', 'Kottamchukkadi Taila local application followed by hot fomentation.', 'Completed'),
(4, 'ABC-004', 4, 'Ananya Sengupta', 4, 'Dr. Ankit Verma', 'Work On Metabolism', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '2:00 PM - 2:45 PM', '45 mins', 'Metabolic checkup and digestive enzyme analysis.', 'Triphala Guggulu 2 tablets before bedtime with warm water.', 'Completed'),
(5, 'ABC-005', 5, 'Suresh Iyer', 3, 'Dr. Priya Patel', 'Stresscare', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '4:15 PM - 5:00 PM', '45 mins', 'Panchakarma Shirodhara post-therapy evaluation.', 'Manasamitra Vatakam 1 tab at bedtime, daily Nasya with Anu Taila.', 'Completed')
ON DUPLICATE KEY UPDATE `appointment_time` = VALUES(`appointment_time`), `duration` = VALUES(`duration`), `patient_name` = VALUES(`patient_name`), `doctor_name` = VALUES(`doctor_name`);


-- ----------------------------------------------------------------------------
-- Table: workshops
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workshops` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `workshop_id` VARCHAR(50) DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `instructor` VARCHAR(150) NOT NULL,
  `speaker` VARCHAR(150) DEFAULT NULL,
  `speaker_role` VARCHAR(150) DEFAULT 'Ayurveda Physician',
  `speaker_bio` TEXT DEFAULT NULL,
  `attendee_type` VARCHAR(100) NOT NULL DEFAULT 'Doctor',
  `date` DATE NOT NULL,
  `time` VARCHAR(50) NOT NULL DEFAULT '8:00 AM',
  `duration_text` VARCHAR(50) NOT NULL DEFAULT '90 mins',
  `location` VARCHAR(150) NOT NULL DEFAULT 'Virtual Google Meet',
  `capacity` INT(11) NOT NULL DEFAULT 50,
  `enrolled` INT(11) NOT NULL DEFAULT 0,
  `registrations` INT(11) NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 799.00,
  `fee` DECIMAL(10,2) NOT NULL DEFAULT 799.00,
  `meet_link` VARCHAR(255) DEFAULT 'meet.google.com/abc-xyz-pqrs',
  `about` TEXT DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT 'assets/courses/course_business.jpg',
  `status` ENUM('Upcoming', 'Completed', 'Cancelled', 'Active', 'Past') NOT NULL DEFAULT 'Upcoming',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_workshop_code` (`workshop_id`),
  KEY `idx_ws_date` (`date`),
  KEY `idx_ws_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Workshops (matching Courses & Workshops UI)
INSERT INTO `workshops` (`id`, `workshop_id`, `title`, `instructor`, `speaker`, `speaker_role`, `speaker_bio`, `attendee_type`, `date`, `time`, `duration_text`, `fee`, `price`, `meet_link`, `image_url`, `about`, `status`) VALUES
(1, 'WS-001', 'Mindset For Business World Of Ayurveda', 'Dr. Ananya Mehta', 'Dr. Ananya Mehta', 'Ayurveda Physician', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.', 'Doctor', '2026-09-02', '8:00 AM', 'month', 5999.00, 5999.00, 'meet.google.com/abc-xyz-pqrs', 'assets/courses/course_business.jpg', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet.', 'Upcoming'),
(2, 'WS-002', 'Skill Development Guide For Ayurveda Doctors', 'Dr. Rajesh Varma', 'Dr. Rajesh Varma', 'Ayurveda Physician', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.', 'Patient', '2026-09-03', '8:00 AM', '90 mins', 799.00, 799.00, 'meet.google.com/abc-xyz-pqrs', 'assets/courses/course_ayurveda_herbs.jpg', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet.', 'Upcoming'),
(3, 'WS-003', 'Telemedicine Necessities Laws & AI', 'Dr. Sneha Patil', 'Dr. Sneha Patil', 'Ayurveda Physician', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.', 'Patient', '2026-09-03', '8:00 AM', '90 mins', 799.00, 799.00, 'meet.google.com/abc-xyz-pqrs', 'assets/courses/course_telemedicine.jpg', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet.', 'Upcoming'),
(4, 'WS-004', 'Online Consultation + Marketing Strategy', 'Dr. Amit Kulkarni', 'Dr. Amit Kulkarni', 'Ayurveda Physician', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.', 'Doctor', '2026-09-03', '8:00 AM', '90 mins', 799.00, 799.00, 'meet.google.com/abc-xyz-pqrs', 'assets/courses/course_consultation.jpg', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet.', 'Upcoming'),
(5, 'WS-005', 'Garbhasanskaar & Future Approach of Ayurveda', 'Dr. Pooja Joshi', 'Dr. Pooja Joshi', 'Ayurveda Physician', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.', 'Doctor', '2026-09-03', '8:00 AM', '90 mins', 499.00, 499.00, 'meet.google.com/abc-xyz-pqrs', 'assets/courses/course_garbhasanskaar.jpg', 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet.', 'Upcoming')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `fee` = VALUES(`fee`), `price` = VALUES(`price`), `duration_text` = VALUES(`duration_text`), `date` = VALUES(`date`), `image_url` = VALUES(`image_url`), `attendee_type` = VALUES(`attendee_type`);


-- ----------------------------------------------------------------------------
-- Table: workshop_bookings
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workshop_bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` VARCHAR(50) NOT NULL,
  `workshop_id` INT(11) NOT NULL,
  `doctor_id` INT(11) NOT NULL DEFAULT 1,
  `doctor_name` VARCHAR(150) NOT NULL,
  `doctor_email` VARCHAR(150) NOT NULL,
  `doctor_phone` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'card',
  `payment_status` ENUM('Success', 'Pending', 'Failed') NOT NULL DEFAULT 'Success',
  `booking_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_booking_code` (`booking_id`),
  KEY `fk_booking_workshop` (`workshop_id`),
  KEY `fk_booking_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Enrolled Doctor Bookings
INSERT INTO `workshop_bookings` (`id`, `booking_id`, `workshop_id`, `doctor_id`, `doctor_name`, `doctor_email`, `doctor_phone`, `amount`, `payment_method`, `payment_status`) VALUES
(1, '#123456', 1, 1, 'John Doe', 'johndoe@gmail.com', '9876543210', 799.00, 'card', 'Success'),
(2, '#123457', 2, 1, 'John Doe', 'johndoe@gmail.com', '9876543210', 799.00, 'upi', 'Success'),
(3, '#123458', 3, 1, 'John Doe', 'johndoe@gmail.com', '9876543210', 799.00, 'paypal', 'Success'),
(4, '#123459', 4, 1, 'John Doe', 'johndoe@gmail.com', '9876543210', 799.00, 'card', 'Success'),
(5, '#123460', 5, 1, 'John Doe', 'johndoe@gmail.com', '9876543210', 499.00, 'card', 'Success')
ON DUPLICATE KEY UPDATE `payment_status` = VALUES(`payment_status`);

-- ----------------------------------------------------------------------------
-- Table: prescriptions
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `prescription_code` VARCHAR(50) DEFAULT NULL,
  `patient_id` INT(11) NOT NULL,
  `patient_name` VARCHAR(150) NOT NULL,
  `doctor_id` INT(11) DEFAULT 1,
  `doctor_name` VARCHAR(150) NOT NULL DEFAULT 'Dr. Ananya Prasad',
  `doctor_specialty` VARCHAR(150) NOT NULL DEFAULT 'Ayurvedic Medicine',
  `doctor_phone` VARCHAR(50) NOT NULL DEFAULT '+91 7896543210',
  `doctor_email` VARCHAR(150) NOT NULL DEFAULT 'ocayursupport@gmail.com',
  `appointment_id` INT(11) DEFAULT NULL,
  `prescription_date` DATE NOT NULL,
  `prescription_time` VARCHAR(30) NOT NULL DEFAULT '11:20 AM',
  `medical_history` TEXT DEFAULT NULL,
  `symptoms` TEXT DEFAULT NULL,
  `diagnosis` VARCHAR(255) NOT NULL DEFAULT 'Migraine',
  `diagnosis_duration` VARCHAR(100) NOT NULL DEFAULT '3 months',
  `medications` LONGTEXT DEFAULT NULL,
  `examination_findings` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rx_code` (`prescription_code`),
  KEY `fk_rx_patient` (`patient_id`),
  KEY `fk_rx_doctor` (`doctor_id`),
  KEY `fk_rx_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data for Prescriptions
INSERT INTO `prescriptions` (`id`, `prescription_code`, `patient_id`, `patient_name`, `doctor_id`, `doctor_name`, `doctor_specialty`, `doctor_phone`, `doctor_email`, `appointment_id`, `prescription_date`, `prescription_time`, `medical_history`, `symptoms`, `diagnosis`, `diagnosis_duration`, `medications`, `examination_findings`, `notes`) VALUES
(1, 'RX-001', 1, 'Rahul Sharma', 1, 'Dr. Ananya Prasad', 'Ayurvedic Medicine', '+91 7896543210', 'ocayursupport@gmail.com', 1, '2026-09-08', '11:20 AM', 'No Known Significant Medical History', 'Constipation (Severity : Moderate)', 'Migraine', '3 months', '[{\"medication\":\"TAB MEENTOACID (TABLET)\",\"dose\":\"1 TABLET\",\"frequency\":\"1-0-1 BEFORE MEAL\",\"duration\":\"10 DAYS\",\"remarks\":\"TAKE 1 TABLET - TWICE A DAY, BEFORE BREAKFAST AND BEFORE DINNER FOR 10 DAYS\"},{\"medication\":\"CAP HERBOCALM (CAPSULE)\",\"dose\":\"1 CAPSULE\",\"frequency\":\"0-0-1 AFTER DINNER\",\"duration\":\"15 DAYS\",\"remarks\":\"TAKE WITH WARM MILK BEFORE SLEEP\"}]', 'Abdomen Feels Distended', 'Gandbush ( Cow Ghee + Triphala Powder )'),
(2, 'RX-002', 2, 'Pooja Deshmukh', 1, 'Dr. Ananya Prasad', 'Ayurvedic Medicine', '+91 7896543210', 'ocayursupport@gmail.com', 2, '2026-09-12', '10:15 AM', 'Hormonal imbalance history, PCOD diagnosis 2 years ago', 'Irregular cycles, Fatigue (Severity : Moderate)', 'PCOS / Hormonal Imbalance', '6 months', '[{\"medication\":\"SHATAVARI GHRUTA (SYRUP)\",\"dose\":\"2 TEASPOONS\",\"frequency\":\"1-0-1 EMPTY STOMACH\",\"duration\":\"30 DAYS\",\"remarks\":\"TAKE IN THE MORNING AND EVENING WITH WARM WATER\"},{\"medication\":\"KANCHNAR GUGGULU (TABLET)\",\"dose\":\"2 TABLETS\",\"frequency\":\"1-0-1 AFTER MEALS\",\"duration\":\"20 DAYS\",\"remarks\":\"FOR HORMONAL AND METABOLIC REGULATION\"}]', 'Mild thyroid enlargement palpated', 'Nasya with Anu Taila daily in morning; Seed cycling protocol'),
(3, 'RX-003', 3, 'Vikram Malhotra', 2, 'Dr. Rohit Mehra', 'Orthopedic & Sports Rehab', '+91 9823456789', 'rohitmehra@gmail.com', 3, '2026-09-15', '02:30 PM', 'Lower lumbar spine stiffness, post-strenuous exercise', 'Lower back ache, Hamstring tightness (Severity : Mild)', 'Lumbar Spondylosis', '4 months', '[{\"medication\":\"TRIPHALA GUGGULU (TABLET)\",\"dose\":\"2 TABLETS\",\"frequency\":\"0-0-2 BEFORE SLEEP\",\"duration\":\"14 DAYS\",\"remarks\":\"TAKE WITH WARM WATER AT BEDTIME FOR GUT HEALTH\"}]', 'L4-L5 lumbar tenderness on flexion', 'Kottamchukkadi Taila local application + Physio core stretches')
ON DUPLICATE KEY UPDATE `patient_name` = VALUES(`patient_name`), `medications` = VALUES(`medications`), `symptoms` = VALUES(`symptoms`), `diagnosis` = VALUES(`diagnosis`), `notes` = VALUES(`notes`);

COMMIT;


-- ============================================================
-- ANU MEAL BOOKING SYSTEM — MySQL Database
-- Import this in phpMyAdmin or run via MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS `anu_meal_booking`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `anu_meal_booking`;

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(50)  UNIQUE NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `fullname`   VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) UNIQUE NOT NULL,
  `role`       ENUM('super_admin','admin','student') NOT NULL DEFAULT 'student',
  `student_id` VARCHAR(30)  DEFAULT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: menus
-- ----------------------------
CREATE TABLE IF NOT EXISTS `menus` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `type`        ENUM('Breakfast','Lunch','Dinner') NOT NULL,
  `date`        DATE NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price`       DECIMAL(8,2) DEFAULT 0.00,
  `available`   TINYINT(1)   DEFAULT 1,
  `created_by`  INT          DEFAULT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: bookings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `code`         VARCHAR(20) UNIQUE NOT NULL,
  `user_id`      INT NOT NULL,
  `menu_id`      INT NOT NULL,
  `date`         DATE NOT NULL,
  `status`       ENUM('pending','approved','rejected','consumed') DEFAULT 'pending',
  `validated_by` INT      DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`menu_id`)      REFERENCES `menus`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: system_logs
-- ----------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT          DEFAULT NULL,
  `action`     VARCHAR(255) NOT NULL,
  `details`    TEXT         DEFAULT NULL,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: settings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key`   VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Default Users
-- Passwords are bcrypt hashed:
--   superadmin & admin  → admin123
--   student             → student123
 -- INSERT INTO `users` (`username`, `password`, `fullname`, `email`, `role`, `student_id`) VALUES
--('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'superadmin@anu.ac.ke', 'super_admin', 'SA001'),
--('admin',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cafeteria Manager',   'manager@anu.ac.ke',    'admin',       'AD001'),
--('student',     '$2y$10$TKh8H1.PyfSAL0Wg.W4HQOS3Jl6JnAl1PJdW1', 'John Doe', 'john.doe@anu.ac.ke', 'student', 'ANU/2024/001');

-- NOTE: If the above bcrypt hashes don't work in your PHP version,
-- run install.php once to regenerate them properly. Then delete install.php.

-- Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('org_name',          'Africa Nazarene University'),
('org_email',         'support@anu.ac.ke'),
('timezone',          'Africa/Nairobi'),
('booking_open',      '06:00'),
('booking_close',     '22:00'),
('auto_reset',        '0'),
('maintenance_mode',  '0'),
('meal_alerts',       '1'),
('email_reports',     '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Sample Menu Items (today and tomorrow)
INSERT INTO `menus` (`name`, `type`, `date`, `description`, `price`, `available`, `created_by`) VALUES
('Mandazi & Milk Tea',     'Breakfast', CURDATE(),                    'Freshly fried mandazi served with warm milk tea',         80.00,  1, 1),
('Ugali & Beef Stew',      'Lunch',     CURDATE(),                    'Traditional ugali with slow-cooked beef stew and kachumbari', 150.00, 1, 1),
('Rice & Grilled Chicken', 'Dinner',    CURDATE(),                    'Steamed jasmine rice with seasoned grilled chicken',      200.00, 1, 1),
('Porridge',               'Breakfast', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Nutritious maize porridge sweetened with sugar',  60.00,  1, 1),
('Chapati & Beans',        'Lunch',     DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Soft layered chapati with seasoned beans',        120.00, 1, 1),
('Githeri',                'Dinner',    DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Mixed maize and beans, a Kenyan classic',         100.00, 1, 1),
('Egg & Toast',            'Breakfast', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Scrambled eggs with buttered toast',              90.00,  1, 1),
('Pilau & Kachumbari',     'Lunch',     DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Spiced Kenyan pilau with fresh tomato kachumbari',180.00, 1, 1);

-- Sample system log entry
INSERT INTO `system_logs` (`user_id`, `action`, `details`, `ip`) VALUES
(1, 'Database Installed', 'ANU Meal Booking System database was set up successfully.', '127.0.0.1');

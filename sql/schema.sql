CREATE DATABASE IF NOT EXISTS secdash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE secdash;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','analyste') DEFAULT 'analyste',
  failed_attempts INT DEFAULT 0,
  locked_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_type ENUM('login_success','login_failed','access_denied','other') NOT NULL,
  username VARCHAR(50),
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip_address),
  INDEX (created_at)
);

CREATE TABLE alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  severity ENUM('faible','moyenne','haute') DEFAULT 'moyenne',
  message VARCHAR(255),
  status ENUM('nouvelle','en_cours','resolue') DEFAULT 'nouvelle',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE blocked_ips (
  ip_address VARCHAR(45) PRIMARY KEY,
  reason VARCHAR(255),
  blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Utilisateur dédié (évite d'utiliser root dans l'application)
CREATE USER IF NOT EXISTS 'secdash_user'@'localhost' IDENTIFIED BY 'Secdash2026Xk9';
GRANT SELECT, INSERT, UPDATE, DELETE ON secdash.* TO 'secdash_user'@'localhost';
FLUSH PRIVILEGES;
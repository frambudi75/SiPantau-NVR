-- NVR Database Schema
CREATE DATABASE IF NOT EXISTS nvr_db;
USE nvr_db;

-- Table for cameras
CREATE TABLE IF NOT EXISTS cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rtsp_url VARCHAR(255) NOT NULL,
    location VARCHAR(100),
    status ENUM('online', 'offline') DEFAULT 'offline',
    is_recording TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for recordings metadata
CREATE TABLE IF NOT EXISTS recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id INT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255),
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    file_size BIGINT DEFAULT 0,
    FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE CASCADE
);

-- Table for system settings
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT
);

-- Default settings (using IGNORE to prevent duplicate errors)
INSERT IGNORE INTO settings (`key`, `value`) VALUES 
('storage_path', 'recordings/'),
('retention_days', '7'),
('ffmpeg_path', 'ffmpeg'),
('telegram_bot_token', ''),
('telegram_chat_id', '');

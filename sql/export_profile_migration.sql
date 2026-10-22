-- Run this on existing databases to enable phase-1 customizable export profiles.

CREATE TABLE IF NOT EXISTS export_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  company_name VARCHAR(150) NOT NULL DEFAULT '',
  company_address VARCHAR(255) NOT NULL DEFAULT '',
  company_logo VARCHAR(255) NOT NULL DEFAULT 'logo/Logo.png',
  supervisor_name VARCHAR(120) NOT NULL DEFAULT '',
  supervisor_title VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_export_profile_student (student_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_export_profile_student
  ON export_profiles(student_id);

ALTER TABLE export_profiles
  ADD COLUMN IF NOT EXISTS company_address VARCHAR(255) NOT NULL DEFAULT '' AFTER company_name;

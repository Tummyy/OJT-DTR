-- Run this on existing databases to enable email verification OTP flow.

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER password;

-- Backfill legacy accounts so pre-existing users can still sign in.
UPDATE students
SET email_verified_at = COALESCE(email_verified_at, created_at, NOW())
WHERE email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  resend_count INT NOT NULL DEFAULT 0,
  attempt_count INT NOT NULL DEFAULT 0,
  request_ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_email_verify_student_time
  ON email_verifications(student_id, created_at);

CREATE INDEX IF NOT EXISTS idx_email_verify_expiry
  ON email_verifications(expires_at);

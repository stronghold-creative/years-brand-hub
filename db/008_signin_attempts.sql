-- Failed sign-in attempts, so passwords cannot be guessed at speed.
CREATE TABLE IF NOT EXISTS sign_in_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  ip         VARBINARY(16) NULL,
  at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_email (email, at),
  INDEX idx_attempts_ip (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

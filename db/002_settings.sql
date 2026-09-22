-- Settings that editors can change without touching config.php.
CREATE TABLE IF NOT EXISTS settings (
  name       VARCHAR(80) NOT NULL PRIMARY KEY,
  value      TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

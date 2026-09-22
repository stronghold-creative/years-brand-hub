-- Years brand hub, initial schema. MySQL 8 / MariaDB 10.5+
-- Run once from hPanel phpMyAdmin, or via the migration runner.

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  name          VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(190) NOT NULL UNIQUE,
  title      VARCHAR(190) NOT NULL,
  section    VARCHAR(120) NOT NULL DEFAULT 'General',
  summary    VARCHAR(400) NULL,
  status     ENUM('draft','published') NOT NULL DEFAULT 'draft',
  sort_order INT NOT NULL DEFAULT 0,
  blocks     LONGTEXT NOT NULL,           -- JSON array of blocks
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT UNSIGNED NULL,
  INDEX idx_pages_section (section, sort_order),
  INDEX idx_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One snapshot per save. Gives history and undo for very little code.
CREATE TABLE IF NOT EXISTS page_versions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(190) NOT NULL,
  blocks     LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  INDEX idx_versions_page (page_id, created_at),
  CONSTRAINT fk_versions_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assets land here in the next pass. Schema now so migrations stay linear.
CREATE TABLE IF NOT EXISTS assets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stored_name   VARCHAR(190) NOT NULL UNIQUE,  -- name on disk in storage/assets
  original_name VARCHAR(190) NOT NULL,
  label         VARCHAR(190) NOT NULL,
  mime          VARCHAR(120) NOT NULL,
  bytes         BIGINT UNSIGNED NOT NULL,
  version       VARCHAR(40) NOT NULL DEFAULT '1.0',
  tags          VARCHAR(400) NULL,
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_by   INT UNSIGNED NULL,
  INDEX idx_assets_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS downloads (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_id      INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,
  downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_downloads_asset (asset_id, downloaded_at),
  CONSTRAINT fk_downloads_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
  filename   VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

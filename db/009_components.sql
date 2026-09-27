-- The component library. A component is not a page: it has variants, a
-- lifecycle, and a spec table derived from its own CSS rather than typed, none
-- of which models as a stack of blocks. See docs/COMPONENTS.md.

CREATE TABLE IF NOT EXISTS components (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(190) NOT NULL UNIQUE,
  name            VARCHAR(190) NOT NULL,
  group_name      VARCHAR(120) NOT NULL DEFAULT 'Components',
  status          ENUM('draft','ready','deprecated') NOT NULL DEFAULT 'draft',
  summary         VARCHAR(400) NULL,
  figma_url       VARCHAR(500) NULL,
  figma_file_key  VARCHAR(80) NULL,
  figma_node_id   VARCHAR(80) NULL,
  base_css        LONGTEXT NOT NULL,          -- shared by every variant
  base_js         LONGTEXT NOT NULL,          -- rare; a disclosure or a carousel
  docs            LONGTEXT NOT NULL,          -- JSON array of blocks, as pages
  notes_override  LONGTEXT NOT NULL,          -- JSON; a written note beats a derived one
  sort_order      INT NOT NULL DEFAULT 0,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by      INT UNSIGNED NULL,
  INDEX idx_components_group (group_name, sort_order),
  INDEX idx_components_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One rendering: "Primary", "Primary, disabled", "Small". The markup is stored
-- raw, which nothing else in this codebase does, and is rendered only inside an
-- opaque-origin sandboxed frame. Never echo it into an admin or reader page.
CREATE TABLE IF NOT EXISTS component_variants (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  component_id  INT UNSIGNED NOT NULL,
  label         VARCHAR(190) NOT NULL,
  markup        LONGTEXT NOT NULL,
  variant_css   LONGTEXT NOT NULL,
  notes         VARCHAR(400) NULL,
  is_default    TINYINT(1) NOT NULL DEFAULT 0,
  sort_order    INT NOT NULL DEFAULT 0,
  INDEX idx_variants_component (component_id, sort_order),
  CONSTRAINT fk_variants_component FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One snapshot per save, the component and its variants together as JSON. Same
-- purpose as page_versions, and pruned the same way.
CREATE TABLE IF NOT EXISTS component_versions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  component_id  INT UNSIGNED NOT NULL,
  name          VARCHAR(190) NOT NULL,
  snapshot      LONGTEXT NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    INT UNSIGNED NULL,
  INDEX idx_component_versions (component_id, created_at),
  CONSTRAINT fk_cversions_component FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

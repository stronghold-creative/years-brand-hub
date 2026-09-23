-- A cover image per page, for the card grid on the index.
ALTER TABLE pages ADD COLUMN cover_asset_id INT UNSIGNED NULL AFTER summary;

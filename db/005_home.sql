-- Which pages appear on the front grid. Everything published shows by default,
-- which matches how the site behaved before this column existed.
ALTER TABLE pages ADD COLUMN show_on_home TINYINT(1) NOT NULL DEFAULT 1 AFTER cover_asset_id;

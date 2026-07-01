-- Cash Flow Summary — local MySQL setup (Step 12)
-- Run as a MySQL admin user (e.g. root). Replace the password before executing.
--
--   mysql -u root -p < scripts/create-local-database.sql
--
-- Or paste into mysql client / TablePlus / Sequel Ace.

CREATE DATABASE IF NOT EXISTS cashflow_summary
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Drop and recreate user only if re-running setup (optional — comment out if first run)
-- DROP USER IF EXISTS 'cashflow_app'@'localhost';

CREATE USER IF NOT EXISTS 'cashflow_app'@'localhost'
  IDENTIFIED BY 'CHANGE_ME_LOCAL_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE ON cashflow_summary.* TO 'cashflow_app'@'localhost';

FLUSH PRIVILEGES;

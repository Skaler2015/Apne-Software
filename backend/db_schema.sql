-- ============================================================
-- ApneSoftware.com — Analytics Backend Database Schema
-- ============================================================
-- HOW TO USE THIS FILE:
-- 1. Log in to hPanel -> Databases -> MySQL Databases
-- 2. Create a new database (e.g. u123456789_apnesoftware) and a database user, note the password
-- 3. Open phpMyAdmin for that database
-- 4. Go to the "Import" tab, choose this file, and click "Go"
-- This will create all tables needed. Safe to re-run (uses IF NOT EXISTS).
-- ============================================================

-- Master list of tools (kept in sync with assets/tools-data.json)
CREATE TABLE IF NOT EXISTS tools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tool_slug VARCHAR(100) NOT NULL UNIQUE,   -- matches the "id" field in tools-data.json e.g. "pdf-merge"
  tool_name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  icon VARCHAR(20) DEFAULT NULL,
  total_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_runs BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every page view of a tool
CREATE TABLE IF NOT EXISTS tool_views (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tool_id INT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  country VARCHAR(80) DEFAULT NULL,
  region VARCHAR(80) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  browser VARCHAR(50) DEFAULT NULL,
  os VARCHAR(50) DEFAULT NULL,
  device_type ENUM('desktop','mobile','tablet','other') DEFAULT 'other',
  referrer_source VARCHAR(50) DEFAULT NULL,   -- google / bing / direct / social / referral
  referrer_url VARCHAR(500) DEFAULT NULL,
  landing_page VARCHAR(255) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  view_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  INDEX idx_tool_date (tool_id, view_date),
  INDEX idx_date (view_date),
  INDEX idx_created (created_at),
  INDEX idx_country (country),
  INDEX idx_device (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every "run" (actual tool usage, e.g. clicked Merge / Convert / Generate / Download)
CREATE TABLE IF NOT EXISTS tool_runs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tool_id INT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  country VARCHAR(80) DEFAULT NULL,
  region VARCHAR(80) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  browser VARCHAR(50) DEFAULT NULL,
  os VARCHAR(50) DEFAULT NULL,
  device_type ENUM('desktop','mobile','tablet','other') DEFAULT 'other',
  run_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  INDEX idx_tool_date (tool_id, run_date),
  INDEX idx_date (run_date),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily rollup, recalculated/updated each time a view or run is logged (keeps dashboard charts fast)
CREATE TABLE IF NOT EXISTS daily_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NOT NULL UNIQUE,
  total_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_runs BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
  mobile_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  desktop_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tablet_views BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Small cache so we don't call the free GeoIP API for the same IP repeatedly (rate-limit friendly)
CREATE TABLE IF NOT EXISTS geoip_cache (
  ip_address VARCHAR(45) PRIMARY KEY,
  country VARCHAR(80) DEFAULT NULL,
  region VARCHAR(80) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple admin login (separate from the existing static-password admin panel)
CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the 103 tools from tools-data.json. Run backend/sync_tools.php once after import
-- (or after adding new tools) to keep this table in sync automatically — no need to edit this SQL by hand.

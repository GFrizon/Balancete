-- ============================================================
-- SISTEMA BALANCETE
-- Estrutura do banco de dados
-- MySQL / MariaDB 10.4+ | Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','user') NOT NULL DEFAULT 'user',
  `force_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `cnpj` VARCHAR(20) NOT NULL DEFAULT '',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_cnpj` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_units` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bu_company_code` (`company_id`, `code`),
  CONSTRAINT `fk_bu_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `unit_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `unit_group_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_group_id` INT UNSIGNED NOT NULL,
  `business_unit_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ugi` (`unit_group_id`, `business_unit_id`),
  CONSTRAINT `fk_ugi_group` FOREIGN KEY (`unit_group_id`) REFERENCES `unit_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ugi_bu` FOREIGN KEY (`business_unit_id`) REFERENCES `business_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `imports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `business_unit_id` INT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `original_filename` VARCHAR(255) NOT NULL,
  `file_hash` CHAR(64) NOT NULL COMMENT 'SHA-256',
  `status` ENUM('pending','processing','confirmed','error') NOT NULL DEFAULT 'pending',
  `error_message` TEXT NULL,
  `imported_by` INT UNSIGNED NOT NULL,
  `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `raw_text_path` VARCHAR(500) NULL COMMENT 'Caminho do arquivo salvo',
  PRIMARY KEY (`id`),
  KEY `idx_imports_unit_year_month` (`business_unit_id`, `year`, `month`),
  KEY `idx_imports_company` (`company_id`),
  CONSTRAINT `fk_imports_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_imports_bu` FOREIGN KEY (`business_unit_id`) REFERENCES `business_units` (`id`),
  CONSTRAINT `fk_imports_user` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trial_balance_rows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `import_id` INT UNSIGNED NOT NULL,
  `line_number` INT UNSIGNED NOT NULL,
  `account_code` VARCHAR(20) NOT NULL,
  `account_description` VARCHAR(500) NOT NULL,
  `indentation_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_analytical` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=sub-conta com unidade',
  `movement_value` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `movement_type` ENUM('DB','CR','') NOT NULL DEFAULT '',
  `debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'armazenado apenas para auditoria',
  `credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'armazenado apenas para auditoria',
  `raw_line` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tbr_import` (`import_id`),
  KEY `idx_tbr_code` (`account_code`),
  CONSTRAINT `fk_tbr_import` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL DEFAULT '',
  `entity_id` INT UNSIGNED NULL,
  `payload` JSON NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dre_simulations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `company_id` INT UNSIGNED NULL,
  `group_id` INT UNSIGNED NULL,
  `unit_id` INT UNSIGNED NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `month_start` TINYINT UNSIGNED NOT NULL,
  `month_end` TINYINT UNSIGNED NOT NULL,
  `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dre_simulations_period` (`year`, `month_start`, `month_end`),
  KEY `idx_dre_simulations_company` (`company_id`),
  KEY `idx_dre_simulations_group` (`group_id`),
  KEY `idx_dre_simulations_unit` (`unit_id`),
  CONSTRAINT `fk_dre_simulations_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dre_simulations_unit` FOREIGN KEY (`unit_id`) REFERENCES `business_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dre_simulations_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dre_simulation_adjustments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `simulation_id` INT UNSIGNED NOT NULL,
  `row_key_hash` CHAR(64) NOT NULL,
  `row_key` TEXT NOT NULL,
  `account_code` VARCHAR(20) NOT NULL DEFAULT '',
  `account_description` VARCHAR(500) NOT NULL DEFAULT '',
  `indentation_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `target_period` VARCHAR(7) NOT NULL DEFAULT '',
  `adjustment_mode` ENUM('amount','percent','override') NOT NULL DEFAULT 'amount',
  `adjustment_value` DECIMAL(18,2) NULL,
  `adjustment_percent` DECIMAL(10,4) NULL,
  `classification` ENUM('none','revenue','variable','fixed','non_recurring','non_operational') NOT NULL DEFAULT 'none',
  `note` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dre_sim_adjustment` (`simulation_id`, `row_key_hash`, `target_period`),
  KEY `idx_dre_sim_adjustment_simulation` (`simulation_id`),
  CONSTRAINT `fk_dre_sim_adjustment_simulation` FOREIGN KEY (`simulation_id`) REFERENCES `dre_simulations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dre_sim_adjustment_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dre_sim_adjustment_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `selector` CHAR(24) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_selector` (`selector`),
  KEY `idx_remember_user` (`user_id`),
  KEY `idx_remember_expires` (`expires_at`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- ORANGE INVOICE & CUSTOMER MANAGEMENT SYSTEM
-- Complete MySQL Database Schema and Seed Data Script
-- Compatible with MySQL 8.0+, MariaDB 10.4+, phpMyAdmin, and Laravel 11/12
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- TABLE STRUCTURE: users
-- =============================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: password_reset_tokens
-- =============================================================================
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: sessions
-- =============================================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL DEFAULT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: personal_access_tokens (Sanctum)
-- =============================================================================
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` TEXT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL DEFAULT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: company_settings
-- =============================================================================
DROP TABLE IF EXISTS `company_settings`;
CREATE TABLE `company_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(255) NOT NULL DEFAULT 'Orange Invoicing & CRM Ltd',
  `company_logo` VARCHAR(255) NULL DEFAULT NULL,
  `company_address` TEXT NULL DEFAULT NULL,
  `phone_number` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `gstin` VARCHAR(255) NULL DEFAULT NULL,
  `invoice_prefix` VARCHAR(255) NOT NULL DEFAULT 'INV-',
  `default_currency` VARCHAR(255) NOT NULL DEFAULT 'INR',
  `default_currency_symbol` VARCHAR(255) NOT NULL DEFAULT '₹',
  `default_payment_terms` TEXT NULL DEFAULT NULL,
  `invoice_footer` TEXT NULL DEFAULT NULL,
  `terms_and_conditions` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: customers
-- =============================================================================
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(255) NOT NULL,
  `last_name` VARCHAR(255) NOT NULL,
  `company_name` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) NULL DEFAULT NULL,
  `city` VARCHAR(255) NOT NULL,
  `country` VARCHAR(255) NOT NULL DEFAULT 'India',
  `payment_method` ENUM('Daily Pay', 'Monthly Pay', '15 Days Pay') NOT NULL DEFAULT 'Monthly Pay',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customers_email_index` (`email`),
  KEY `customers_city_index` (`city`),
  KEY `customers_payment_method_index` (`payment_method`),
  KEY `customers_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: customer_phones
-- =============================================================================
DROP TABLE IF EXISTS `customer_phones`;
CREATE TABLE `customer_phones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `phone_number` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `type` VARCHAR(255) NOT NULL DEFAULT 'Mobile',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_phones_customer_id_is_primary_index` (`customer_id`, `is_primary`),
  CONSTRAINT `customer_phones_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: items
-- =============================================================================
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `import_declaration` TEXT NULL DEFAULT NULL,
  `export_declaration` TEXT NULL DEFAULT NULL,
  `item_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `gst_percentage` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
  `certificate_of_origin` VARCHAR(255) NULL DEFAULT NULL,
  `xo_permit` VARCHAR(255) NULL DEFAULT NULL,
  `amendment` VARCHAR(255) NULL DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `items_item_name_index` (`item_name`),
  KEY `items_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: invoice_sequences
-- =============================================================================
DROP TABLE IF EXISTS `invoice_sequences`;
CREATE TABLE `invoice_sequences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year` INT NOT NULL,
  `prefix` VARCHAR(20) NOT NULL DEFAULT 'INV',
  `last_number` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_sequences_year_prefix_unique` (`year`, `prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: invoices
-- =============================================================================
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(255) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `customer_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `company_name` VARCHAR(255) NULL DEFAULT NULL,
  `customer_address` TEXT NULL DEFAULT NULL,
  `customer_phone` VARCHAR(255) NULL DEFAULT NULL,
  `customer_email` VARCHAR(255) NULL DEFAULT NULL,
  `payment_method` VARCHAR(255) NULL DEFAULT NULL,
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_gst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `additional_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `terms_and_conditions` TEXT NULL DEFAULT NULL,
  `status` ENUM('Draft', 'Generated', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled') NOT NULL DEFAULT 'Draft',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_invoice_date_index` (`invoice_date`),
  KEY `invoices_due_date_index` (`due_date`),
  KEY `invoices_status_index` (`status`),
  CONSTRAINT `invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: invoice_items
-- =============================================================================
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `quantity` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
  `gst_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `gst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_sort_order_index` (`invoice_id`, `sort_order`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: payments
-- =============================================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `payment_number` VARCHAR(255) NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_method` VARCHAR(255) NOT NULL,
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_payment_number_unique` (`payment_number`),
  KEY `payments_invoice_id_payment_date_index` (`invoice_id`, `payment_date`),
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: cache & cache_locks
-- =============================================================================
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE STRUCTURE: jobs & failed_jobs
-- =============================================================================
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL DEFAULT NULL,
  `cancelled_at` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SEED DATA
-- =============================================================================

-- 1. Default Administrator User (admin@orangeinvoice.com / password123)
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `created_at`, `updated_at`)
VALUES (
  1,
  'Orange Admin',
  'admin@orangeinvoice.com',
  NOW(),
  '$2y$10$i4afk5DVjZ0oafG4bVb05eRiSJgE45IjDJsjyzI3g6Qjo1Aac/b4K',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. Default Company Settings
INSERT INTO `company_settings` (
  `id`, `company_name`, `company_logo`, `company_address`, `phone_number`, `email`, `gstin`,
  `invoice_prefix`, `default_currency`, `default_currency_symbol`, `default_payment_terms`,
  `invoice_footer`, `terms_and_conditions`, `created_at`, `updated_at`
) VALUES (
  1,
  'Orange Invoicing & Logistics Ltd',
  NULL,
  'Plot 42, Tech Innovation Corridor, Phase 1\nBengaluru, Karnataka - 560100, India',
  '+91 80 4123 4567',
  'contact@orangeinvoice.com',
  '29AAFCO8421K1ZN',
  'INV-',
  'INR',
  '₹',
  'Net 15 days from invoice issuance date',
  'Thank you for your valuable partnership. For assistance, reach us at contact@orangeinvoice.com',
  '1. Goods and services supplied are subject to Orange Invoicing standard trade terms.\n2. Overdue payments accrue interest at 1.5% per month.\n3. All disputes are subject to the exclusive jurisdiction of the courts of Bengaluru.',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE `company_name`=VALUES(`company_name`);

-- 3. Seed Items
INSERT INTO `items` (`id`, `item_name`, `import_declaration`, `export_declaration`, `item_cost`, `gst_percentage`, `certificate_of_origin`, `xo_permit`, `amendment`, `status`, `created_at`, `updated_at`)
VALUES
(1, 'Industrial Centrifugal Pump 50HP', 'IMP/2026/BLR/88219 - Customs cleared through Bangalore ICD', 'EXP/DE/441092 - Port of Hamburg outbound', 45000.00, 18.00, 'COO-IND-2026-9912', 'XO-PMT-7821-B', 'AMD-02/RevA', 'active', NOW(), NOW()),
(2, 'Heavy Duty Hydraulic Valve Assembly', 'IMP/2026/MAA/10492 - Port of Chennai Customs', 'EXP/SG/209182 - Singapore Jurong Port Transit', 12500.00, 18.00, 'COO-IND-2026-5510', 'XO-PMT-4019-A', 'AMD-01', 'active', NOW(), NOW()),
(3, 'Automated PLC Control Panel Unit', 'IMP/2026/BOM/33019 - Nhava Sheva JNPT Customs', 'EXP/UAE/990123 - Jebel Ali Port destination', 85000.00, 18.00, 'COO-IND-2026-1188', 'XO-PMT-9182-C', 'AMD-03/FullSpec', 'active', NOW(), NOW()),
(4, 'Precision Stainless Steel Flanges DN100', 'IMP/2026/DEL/77120 - Tughlakabad ICD clearance', 'EXP/US/102938 - Long Beach Marine clearance', 3800.00, 12.00, 'COO-IND-2026-3390', 'XO-PMT-2201-D', 'None', 'active', NOW(), NOW()),
(5, 'High-Speed Conveyor Belt Roller 1200mm', 'IMP/2026/HYD/40192 - Hyderabad Cargo Airport', 'EXP/UK/551209 - Felixstowe Container terminal', 9200.00, 18.00, 'COO-IND-2026-7721', 'XO-PMT-6610-A', 'AMD-01/RubberSpec', 'active', NOW(), NOW()),
(6, 'Customs Clearance & Documentation Service', 'CHA Clearance License Ref # 4492/KA', 'EDI Filing Acknowledgement # 992019', 7500.00, 18.00, 'CHAMBER-COMM-BLR', 'XO-SRV-001', 'Standard', 'active', NOW(), NOW());

-- 4. Seed Customers
INSERT INTO `customers` (`id`, `first_name`, `last_name`, `company_name`, `email`, `address_line1`, `address_line2`, `city`, `country`, `payment_method`, `status`, `notes`, `created_at`, `updated_at`)
VALUES
(1, 'Rajesh', 'Sharma', 'Apex Engineering Works Pvt Ltd', 'rajesh.sharma@apexeng.co.in', '24 Industrial Suburb, Peenya 2nd Stage', 'Near Central Warehouse', 'Bengaluru', 'India', 'Monthly Pay', 'active', 'Key industrial engineering customer', NOW(), NOW()),
(2, 'Priya', 'Nair', 'Oceanic Marine & Logistics Ltd', 'priya.nair@oceanicmarine.com', 'Harbor View Commercial Complex, Suite 501', 'Willingdon Island', 'Kochi', 'India', '15 Days Pay', 'active', 'Marine cargo logistics client', NOW(), NOW()),
(3, 'Vikram', 'Mehta', 'Gujarat Precision Forgings Corp', 'vmehta@gujaratforgings.in', 'GIDC Estate, Phase 3, Plot 110', 'Makarpura', 'Vadodara', 'India', 'Daily Pay', 'active', 'Daily forging component supplier', NOW(), NOW()),
(4, 'Sunita', 'Patel', 'Zenith Global Trading FZE', 'sunita.patel@zenithglobal.com', 'Tower B, 14th Floor, World Trade Centre', 'Cuffe Parade', 'Mumbai', 'India', 'Monthly Pay', 'active', 'High volume trading partner', NOW(), NOW()),
(5, 'Amitabh', 'Verma', 'Verma Hydrotech Systems', 'averma@hydrotechsystems.net', 'Sector 58, Industrial Model Township', 'Ballabgarh Road', 'Faridabad', 'India', '15 Days Pay', 'active', 'Hydraulic cylinders contractor', NOW(), NOW());

-- 5. Seed Customer Phones (Multiple Phone Numbers per customer)
INSERT INTO `customer_phones` (`id`, `customer_id`, `phone_number`, `is_primary`, `type`, `created_at`, `updated_at`)
VALUES
(1, 1, '+91 98450 12345', 1, 'Mobile', NOW(), NOW()),
(2, 1, '+91 80 2839 0011', 0, 'Work', NOW(), NOW()),
(3, 2, '+91 97451 98765', 1, 'Mobile', NOW(), NOW()),
(4, 2, '+91 484 266 7788', 0, 'Work', NOW(), NOW()),
(5, 2, '+91 94470 55443', 0, 'Alternate', NOW(), NOW()),
(6, 3, '+91 98250 33445', 1, 'Mobile', NOW(), NOW()),
(7, 3, '+91 265 264 9900', 0, 'Work', NOW(), NOW()),
(8, 4, '+91 98201 88990', 1, 'Mobile', NOW(), NOW()),
(9, 4, '+91 22 6655 4400', 0, 'Work', NOW(), NOW()),
(10, 5, '+91 98110 44556', 1, 'Mobile', NOW(), NOW());

-- 6. Seed Invoice Sequence
INSERT INTO `invoice_sequences` (`id`, `year`, `prefix`, `last_number`, `created_at`, `updated_at`)
VALUES (1, YEAR(CURRENT_DATE), 'INV', 4, NOW(), NOW())
ON DUPLICATE KEY UPDATE `last_number` = 4;

-- 7. Seed Invoices
INSERT INTO `invoices` (
  `id`, `invoice_number`, `invoice_date`, `due_date`, `customer_id`,
  `customer_name`, `company_name`, `customer_address`, `customer_phone`, `customer_email`,
  `payment_method`, `subtotal`, `total_gst`, `discount`, `additional_charges`,
  `grand_total`, `amount_paid`, `balance_due`, `payment_terms`, `notes`, `terms_and_conditions`, `status`,
  `created_at`, `updated_at`
) VALUES
-- Invoice 1: Paid in full (Rajesh Sharma)
(
  1,
  CONCAT('INV-', YEAR(CURRENT_DATE), '-0001'),
  DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY),
  DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY),
  1,
  'Rajesh Sharma',
  'Apex Engineering Works Pvt Ltd',
  '24 Industrial Suburb, Peenya 2nd Stage, Bengaluru',
  '+91 98450 12345',
  'rajesh.sharma@apexeng.co.in',
  'Monthly Pay',
  90000.00,
  16200.00,
  2000.00,
  500.00,
  104700.00,
  104700.00,
  0.00,
  'Net 15 days',
  'Annual equipment supply agreement order # 104.',
  'Standard warranty applicable for 12 months.',
  'Paid',
  DATE_SUB(NOW(), INTERVAL 10 DAY),
  NOW()
),
-- Invoice 2: Partially Paid (Sunita Patel)
(
  2,
  CONCAT('INV-', YEAR(CURRENT_DATE), '-0002'),
  DATE_SUB(CURRENT_DATE, INTERVAL 4 DAY),
  DATE_ADD(CURRENT_DATE, INTERVAL 11 DAY),
  4,
  'Sunita Patel',
  'Zenith Global Trading FZE',
  'Tower B, 14th Floor, World Trade Centre, Mumbai',
  '+91 98201 88990',
  'sunita.patel@zenithglobal.com',
  'Monthly Pay',
  85000.00,
  15300.00,
  0.00,
  1000.00,
  101300.00,
  50000.00,
  51300.00,
  '50% advance, balance on delivery',
  'Export packing and documentation completed.',
  'Standard export terms applicable.',
  'Partially Paid',
  DATE_SUB(NOW(), INTERVAL 4 DAY),
  NOW()
),
-- Invoice 3: Generated Today (Priya Nair)
(
  3,
  CONCAT('INV-', YEAR(CURRENT_DATE), '-0003'),
  CURRENT_DATE,
  DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY),
  2,
  'Priya Nair',
  'Oceanic Marine & Logistics Ltd',
  'Harbor View Commercial Complex, Suite 501, Kochi',
  '+91 97451 98765',
  'priya.nair@oceanicmarine.com',
  '15 Days Pay',
  25000.00,
  4500.00,
  500.00,
  0.00,
  29000.00,
  0.00,
  29000.00,
  'Net 15 days',
  'Routine shipment delivery to Kochi port terminal.',
  'Standard commercial terms.',
  'Generated',
  NOW(),
  NOW()
),
-- Invoice 4: Overdue (Vikram Mehta)
(
  4,
  CONCAT('INV-', YEAR(CURRENT_DATE), '-0004'),
  DATE_SUB(CURRENT_DATE, INTERVAL 25 DAY),
  DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY),
  3,
  'Vikram Mehta',
  'Gujarat Precision Forgings Corp',
  'GIDC Estate, Phase 3, Plot 110, Vadodara',
  '+91 98250 33445',
  'vmehta@gujaratforgings.in',
  'Daily Pay',
  15200.00,
  1824.00,
  0.00,
  200.00,
  17224.00,
  0.00,
  17224.00,
  'Immediate on supply',
  'Follow up sent on overdue balance.',
  '1.5% interest accrues monthly.',
  'Overdue',
  DATE_SUB(NOW(), INTERVAL 25 DAY),
  NOW()
);

-- 8. Seed Invoice Line Items
INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_id`, `item_name`, `description`, `price`, `quantity`, `gst_percentage`, `gst_amount`, `line_total`, `sort_order`, `created_at`, `updated_at`)
VALUES
(1, 1, 1, 'Industrial Centrifugal Pump 50HP', 'Industrial Centrifugal Pump 50HP with custom base mountings', 45000.00, 2.00, 18.00, 16200.00, 90000.00, 0, DATE_SUB(NOW(), INTERVAL 10 DAY), NOW()),
(2, 2, 3, 'Automated PLC Control Panel Unit', 'Automated PLC Control Panel Unit with SCADA interface', 85000.00, 1.00, 18.00, 15300.00, 85000.00, 0, DATE_SUB(NOW(), INTERVAL 4 DAY), NOW()),
(3, 3, 2, 'Heavy Duty Hydraulic Valve Assembly', 'Heavy Duty Hydraulic Valve Assembly (Marine grade)', 12500.00, 2.00, 18.00, 4500.00, 25000.00, 0, NOW(), NOW()),
(4, 4, 4, 'Precision Stainless Steel Flanges DN100', 'Precision Stainless Steel Flanges DN100 Grade 316L', 3800.00, 4.00, 12.00, 1824.00, 15200.00, 0, DATE_SUB(NOW(), INTERVAL 25 DAY), NOW());

-- 9. Seed Payments Recorded
INSERT INTO `payments` (`id`, `invoice_id`, `payment_number`, `payment_date`, `amount`, `payment_method`, `reference_number`, `notes`, `created_at`, `updated_at`)
VALUES
(
  1,
  1,
  CONCAT('PAY-', YEAR(CURRENT_DATE), '-0001'),
  DATE_SUB(CURRENT_DATE, INTERVAL 8 DAY),
  104700.00,
  'Bank Transfer',
  'NEFT-AXIS-99201948',
  'Full payment received through Axis Bank',
  DATE_SUB(NOW(), INTERVAL 8 DAY),
  NOW()
),
(
  2,
  2,
  CONCAT('PAY-', YEAR(CURRENT_DATE), '-0002'),
  DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY),
  50000.00,
  'UPI',
  'UPI/401929381920',
  '50% advance received via corporate UPI',
  DATE_SUB(NOW(), INTERVAL 3 DAY),
  NOW()
);

SET FOREIGN_KEY_CHECKS = 1;

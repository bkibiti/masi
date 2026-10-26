-- ---------------------------------------------------------------------------
-- WADAU setup: run ONCE on the `wadau` database (a copy of the abg database).
--
-- 1. Drops the tables of the modules Wadau does not use
--    (sales, products/stock, purchases, partnership, customers).
-- 2. Creates the Wadau tables: members, member_phones, dependants, deaths.
-- 3. Cleans up permissions: removes the old module permissions and adds
--    the Members / Deaths permissions, given to the Superuser role.
--
-- In HeidiSQL / phpMyAdmin: select the `wadau` database FIRST, then run.
-- Afterwards run:  php artisan permission:cache-reset
-- ---------------------------------------------------------------------------

-- 0. Stop at once if no database is selected
SET @sql := IF(DATABASE() IS NULL,
    'SELECT * FROM `SELECT THE wadau DATABASE FIRST`',
    'SELECT DATABASE() AS running_on');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 1. Drop the old module tables (IF EXISTS: names that are not there are skipped)
-- ---------------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS `stock_movements`;

DROP TABLE IF EXISTS
    `sales`, `sale_details`, `sales_details`, `credit_sales`,
    `stock`, `stock_tracking`, `stock_adjustments`, `stock_issues`, `issue_returns`, `stock_transfers`,
    `products`, `product_categories`, `price_list`, `price_categories`,
    `purchases`, `goods_receiving`, `orders`, `order_details`, `suppliers`,
    `adjustment_reasons`, `locations`,
    `partners`, `partner_contributions`, `partner_payouts`,
    `customers`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- 2. Wadau tables
-- ---------------------------------------------------------------------------

-- Wanachama
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_no` VARCHAR(20) NULL,
    `name` VARCHAR(150) NOT NULL,
    `gender` ENUM('Male', 'Female') NOT NULL,
    `date_of_birth` DATE NULL,
    `location` VARCHAR(100) NULL,
    `joined_on` DATE NULL,
    `status` ENUM('Active', 'Inactive', 'Deceased') NOT NULL DEFAULT 'Active',
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `members_member_no_unique` (`member_no`),
    KEY `members_status_index` (`status`),
    KEY `members_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Up to 3 mobile numbers per member (the limit is enforced by the app)
CREATE TABLE IF NOT EXISTS `member_phones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `phone` VARCHAR(12) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `member_phones_member_phone_unique` (`member_id`, `phone`),
    KEY `member_phones_phone_index` (`phone`),
    CONSTRAINT `member_phones_member_id_foreign` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wategemezi: Mzazi (wazazi), Mkwe (wakwe), Mtoto (watoto), Mume, Mke
CREATE TABLE IF NOT EXISTS `dependants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `relationship` ENUM('Mzazi', 'Mkwe', 'Mtoto', 'Mume', 'Mke') NOT NULL,
    `gender` ENUM('Male', 'Female') NULL,
    `date_of_birth` DATE NULL,
    `phone` VARCHAR(12) NULL,
    `status` ENUM('Alive', 'Deceased') NOT NULL DEFAULT 'Alive',
    `notes` VARCHAR(255) NULL,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `dependants_member_id_index` (`member_id`),
    KEY `dependants_relationship_index` (`relationship`),
    CONSTRAINT `dependants_member_id_foreign` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vifo: death history of members and dependants.
-- Rows are never deleted. A death recorded by mistake is reversed (reversed_at is set),
-- so the history of what was recorded, by whom and when is kept.
CREATE TABLE IF NOT EXISTS `deaths` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT UNSIGNED NOT NULL,
    `dependant_id` INT UNSIGNED NULL,
    `deceased_name` VARCHAR(150) NOT NULL,
    `relationship` VARCHAR(20) NOT NULL,
    `date_of_death` DATE NULL,
    `place_of_death` VARCHAR(150) NULL,
    `burial_date` DATE NULL,
    `burial_place` VARCHAR(150) NULL,
    `reported_by` VARCHAR(150) NULL,
    `notes` TEXT NULL,
    `recorded_by` INT UNSIGNED NULL,
    `reversed_at` TIMESTAMP NULL DEFAULT NULL,
    `reversed_by` INT UNSIGNED NULL,
    `reversal_reason` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `deaths_member_id_index` (`member_id`),
    KEY `deaths_dependant_id_index` (`dependant_id`),
    KEY `deaths_date_of_death_index` (`date_of_death`),
    CONSTRAINT `deaths_member_id_foreign` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `deaths_dependant_id_foreign` FOREIGN KEY (`dependant_id`)
        REFERENCES `dependants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Permissions
-- ---------------------------------------------------------------------------

-- 3a. Rename the customer permissions to members (keeps existing role assignments)
UPDATE `permissions` SET `name` = 'View Members'   WHERE `name` = 'View Customers'   AND `guard_name` = 'web';
UPDATE `permissions` SET `name` = 'Manage Members' WHERE `name` = 'Manage Customers' AND `guard_name` = 'web';

-- 3b. Remove permissions of the dropped modules
DELETE rhp FROM `role_has_permissions` rhp
JOIN `permissions` p ON p.`id` = rhp.`permission_id`
WHERE p.`name` IN (
    'View Sales Management', 'Point of Sales', 'View Sales History',
    'View Inventory Management', 'View Current Stock', 'View Adjustment History', 'Adjust Stock', 'Stock Transfer',
    'View Purchase Management', 'Receive Goods', 'Purchase History', 'Edit Purchase',
    'View Products', 'Manage Products', 'View Products Categories', 'Manage Products Categories',
    'View Suppliers', 'Manage Suppliers',
    'View Partnership', 'Manage Partnership', 'Reverse payouts'
);

DELETE mhp FROM `model_has_permissions` mhp
JOIN `permissions` p ON p.`id` = mhp.`permission_id`
WHERE p.`name` IN (
    'View Sales Management', 'Point of Sales', 'View Sales History',
    'View Inventory Management', 'View Current Stock', 'View Adjustment History', 'Adjust Stock', 'Stock Transfer',
    'View Purchase Management', 'Receive Goods', 'Purchase History', 'Edit Purchase',
    'View Products', 'Manage Products', 'View Products Categories', 'Manage Products Categories',
    'View Suppliers', 'Manage Suppliers',
    'View Partnership', 'Manage Partnership', 'Reverse payouts'
);

DELETE FROM `permissions` WHERE `name` IN (
    'View Sales Management', 'Point of Sales', 'View Sales History',
    'View Inventory Management', 'View Current Stock', 'View Adjustment History', 'Adjust Stock', 'Stock Transfer',
    'View Purchase Management', 'Receive Goods', 'Purchase History', 'Edit Purchase',
    'View Products', 'Manage Products', 'View Products Categories', 'Manage Products Categories',
    'View Suppliers', 'Manage Suppliers',
    'View Partnership', 'Manage Partnership', 'Reverse payouts'
);

-- 3c. Wadau permissions (only added if missing)
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT x.`name`, 'web', NOW(), NOW()
FROM (
    SELECT 'View Members' AS `name`
    UNION ALL SELECT 'Manage Members'
    UNION ALL SELECT 'Manage Dependants'
    UNION ALL SELECT 'View Deaths'
    UNION ALL SELECT 'Record Deaths'
    UNION ALL SELECT 'Reverse Deaths'
) x
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`name` = x.`name` AND p.`guard_name` = 'web');

-- 3d. Give them to the Superuser role
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'Superuser' AND r.`guard_name` = 'web'
WHERE p.`guard_name` = 'web'
  AND p.`name` IN ('View Members', 'Manage Members', 'Manage Dependants', 'View Deaths', 'Record Deaths', 'Reverse Deaths')
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` rhp WHERE rhp.`permission_id` = p.`id` AND rhp.`role_id` = r.`id`
  );

-- ---------------------------------------------------------------------------
-- Check: tables left in the database, and the permissions list
-- ---------------------------------------------------------------------------
SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME;

SELECT `id`, `name` FROM `permissions` ORDER BY `name`;

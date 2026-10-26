-- ---------------------------------------------------------------------------
-- WADAU: dependant categories master (Wazazi, Wakwe, Watoto, Mume, Mke, ...)
--
-- Moves dependants.relationship (fixed list) to a dependant_categories table
-- that can be extended and renamed under Masters > Dependant Categories.
-- Existing dependants are moved to the matching category; nothing is lost.
--
-- Run AFTER wadau_setup.sql and BEFORE the Faraja import, on the `wadau`
-- database (select it FIRST). Safe to run more than once.
-- Afterwards: php artisan permission:cache-reset
-- ---------------------------------------------------------------------------

SET @sql := IF(DATABASE() IS NULL,
    'SELECT * FROM `SELECT THE wadau DATABASE FIRST`',
    'SELECT DATABASE() AS running_on');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1. Categories table
--    gender        = gender every dependant in this category has (Mume -> Male), NULL = any
--    member_gender = only members of this gender can add it (Mume -> Female member), NULL = any
CREATE TABLE IF NOT EXISTS `dependant_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `group_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female') NULL,
    `member_gender` ENUM('Male', 'Female') NULL,
    `sort_order` SMALLINT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `dependant_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. The starting categories (fixed ids 1-5, used by the Faraja import)
INSERT INTO `dependant_categories` (`id`, `name`, `group_name`, `gender`, `member_gender`, `sort_order`, `created_at`, `updated_at`)
SELECT x.* FROM (
    SELECT 1 AS id, 'Mzazi' AS name, 'Wazazi' AS group_name, NULL AS gender, NULL AS member_gender, 1 AS sort_order, NOW() AS c, NOW() AS u
    UNION ALL SELECT 2, 'Mkwe',  'Wakwe',  NULL, NULL, 2, NOW(), NOW()
    UNION ALL SELECT 3, 'Mtoto', 'Watoto', NULL, NULL, 3, NOW(), NOW()
    UNION ALL SELECT 4, 'Mume',  'Mume',   'Male', 'Female', 4, NOW(), NOW()
    UNION ALL SELECT 5, 'Mke',   'Mke',    'Female', 'Male', 5, NOW(), NOW()
) x
WHERE NOT EXISTS (SELECT 1 FROM `dependant_categories` c WHERE c.`id` = x.id);

-- 3. dependants.dependant_category_id
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dependants' AND COLUMN_NAME = 'dependant_category_id') = 0,
    'ALTER TABLE `dependants` ADD COLUMN `dependant_category_id` INT UNSIGNED NULL AFTER `member_id`',
    'SELECT ''dependant_category_id already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Move existing rows from the old relationship column, then drop it
SET @has_rel := (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dependants' AND COLUMN_NAME = 'relationship');

SET @sql := IF(@has_rel > 0,
    'UPDATE `dependants` d JOIN `dependant_categories` c ON c.`name` = d.`relationship` SET d.`dependant_category_id` = c.`id` WHERE d.`dependant_category_id` IS NULL',
    'SELECT ''relationship column already removed'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_rel > 0,
    'ALTER TABLE `dependants` DROP INDEX `dependants_relationship_index`, DROP COLUMN `relationship`',
    'SELECT ''nothing to drop'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Required + foreign key (a category in use cannot be deleted)
ALTER TABLE `dependants` MODIFY COLUMN `dependant_category_id` INT UNSIGNED NOT NULL;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dependants'
          AND CONSTRAINT_NAME = 'dependants_dependant_category_id_foreign') = 0,
    'ALTER TABLE `dependants` ADD CONSTRAINT `dependants_dependant_category_id_foreign` FOREIGN KEY (`dependant_category_id`) REFERENCES `dependant_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT ''foreign key already exists'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Permission to manage the categories, given to Superuser
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'Manage Dependant Categories', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `name` = 'Manage Dependant Categories' AND `guard_name` = 'web');

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'Superuser' AND r.`guard_name` = 'web'
WHERE p.`name` = 'Manage Dependant Categories' AND p.`guard_name` = 'web'
  AND NOT EXISTS (SELECT 1 FROM `role_has_permissions` rhp WHERE rhp.`permission_id` = p.`id` AND rhp.`role_id` = r.`id`);

-- Check
SELECT c.`id`, c.`name`, c.`group_name`, c.`gender`, c.`member_gender`, COUNT(d.`id`) AS dependants
FROM `dependant_categories` c LEFT JOIN `dependants` d ON d.`dependant_category_id` = c.`id`
GROUP BY c.`id` ORDER BY c.`sort_order`, c.`name`;

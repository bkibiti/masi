-- ---------------------------------------------------------------------------
-- WADAU settings cleanup: run ONCE on the `wadau` database.
--
-- Keeps only 4 settings (the ids used by the ABG settings page):
--   100 business_name -> group_name
--   101 registration number
--   104 slogan
--   105 logo
-- Everything else (TIN, VRN, VAT, receipt/printing options, ...) is removed.
-- Select the `wadau` database FIRST, then run.
-- ---------------------------------------------------------------------------

DELETE FROM `settings` WHERE `id` NOT IN (100, 101, 104, 105);

UPDATE `settings` SET `name` = 'group_name',          `display_name` = 'Group Name'          WHERE `id` = 100;
UPDATE `settings` SET `name` = 'registration_number', `display_name` = 'Registration Number' WHERE `id` = 101;
UPDATE `settings` SET `name` = 'slogan',              `display_name` = 'Slogan'              WHERE `id` = 104;
UPDATE `settings` SET `name` = 'logo',                `display_name` = 'Logo'                WHERE `id` = 105;

-- Add any of the 4 rows that are missing
INSERT INTO `settings` (`id`, `name`, `display_name`, `value`)
SELECT x.`id`, x.`name`, x.`display_name`, x.`value`
FROM (
    SELECT 100 AS `id`, 'group_name' AS `name`, 'Group Name' AS `display_name`, 'Wadau' AS `value`
    UNION ALL SELECT 101, 'registration_number', 'Registration Number', NULL
    UNION ALL SELECT 104, 'slogan', 'Slogan', NULL
    UNION ALL SELECT 105, 'logo', 'Logo', NULL
) x
WHERE NOT EXISTS (SELECT 1 FROM `settings` s WHERE s.`id` = x.`id`);

-- Check
SELECT `id`, `name`, `display_name`, `value` FROM `settings` ORDER BY `id`;

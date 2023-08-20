ALTER TABLE `codes`
    CHANGE COLUMN `code` `code` VARCHAR(25) NOT NULL DEFAULT '' COLLATE 'utf8mb4_general_ci' AFTER `id`,
    CHANGE COLUMN `code_type` `code_type` SMALLINT(6) NULL DEFAULT NULL AFTER `code`,
    ADD COLUMN `source` JSON NULL AFTER `revenue_code`;


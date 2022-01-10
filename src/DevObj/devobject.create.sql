CREATE TABLE `dev_obj` (
    `uuid` BINARY(16) DEFAULT (UUID_TO_BIN(UUID())),
    `obj_type` VARCHAR(10) NOT NULL,
    `obj_id` VARCHAR(255) NOT NULL,
    `obj_version` VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'Reserved for future use',
    `obj_desc` VARCHAR(255) NOT NULL,
    `active` TINYINT DEFAULT '1' COMMENT '0 = inactive, 1 = active',
    `obj_json` TEXT DEFAULT NULL COMMENT 'JSON data for the object',
    PRIMARY KEY (`obj_type`, `obj_id`, `obj_version`) USING BTREE,
    INDEX `uuid_dev_obj` (`uuid`) USING BTREE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
;
CREATE TABLE `dev_component` (
    `uuid` BINARY(16) DEFAULT (UUID_TO_BIN(UUID())),
    `obj_type` VARCHAR(10) NOT NULL,
    `obj_id` VARCHAR(255) NOT NULL,
    `obj_version` VARCHAR(10) NOT NULL DEFAULT '',
    `comp_obj_type` VARCHAR(10) NOT NULL,
    `comp_obj_id` VARCHAR(255) NOT NULL,
    `comp_obj_version` VARCHAR(10) NOT NULL DEFAULT '',
    `comp_type` VARCHAR(10) NOT NULL,
    `comp_seq` INT NOT NULL DEFAULT '0',
    `comp_json` TEXT DEFAULT NULL COMMENT 'JSON data for the object-component use',
    INDEX `ix_parent_obj` (`obj_type`, `obj_id`, `obj_version`) USING BTREE,
    INDEX `ix_comp_obj` (`comp_obj_type`, `comp_obj_id`, `comp_obj_version`) USING BTREE
)
COLLATE='utf8mb4_general_ci'
ENGINE=InnoDB
;
INSERT INTO `dev_obj` (`uuid`,`obj_type`,`obj_id`,`obj_desc`) VALUES
(UUID_TO_BIN('57839667-8949-11eb-a45f-001320cfc753',1),'','DevObject','Root object'),
(UUID_TO_BIN('90a8e2c7-8948-11eb-a45f-001320cfc753',1),'DevObject','DevScript','Script object')
;
INSERT INTO `dev_component` (`uuid`,`obj_type`,`obj_id`,`comp_obj_type`,`comp_obj_id`,`comp_type`,`comp_seq`) VALUES
(UUID_TO_BIN('57b2f8aa-8949-11eb-a45f-001320cfc753',1),'','DevObject','DevObject','DevScript','php',0),
(UUID_TO_BIN('57b30066-8949-11eb-a45f-001320cfc753',1),'','DevObject','DevObject','DevScript','js',0)
;

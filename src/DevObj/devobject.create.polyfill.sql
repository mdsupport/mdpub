DELIMITER $$

DROP FUNCTION IF EXISTS BIN_TO_UUID$$
CREATE FUNCTION BIN_TO_UUID(b BINARY(16), f BOOLEAN)
RETURNS CHAR(36)
DETERMINISTIC
BEGIN
   DECLARE hexStr CHAR(32);
   SET hexStr = HEX(b);
   RETURN LOWER(CONCAT(
        IF(f,SUBSTR(hexStr, 9, 8),SUBSTR(hexStr, 1, 8)), '-',
        IF(f,SUBSTR(hexStr, 5, 4),SUBSTR(hexStr, 9, 4)), '-',
        IF(f,SUBSTR(hexStr, 1, 4),SUBSTR(hexStr, 13, 4)), '-',
        SUBSTR(hexStr, 17, 4), '-',
        SUBSTR(hexStr, 21)
    ));
END$$

DROP FUNCTION IF EXISTS UUID_TO_BIN$$
CREATE FUNCTION UUID_TO_BIN(uuid CHAR(36), f BOOLEAN)
RETURNS BINARY(16)
DETERMINISTIC
BEGIN
    RETURN UNHEX(CONCAT(
        IF(f,SUBSTRING(uuid, 15, 4),SUBSTRING(uuid, 1, 8)),
        SUBSTRING(uuid, 10, 4),
        IF(f,SUBSTRING(uuid, 1, 8),SUBSTRING(uuid, 15, 4)),
        SUBSTRING(uuid, 20, 4),
        SUBSTRING(uuid, 25))
    );
END$$

DROP PROCEDURE IF EXISTS `execStatement`$$
CREATE PROCEDURE `execStatement`(IN textSql MEDIUMTEXT, IN tblName VARCHAR(64))
BEGIN
    SET @sql_statement = REPLACE(textSql, 'tblName', tblName);
    PREPARE sql_exec FROM @sql_statement;
    EXECUTE sql_exec;
    DEALLOCATE PREPARE sql_exec;
END$$

DROP TABLE IF EXISTS `dev_obj`$$
CREATE TABLE `dev_obj` (
    `uuid` BINARY(16) NOT NULL DEFAULT UNHEX(SYS_GUID()),
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
ENGINE=InnoDB$$

DROP TABLE IF EXISTS `dev_component`$$
CREATE TABLE `dev_component` (
    `uuid` BINARY(16) NOT NULL DEFAULT UNHEX(SYS_GUID()),
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
ENGINE=InnoDB$$

DROP PROCEDURE IF EXISTS `modify_null_uuids`$$
CREATE PROCEDURE `modify_null_uuids`()
BEGIN
    -- Declare variables needed later.
    DECLARE uuidTable VARCHAR(64) DEFAULT NULL; -- Table requiring trigger

    DECLARE sql_template MEDIUMTEXT;
    DECLARE sql_statement MEDIUMTEXT DEFAULT '';

    -- FETCH NEXT throws an exception at end which is caught by following handler.
    DECLARE RowNotFound TINYINT DEFAULT FALSE;
    -- Cursor to the list of tables requiring trigger
    DECLARE currTableList
     CURSOR FOR
     SELECT isCol.TABLE_NAME
       FROM information_schema.COLUMNS isCol
       LEFT OUTER JOIN information_schema.TRIGGERS isTrig
         ON isCol.TABLE_SCHEMA=isTrig.EVENT_OBJECT_SCHEMA
        AND isCol.TABLE_NAME=isTrig.EVENT_OBJECT_TABLE
      WHERE isCol.TABLE_SCHEMA=DATABASE()
        AND isCol.COLUMN_NAME='uuid'
        AND isCol.COLUMN_TYPE='binary(16)'
        AND isCol.COLUMN_DEFAULT='NULL'
        AND IFNULL(isTrig.TRIGGER_NAME,'') NOT LIKE 'autouuid_%'
        AND isTrig.EVENT_OBJECT_SCHEMA IS NULL;
    -- Define NOT FOUND exception handler
    DECLARE CONTINUE HANDLER
        FOR NOT FOUND
        SET RowNotFound = TRUE;

    -- Setup SQL statements to create trigger as JSON document
    SET @sql_insert_devsql = "
        INSERT INTO dev_obj(obj_type,obj_id,obj_version,obj_desc,active,obj_json)
        VALUES('DevSQL','Trigger_autouuid_tblName','','UUID TRIGGER FOR tblName',1,JSON_OBJECT(
        1, 'DROP TRIGGER IF EXISTS `autouuid_tblName`;',
        2, 'CREATE TRIGGER `autouuid_tblName` BEFORE INSERT ON `tblName` FOR EACH ROW
             BEGIN
                 IF (NEW.uuid IS NULL) THEN
                    SET NEW.uuid = UUID_TO_BIN(UUID(),1);
               END IF;
          END;
          '
    ));
    ";
    SET @sql_insert_devsql = REPLACE(@sql_insert_devsql, "\r\n", " ");

    -- Main
    OPEN currTableList;
    -- read the values from the first row that is available in the cursor
    FETCH FROM currTableList INTO uuidTable;

    WHILE (NOT RowNotFound) DO
      -- Store Trigger related statements as DevSQL
      CALL execStatement(@sql_insert_devsql, uuidTable);
      FETCH NEXT FROM currTableList INTO uuidTable;
    END WHILE;
    CLOSE currTableList;
END$$

CREATE TRIGGER `autouuid_dev_obj` BEFORE INSERT ON `dev_obj` FOR EACH ROW
BEGIN
    IF (NEW.uuid IS NULL) THEN
        SET NEW.uuid = UUID_TO_BIN(UUID(),1);
    END IF;
END$$
CREATE TRIGGER `autouuid_dev_component` BEFORE INSERT ON `dev_component` FOR EACH ROW
BEGIN
    IF (NEW.uuid IS NULL) THEN
        SET NEW.uuid = UUID_TO_BIN(UUID(),1);
    END IF;
END$$

CALL `modify_null_uuids`$$

DELIMITER ;

INSERT INTO `dev_obj` (`uuid`,`obj_type`,`obj_id`,`obj_desc`) VALUES
(UUID_TO_BIN('57839667-8949-11eb-a45f-001320cfc753',1),'','DevObject','Root object'),
(UUID_TO_BIN('90a8e2c7-8948-11eb-a45f-001320cfc753',1),'DevObject','DevScript','Script object')
;
INSERT INTO `dev_component` (`uuid`,`obj_type`,`obj_id`,`comp_obj_type`,`comp_obj_id`,`comp_type`,`comp_seq`) VALUES
(UUID_TO_BIN('57b2f8aa-8949-11eb-a45f-001320cfc753',1),'','DevObject','DevObject','DevScript','php',0),
(UUID_TO_BIN('57b30066-8949-11eb-a45f-001320cfc753',1),'','DevObject','DevObject','DevScript','js',0)
;
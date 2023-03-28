#IfMissingColumn documents name
ALTER TABLE `documents` ADD `name` varchar(255) DEFAULT NULL;
UPDATE `documents` SET `name`=IF(ISNULL(`couch_docid`), SUBSTRING_INDEX(`url`,"/",-1), `url`) WHERE IFNULL(`name`,"")="";
#EndIf

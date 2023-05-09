<?php

/* Common post-Version upgrade fix for uuid columns (offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

function ddlColUUID($strTable='', $strCol='uuid') {
    // Exclude uuid* tables used by standard project's registry mechanism
    if (strncmp($strTable, 'uuid', 4) == 0) {
        return "Skip $strTable.$strCol - part of uuid registry.";
    }

    $adb = $GLOBALS['adodb']['db'];

    // Add column if not exists
    $aaCols = $adb->metaColumns($strTable);
    if (!$aaCols) {
        return "Table $strTable missing.";
    }
    $aSql = [];
    if (isset($aaCols[strtoupper($strCol)])) {
        $adoFld = $aaCols[strtoupper($strCol)];
        if ((!($adoFld->not_null)) || (!($adoFld->has_default))) {
            // Fix current null values (if any)
            $aSql[] = sprintf(
                'UPDATE `%s` SET `%s`=UNHEX(SYS_GUID()) where `%s` IS NULL;',
                $strTable, $strCol, $strCol
            );
            // Now add default value set by built-in functions
            $aSql[] = sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `%s` binary(16) not null default UNHEX(SYS_GUID());',
                $strTable, $strCol
            );
        }
    } else {
        // Add missing column (probably from future patch)
        $aSql[] = sprintf(
            'ALTER TABLE `%s` ADD `%s` binary(16) not null default UNHEX(SYS_GUID());',
            $strTable, $strCol
        );
        // Add index for the new column
        $aSql[] = sprintf(
            'CREATE UNIQUE INDEX `%s` ON `%s` (`%s`);',
            $strCol, $strTable, $strCol
        );
    }

    return $aSql;    
}


require_once("load_globals.php");

// Add default used by this script
$defaults['sqlFull'] = "$osInstPath/sql/database.sql";

$_POST = array_merge($defaults, $_POST);

// Get engine version
$dbVer = $adb->serverInfo();
// 15-AUG-2022 - No known plans for UUID functions added in MySQL 8
if ((strcasecmp($dbVer->version, '8.0.0') < 0) || (stristr($dbVer, 'MariaDB') > 0)) {
    $chk = $adb->getArray("select UUID_TO_BIN(BIN_TO_UUID(0, false), false) uuid");
    if (!$chk) {
        die("DevObj installation check failed.".PHP_EOL);
    }
}

if  (!$modeUpdate) {
    printf('"update" flag missing. NO updates will be applied.%s', PHP_EOL);
}

// Scan database.sql for full uuid requirements
$fSql = new SplFileObject($_POST['sqlFull'], 'r');
$sqlQueue = [];
$strTable = '';
foreach ($fSql as $strSql) {
    $curMatch = '';
    if (preg_match('/^\s*CREATE\s+TABLE\s+`(.+)`/', $strSql, $curMatch)) {
        $strTable = $curMatch[1];
    } else if (preg_match('/^\s+`(.*uuid.*)`.+binary\(16\)/i', $strSql, $curMatch)) {
        $ddlSql = ddlColUUID($strTable, $curMatch[1]);
        if (is_array($ddlSql)) {
            $sqlQueue = array_merge($sqlQueue, $ddlSql);
        } else {
            printf('%s%s', $ddlSql, PHP_EOL);
        }
    }
}
$adbOK = false;
foreach ($sqlQueue as $strSql) {
    if ($modeUpdate) {
        $adbOK = $adb->execute($strSql);
    }
    
    echo $strSql.($adbOK ? " - OK":'').PHP_EOL;
}
echo "Done.".PHP_EOL;

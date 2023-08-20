<?php

/* Import LOINC codes in OpenEMR
 * 
 * Schedule this as a cronjob that can check a specified location for downloaded zip package.
 * Expect the zip filename as loinc_<version>.zip to extract version.
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once ('common/sites.php');

use Mdsupport\Mdpub\DevObj\DevDB;

$objSettings = getSettings();

// Validate input package
if (!file_exists($objSettings->zipIn)) {
    die ("Missing input package - {$objSettings->zipIn}".PHP_EOL);
}
// Extract files
$zipPart = pathinfo($objSettings->zipIn);
$dirExtracts = sprintf('%s%s%s%s', $zipPart['dirname'], DIRECTORY_SEPARATOR, $zipPart['filename'], DIRECTORY_SEPARATOR);
printf('Extracting files to %s .. ', $dirExtracts);
$importList = [
    "LoincTable/Loinc.csv",
];
$zip = new ZipArchive;
$res = $zip->open($objSettings->zipIn);
if ($res === TRUE) {
    $zip->extractTo( $dirExtracts, $importList );
    $zip->close();
    echo 'ok'.PHP_EOL;
} else {
    die ('failed'.PHP_EOL);
}

// Validate site
$osSitePath = sprintf('%s/sites/%s', $objSettings->osInstPath, $objSettings->site);
$configs = getOeSiteConfigs($osSitePath);
if (count($configs) !== 1) {
    exit("Exiting - SQL Configuration not available at {$osSitePath}.".PHP_EOL);
}

// Include standard libraries
$ignoreAuth = true; // no login required
$_GET['site'] = array_keys($configs)[0];

require_once("{$objSettings->osInstPath}/interface/globals.php");

// Get details about current setup
$objDB = new DevDB();

$aaCt = $objDB->execSql([
    'sql' => 'select ct_key, ct_id code_type from code_types',
    'where' => ['ct_key' => 'LOINC'],
    'return' => 'assoc'
]);

// Skipping check to see if codes table has been upgraded.

// Deactivate all current records
$objDB->execUpdates([
    'codes' => [
        'where' => [
            'code_type' => $aaCt['LOINC'],
        ],
        'set' => [
            'active' => 0,
        ],
    ],
]);

// Begin import
$ixRow = 0;
$rowHdr = true;
$fh = fopen("$dirExtracts{$importList[0]}", "r");
while (($csvCols = fgetcsv($fh)) !== FALSE) {
    $ixRow++;
    if ($rowHdr) {
        $rowHdr = false;
        $aCodeKeys = $csvCols;
        $aCodeKeysCount = count($csvCols);
        continue;
    }

    if ($aCodeKeysCount == count($csvCols)) {
        $codeRec = array_combine($aCodeKeys, $csvCols);
        // mdsupport - Uncertain about implication of this.
        // Remove this filter in future if SELECTs are affected.
        foreach ($codeRec as $ck => $cv) {
            if ($cv === '') {
                unset($codeRec[$ck]);
            }
        }
        // Transform import to database record
        $codeRec = [
            'code' => $codeRec['LOINC_NUM'],
            'code_type' => $aaCt['LOINC'],
            'code_text' => $codeRec['LONG_COMMON_NAME'],
            'code_text_short' => ($codeRec['SHORTNAME'] ?? ''),
            'active' => ($codeRec['STATUS'] == 'ACTIVE' ? 1 : 0),
            'source' => json_encode($codeRec),
        ];
    } else {
        printf(
            'Skipped row %s - found %s columns instead of %s.%s', 
            $ixRow, count($csvCols), $aCodeKeysCount, PHP_EOL
        );
        continue;
    }

    // Get recordset in preparation for insert/update
    $rsCodes = $objDB->execSql([
        'sql' => "select * from codes",
        'where' => [
            'code' => $codeRec['code'],
            'code_type' => $codeRec['code_type'],
            'source' => $codeRec['source'],
        ],
        'sfx' => 'order by id desc limit 1'
    ]);
    
    // If no matching source record exists, create a new one else reactivate
    // DevDB methods not migrated yet
    $adoDB = $GLOBALS['adodb']['db'];
    if ($rsCodes->recordCount() == 0) {
        $adoSql = $adoDB->getInsertSql($rsCodes, $codeRec);
    } else {
        $adoSql = $adoDB->getUpdateSql($rsCodes, $codeRec);
    }
    if (!empty($adoSql)) {
        $adoDB->execute($adoSql);
    }
    unset($csvCols);
}
fclose($fh);

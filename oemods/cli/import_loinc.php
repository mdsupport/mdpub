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

// Options specific to this script
$aScriptOptions = [
    [
        'arg' => ['zipIn', 'z'],
        'props' => [
            'default'     => getcwd().'/Loinc_latest.zip',
            'description' => 'Set the staged Loinc package archive - Loinc_<version>.zip'
        ]
    ],
];

$objSettings = getSettings($aScriptOptions);

// Require sqlconf.php
if (empty($objSettings->config)) {
    exit("Exiting - SQL Configuration not available.".PHP_EOL);
}

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

// Get details about current setup
$objDB = new DevDB();

$aaCt = $objDB->execSql([
    'sql' => 'select ct_key, ct_id code_type from code_types',
    'where' => ['ct_key' => 'LOINC'],
    'return' => 'assoc'
]);

// Skipping check to see if codes table has been upgraded.

// Deactivate all current records
$objDB->execSql([
    'sql' => 'update codes set active=0',
    'where' => [
        'code_type' => $aaCt['LOINC'],
        'active' => 1,
    ],
]);
printf('All currently active LOINC records were deactivated.%s', PHP_EOL);

// Gather ids of codes records for reactivation.
$aReActivateIds = [];

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
    if ($rsCodes->recordCount() == 0) {
        $adoSql = $objDB->adoMethod('getInsertSql', [$rsCodes, $codeRec]);
    } else {
        // Optimize - since only active column change is expected
        // $adoSql = $adoDB->getUpdateSql($rsCodes, $codeRec);
        $recCodes = $rsCodes->fetchRow();
        if (($codeRec['active'] ?? 0) == 1) {
            array_push($aReActivateIds, $recCodes['id']);
        }
    }
    if (!empty($adoSql)) {
        $objDB->adoMethod('execute', [$adoSql]);
    }
    unset($csvCols, $adoSql);
}
fclose($fh);

// ReActivate unchanged active records
$countReactivated = count($aReActivateIds);
if ($countReactivated > 0) {
    $aaReActivateIds = array_chunk($aReActivateIds, 1000);
    foreach ($aaReActivateIds as $aReActivateIds) {
        $objDB->execSql([
            'sql' => 'update codes set active=1',
            'where' => [
                'active' => 0,
            ],
            'sfx' => sprintf(' AND id IN (%s)', implode(',', $aReActivateIds))
        ]);
    }
    printf('%d previously deactivated LOINC records were re-activated.%s', $countReactivated, PHP_EOL);
}

printf('%s LOINC records were processed from %s.%s', $ixRow, $objSettings->zipIn, PHP_EOL);

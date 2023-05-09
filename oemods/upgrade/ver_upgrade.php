<?php

/* Version upgrade (offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

class clsUpgScript extends SplFileObject {
    private $upgInfo; // 1-verFrom, 2-verTo, 3-upgStage, 4-scriptExt;
    private $tags = [];
    
    public function __construct($fname) {
        parent::__construct($fname);
        $bname = $this->getBasename();
        // Pattern for file based scripts is [<ver>-to-]<ver>]_<fn>.<ext>
        $matchRegEx = '/^(.+)-to-(.+)_(.+)\.(.+)$/';
        if (preg_match($matchRegEx, $bname, $this->upgInfo) !== 1) {
            $matchRegEx = '/^(.+)_(.+)\.(.+)$/';
            if (preg_match($matchRegEx, $bname, $this->upgInfo) !== 1) {
                array_unshift($this->upgInfo, '');
            }
        }
    }
    
    public function isValid() {
        return $this->isReadable();
    }
    
    public function verFrom() {
        return $this->upgInfo[1];
    }
    
    public function verTo() {
        return $this->upgInfo[2];
    }
    
    public function upgStage() {
        return $this->upgInfo[3];
    }
    
    public function scriptExt() {
        return $this->upgInfo[4];
    }
    
    public function exec() {
        ;
    }

    /**
     * Get value(s) of tag(s) set earlier.
     * If a string is passed, tag value is returned.
     * if an array is passed, array of tags with matching keys is returned.
     * @param array $aaTags
     * @return boolean|array|mixed
     */
    public function getTags($aaTags=[]) {
        $tags = false;
        if (is_array($aaTags )) {
            $tags = array_intersect_key($this->tags, $aaTags);
        } else if (isset($this->tags[$aaTags])) {
            $tags = $this->tags[$aaTags];
        }
        return $tags;
    }
    /**
     * Use this to store random (key, value) pairs.
     * Caution - This is a convenience feature without much validation
     * @param (associative)array $aaTags
     */
    public function setTags($aaTags=[]) {
        if (is_array($aaTags)) {
            $this->tags = array_merge($this->tags, $aaTags);
        }
        return $this->getTags($aaTags);
    }
}

function fnSortObjVersions($objScr1, $objScr2) {
    $sortOrder = strcmp($objScr1->verFrom(), $objScr2->verFrom());
    if ($sortOrder == 0) {
        $sortOrder = ($objScr1->getTags('seq') > $objScr2->getTags('seq') ? 1 : -1);
    };
    return $sortOrder;
}

require_once("load_globals.php");

// Add defaults used exclusively by this script
$defaults_upg = [
    'from' => '5_0_0',
    'to' => '0_0_0',
    'debug' => false,
    'prompts' => true,
];
$_POST = array_merge($defaults_upg, $_POST);

/**
 * CAUTION - mdpub version uses filename based linked chain vs devdb based upgrade paths
 */
$registry = $adb->getAssoc("select concat('interface/forms/',directory), state from registry where state=1");

// Build directories to search for upgrade related files
$upgDirs = array_merge(
    ['sql'],
    array_keys($registry),
    ["$osModUpgDir"]
    );

/**
 * Build list of files for each steps
 * sql_version : sql files matching <src_ver>-to-<tgt_ver>_upgrade.sql (linked list)
 * patch : sql files matching /sql/<src_ver>-to-<tgt_ver>_patch.sql (linked list)
 * php : php script matching /cli/ver_setup_<tgt_ver>.php to be included followed by a call to verSetup(version)
 **/
$upgScr = [
    'upgrade' => [
        'ext' => 'sql',
        'stage' => 'version',
    ],
    'module' => [
        'ext' => 'sql',
        'stage' => 'version',
    ],
    'patch' => [
        'ext' => 'sql',
        'stage' => 'patch',
    ],
    'setup' => [
        'ext' => 'php',
        'stage' => 'final',
    ],
];

//Build upgrade action queue across folders
$upgQueue = [
    'version' => [],
    'patch' => [],
    'final' => [],
];

$upgTo = '';
$upgDirSeq = 0;
foreach ($upgDirs as $upgDir) {
    $upgDir = "$osInstPath/$upgDir";
    $upgFrom = $_POST['from'];
    foreach ($upgScr as $fileSfx => $fileAttr) {
        $fileExt = $fileAttr['ext'];
        $globPattern = sprintf('%s/*%s.%s', $upgDir, $fileSfx, $fileExt);
        if ($_POST['debug']) {
            printf('Scanning %s%s', $globPattern, PHP_EOL);
        }
        // glob returns files sorted alphabetically
        $aFiles = glob($globPattern);
        foreach ($aFiles as $scrFile) {
            $objScr = new clsUpgScript($scrFile);
            // Special case for first hit in secondary directories
            if (($upgDirSeq > 0) &&
                (strcmp($objScr->verFrom(), $upgFrom) > 0)) {
                $upgFrom = $objScr->verFrom();
            }
            // Unlike devobjects based linking, this check relies on string comparison
            // As a consequence, versions of forms+modules must align with main project.
            if (strcmp($objScr->verFrom(), $upgFrom) == 0) {
                $objScr->setTags(['seq' => $upgDirSeq]);
                array_push($upgQueue[$fileAttr['stage']], $objScr);
                $upgFrom = $objScr->verTo();
                if ($_POST['debug']) {
                    printf('Queued - %s%s', $objScr->getBasename(), PHP_EOL);
                }
            } elseif ($_POST['debug']) {
                printf('Skipping pre %s - %s%s', $upgFrom, $objScr->getBasename(), PHP_EOL);
            }
        }
    }
    if ($upgDirSeq == 0) {
        $upgTo = $objScr->verTo();
    }
    $upgDirSeq++;
}
if (count($upgQueue) == 0) {
    printf('No upgrade scripts match the selection. Exiting.');
    exit;
}

// Arrange objects by 'from' version
foreach ($upgQueue as $upgstage => $objScrs) {
    usort($objScrs, 'fnSortObjVersions');
    $upgQueue[$upgstage] = $objScrs;
}

$now = new DateTime();
$fOutName = sprintf(
    '%s/sites/%s/documents/ver_upgrade_%s.html',
    $osInstPath, $_POST['site'], $now->format('Ymd.His')
);

if ($_POST['debug'] !== true) {
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
    $STDIN = fopen('/dev/null', 'r');
    $STDOUT = fopen($fOutName, 'wb');
    $STDERR = fopen($fOutName.'.err', 'wb');
}

// Legacy setting when running as command line script
// need this for output to be readable when running as command line
$GLOBALS['force_simple_sql_upgrade'] = true;
// Special map for version field
$_POST['form_old_version'] = str_replace('_', '.', $_POST['from']);

// Checks if the server's PHP version is compatible with OpenEMR:
require_once($osInstPath . "/src/Common/Compatibility/Checker.php");
$response = OpenEMR\Common\Compatibility\Checker::checkPhpVersion();
if ($response !== true) {
    die(htmlspecialchars($response));
}

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ini_set('max_execution_time', '0');

$sessionAllowWrite = true;
$GLOBALS['connection_pooling_off'] = true; // force off database connection pooling

require_once("$osInstPath/library/sql_upgrade_fx.php");

use OpenEMR\Core\Header;
use OpenEMR\Services\Utils\SQLUpgradeService;

// Force logging off
$GLOBALS["enable_auditlog"] = 0;

session_write_close();

// Begin upgrade
if  (!$modeUpdate) {
    printf('"update" flag missing. NO updates will be applied.%s', PHP_EOL);
} else {
    $objBatchProcessor = new SQLUpgradeService();
}
foreach ($upgQueue as $upgstage => $objScrs) {
    foreach ($objScrs as $objScr) {
        if  (!$modeUpdate) {
            printf('%s - %s%s', $upgstage, $objScr->getPath(), PHP_EOL);
            continue;
        }
        
        $choice = 'y';
        if ($_POST['prompts']) {
            printf(
                "%sReady to %s from %s to %s (%s)%sPress Y-Yes, S-Skip or C-Cancel and Enter%s",
                PHP_EOL, $objScr->upgStage(), $objScr->verFrom(), $objScr->verTo(), $objScr->getPath(), PHP_EOL,
                PHP_EOL
                );
            $choice = strtolower(substr(readline(''),0,1));
        }
        if ($choice!=='y') {
            if ($choice='s') {
                continue;
            }
            die('Exiting....');
        }
        if ($objScr->scriptExt() == 'sql') {
            // UpgradeService requires fullpath split into two args
            $objBatchProcessor->upgradeFromSqlFile(
                $objScr->getBasename(),
                $objScr->getPath()
            );
        } elseif ($objScr->scriptExt() == 'php') {
            // Use functions with version specific names as multiple php scripts can get included.
            // Inline code execution is expected.
            require_once($objScr->getPathname());
        }
    }
}

// TBD - Split copied code into version relevent post-upgrade processing
// For now run it same as online
if  ($modeUpdate) {
    require_once ('ver_upgradefix.php');
}
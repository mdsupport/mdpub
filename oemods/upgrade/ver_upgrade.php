<?php

/* Version upgrade (offline)
 *
 * @package OpenEMR
 * @author MD Support <rod@sunsetsystems.com>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

function globTask($verFrom, $aaTaskCfg)
{
    global $osInstPath;
    $aaTask = [];
    $upgchk = sprintf('%s/%s%s*%s', $osInstPath, $aaTaskCfg['pfx'], $verFrom, $aaTaskCfg['sfx']);
    $aFiles = glob($upgchk);
    if (count($aFiles) !== 1) {
        return false;
    }

    $aaTask['file'] = $aFiles[0];
    $matchRegEx = sprintf('/^.+-to-(.+?)%s$/', str_replace('.', '\.', $aaTaskCfg['sfx']));
    if (preg_match($matchRegEx, $aFiles[0], $verFrom) == 1) {
        $aaTask['nextVer'] = $verFrom[1];
    }
    $objFile = new SplFileObject($aFiles[0]);
    $aaTask['ext'] = $objFile->getExtension();
    // Check if any local supplements are present
    if (!empty($_POST['local'])) {
        $local = sprintf('%s/%s_%s',
            __DIR__,
            $_POST['local'],
            $objFile->getBasename(),
            );
        if (file_exists($local)) {
            $aaTask['local'] = $local;
        }
    }
    return $aaTask;
}

function queueTask(&$upgQueue, $aaTask)
{
    if (isset($aaTask['local'])) {
        $local = $aaTask['local'];
        unset($aaTask['local']);
    }
    $upgQueue[] = $aaTask;
    if (isset($local)) {
        $aaTask['file'] = $local;
        $aaTask['desc'] .= '(local)';
        queueTask($upgQueue, $aaTask);
    }
}

// Installation path prefix
$osInstPath = dirname(__FILE__, 6);
$osModUpgDir = str_replace($osInstPath, '', __DIR__);

if (php_sapi_name() !== 'cli') {
    // Assume someone, somehow tried to access this script using browser
    $hdrLoc = str_replace($_SERVER['DOCUMENT_ROOT'], '', $osInstPath) . '/sql_upgrade.php';
    header("Location: $hdrLoc"); /* Redirect browser */
    exit;
}

// Check arguments
$scr = $argv[0];
array_shift($argv);
// TBD - Provide ability to limit upgrade to specific version
$_POST = [
    'site' => false,
    'from' => '0_0_0',
    'to' => '0_0_0',
    'local' => false,
    'update' => false,
    'debug' => true,
];
foreach ($argv as $arg) {
    $arg = explode('=', $arg);
    $_POST[$arg[0]] = (count($arg)>1 ? $arg[1] : '');
}
// Need site and a valid file(s) in sql directory to proceed
$upgQueue = [];

// Build list of version and patch sql files
// Patches begin from last version and end at milestones/commit-ids
// Begin with version specified by 'from' parameter
$verFrom = $_POST['from'];
/**
* Build list of files for each steps
* sql_version : sql files matching /sql/*-to-*_upgrade.sql (linked list)
* patch : sql files matching /sql/patch_*-to-*_upgrade.sql (linked list)
* php : php script matching /cli/ver_setup_*.php to be included followed by a call to verSetup(version)
**/
$stepCfg = [
    'version' => [
        'pfx' => 'sql/',
        'sfx' => '_upgrade.sql'
    ],
    'patch' => [
        'pfx' => 'sql/',
        'sfx' => '_patch.sql'
    ],
    'setup' => [
        'pfx' => "$osModUpgDir/ver_setup_",
        'sfx' => '_upgrade.php'
    ],
];
while ($nextTask = globTask($verFrom, $stepCfg['version'])) {
    $nextTask['desc'] = sprintf('%s->%s', $verFrom, $nextTask['nextVer']);
    queueTask($upgQueue, $nextTask);
    $verFrom = $nextTask['nextVer'];
    $nextTask = globTask($verFrom, $stepCfg['setup']);
    if ($nextTask) {
        $nextTask['desc'] = sprintf('Setup %s', $verFrom);
        queueTask($upgQueue, $nextTask);
    }
}
while ($nextTask = globTask($verFrom, $stepCfg['patch'])) {
    $nextTask['desc'] = sprintf('%s->%s', $verFrom, $nextTask['nextVer']);
    $verFrom = $nextTask['nextVer'];
    queueTask($upgQueue, $nextTask);
}

if ((!($_POST['site'])) || (count($upgQueue) == 0)) {
    printf('php %s site=x from=n_n_n [update=true] [local=xx] [to=n_n_n]%s', $scr, PHP_EOL);
    die();
}
if ((!($_POST['update'])) || ($_POST['update'] != 'true')) {
    printf('"update" flag missing. NO updates applied from%s', PHP_EOL);
    foreach ($upgQueue as $task) {
        printf('%s%sFile - %s%s', $task['desc'], PHP_EOL, $task['file'], PHP_EOL);
    }
    exit;
}

$now = new DateTime();
$fOutName = sprintf(
    '%s/sites/%s/documents/ver_upgrade_%s.html',
    $osInstPath,
    $_POST['site'],
    $now->format('Ymd.His')
);

if ($_POST['debug'] !== true) {
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
    $STDIN = fopen('/dev/null', 'r');
    $STDOUT = fopen($fOutName, 'wb');
    $STDERR = fopen($fOutName.'.err', 'wb');
}

// Setup stuff to avoid errors thrown by globals.php ** Fix This **
$_GET['site'] = $_POST['site'];
// Legacy setting when running as command line script
// need this for output to be readable when running as command line
$GLOBALS['force_simple_sql_upgrade'] = true;
// Special map for version field
$_POST['form_old_version'] = str_replace('_', '.', $_POST['from']);
$GLOBALS['ongoing_sql_upgrade'] = true;

// Checks if the server's PHP version is compatible with OpenEMR:
require_once($osInstPath . "/src/Common/Compatibility/Checker.php");
$response = OpenEMR\Common\Compatibility\Checker::checkPhpVersion();
if ($response !== true) {
    die(htmlspecialchars($response));
}

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ini_set('max_execution_time', '0');

$ignoreAuth = true; // no login required
$sessionAllowWrite = true;
$GLOBALS['connection_pooling_off'] = true; // force off database connection pooling

require_once("$osInstPath/interface/globals.php");
require_once("$osInstPath/library/sql_upgrade_fx.php");

use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Core\Header;
use OpenEMR\Services\Utils\SQLUpgradeService;
use OpenEMR\Services\VersionService;

// Force logging off
$GLOBALS["enable_auditlog"] = 0;

session_write_close();

// Begin upgrade
$objBatchProcessor = new SQLUpgradeService();
foreach ($upgQueue as $task) {
    readline("Press `Enter` to process {$task['file']}");
    if ($task['ext'] == 'sql') {
        // UpgradeService requires fullpath split into two args
        $objBatchProcessor->upgradeFromSqlFile(
            basename($task['file']),
            dirname($task['file'])
        );
    } elseif ($task['ext'] == 'php') {
        
    }
}

// TBD - Split copied code into version relevent post-upgrade processing
// For now run it same as online
set_include_path($osInstPath);
require_once ('post_upgrade_all.php');

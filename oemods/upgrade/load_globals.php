<?php

/* Common validation for upgrade cli utilities
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Installation path prefix
$osInstPath = dirname(__FILE__, 6);
set_include_path($osInstPath);
$osModUpgDir = str_replace("$osInstPath/", '', __DIR__);

if (php_sapi_name() !== 'cli') {
    // Assume someone, somehow tried to access this script using browser
    $hdrLoc = str_replace($_SERVER['DOCUMENT_ROOT'], '', $osInstPath) . '/sql_upgrade.php';
    header("Location: $hdrLoc"); /* Redirect browser */
    exit;
}

// If invoked as require() from other scripts, continue using same variables
$defaults = [
    'site' => '',
    'update' => false,
    'proc' => $argv[0]
];
$_POST = $_POST ?? $defaults;
foreach ($argv as $arg) {
    $arg = explode('=', $arg);
    if (count($arg) > 1) {
        if ($arg[1] == 'true') {
            $arg[1] = true;
        } elseif ($arg[1] == 'false') {
            $arg[1] = false;
        }
    }
    $_POST[$arg[0]] = ($arg[1] ?? '');
}
$_GET = $_POST = array_merge($defaults, $_POST);
if (empty($_POST['site'])) {
    printf(
        '%sAt least "site=xxx" must be specified in the command line.%s',
        PHP_EOL, PHP_EOL
    );
    die();
}

$modeUpdate = ((isset($_POST['update'])) && ($_POST['update']));

// Include standard libraries
$ignoreAuth = true; // no login required
// Required to ignore globals code issue
$GLOBALS['ongoing_sql_upgrade'] = true;

require_once("$osInstPath/interface/globals.php");

// Use adodb methods whenever possible
$adb = $GLOBALS['adodb']['db'];

<?php

/* Common post-Version upgrade (offline)
 *
 * @package OpenEMR
 * @author MD Support <rod@sunsetsystems.com>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

if (php_sapi_name() !== 'cli') {
    header("Location: sql_upgrade.php"); /* Redirect browser */
    exit;
}

// Installation path prefix
$osInstPath = dirname(__FILE__, 6);

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
use OpenEMR\Services\VersionService;

// Force logging off
$GLOBALS["enable_auditlog"] = 0;

session_write_close();

echo "<br /><p class='text-success'>Updating UUIDs (this could take some time)<br />\n";

$updateUuidLog = UuidRegistry::populateAllMissingUuids();
if (!empty($updateUuidLog)) {
    echo "Updated UUIDs: " . text($updateUuidLog) . "</p><br />\n";
} else {
    echo "Did not need to update or add any new UUIDs</p><br />\n";
}

echo "<p class='text-success'>" . xlt("Updating global configuration defaults") . "..." . "</p><br />\n";
$skipGlobalEvent = true; //use in globals.inc.php script to skip event stuff
require_once("library/globals.inc.php");
foreach ($GLOBALS_METADATA as $grpname => $grparr) {
    foreach ($grparr as $fldid => $fldarr) {
        list($fldname, $fldtype, $flddef, $flddesc) = $fldarr;
        if (is_array($fldtype) || (substr($fldtype, 0, 2) !== 'm_')) {
            $row = sqlQuery("SELECT count(*) AS count FROM globals WHERE gl_name = '$fldid'");
            if (empty($row['count'])) {
                sqlStatement("INSERT INTO globals ( gl_name, gl_index, gl_value ) " .
                    "VALUES ( '$fldid', '0', '$flddef' )");
            }
        }
    }
}

echo "<p class='text-success'>" . xlt("Updating Access Controls") . "..." . "</p><br />\n";
require("acl_upgrade.php");
echo "<br />\n";

$versionService = new VersionService();
$currentVersion = $versionService->fetch();
$desiredVersion = $currentVersion;
$desiredVersion['v_database'] = $v_database;
$desiredVersion['v_tag'] = $v_tag;
$desiredVersion['v_realpatch'] = $v_realpatch;
$desiredVersion['v_patch'] = $v_patch;
$desiredVersion['v_minor'] = $v_minor;
$desiredVersion['v_major'] = $v_major;

$canRealPatchBeApplied = $versionService->canRealPatchBeApplied($desiredVersion);
$line = "Updating version indicators";

if ($canRealPatchBeApplied) {
    $line = $line . ". " . xlt("Patch was also installed, updating version patch indicator");
}

echo "<p class='text-success'>" . $line . "...</p><br />\n";
$versionService->update($desiredVersion);

echo "<p><p class='text-success'>" . xlt("Database and Access Control upgrade finished.") . "</p></p>\n";
echo "</div>\n";

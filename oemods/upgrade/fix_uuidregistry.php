<?php

/* Common post-Version upgrade fix for uuid registry (offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("load_globals.php");

use OpenEMR\Common\Uuid\UuidRegistry;

echo "Updating UUIDs (this could take some time)".PHP_EOL;

if (empty($GLOBALS['rest_fhir_api'])) {
    echo "Did not update UUID registry - FHIR api not enabled.".PHP_EOL;
} else {
    $updateUuidLog = UuidRegistry::populateAllMissingUuids();
    if (!empty($updateUuidLog)) {
        echo "Updated UUIDs: " . text($updateUuidLog) . PHP_EOL;
    } else {
        echo "Did not need add any new UUIDs".PHP_EOL;
    }}

echo "Done.".PHP_EOL;

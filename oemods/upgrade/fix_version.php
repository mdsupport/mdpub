<?php

/* Common post-Version upgrade fix for version display (offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("load_globals.php");

use OpenEMR\Services\VersionService;

$versionService = new VersionService();
$aaVersion = $versionService->fetch();

printf(
    'Current version db=%s, tag=%s, realpatch=%s, patch=%s, major=%s, minor=%s%s',
    $v_database, $v_tag, $v_realpatch, $v_patch, $v_major, $v_minor, PHP_EOL
);

// Skip for now
if (false) {
    echo "Updating displayed version info.".PHP_EOL;
    $aaVersion['v_database'] = $v_database;
    $aaVersion['v_tag'] = $v_tag;
    $aaVersion['v_realpatch'] = $v_realpatch;
    $aaVersion['v_patch'] = $v_patch;
    $aaVersion['v_minor'] = $v_minor;
    $aaVersion['v_major'] = $v_major;
    
    $canRealPatchBeApplied = $versionService->canRealPatchBeApplied($aaVersion);
    
    if ($canRealPatchBeApplied) {
        echo "Adding patch indicator.".PHP_EOL;
    }
    
    $versionService->update($aaVersion);
}

echo "Done.".PHP_EOL;

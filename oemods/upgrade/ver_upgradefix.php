<?php

/* Common post-Version upgrade fixes (offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("load_globals.php");

// Include standard libraries/classes
require_once("$osInstPath/vendor/autoload.php");

// Fix 1 - Standard acl upgrade (needed for all versions?)
require_once ('acl_upgrade.php');
printf('%s', PHP_EOL);

// Fix 2 - Add new globals if any
require_once ('fix_globals.php');

// Fix 3 - Update version
require_once ('fix_version.php');

// Fix 4 - Change standard default settings for all uuid columns
require_once ('fix_uuid_cols.php');

// Fix 5 - uuid registry stuff if fhir is active
require_once ('fix_uuidregistry.php');
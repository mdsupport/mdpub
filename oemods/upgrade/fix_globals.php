<?php

/* Common post-Version upgrade fix for globals(offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("load_globals.php");

echo "Updating global configuration defaults".PHP_EOL;
$skipGlobalEvent = true; //use in globals.inc.php script to skip event stuff
require_once("library/globals.inc.php");

foreach ($GLOBALS_METADATA as $grpname => $grparr) {
    foreach ($grparr as $fldid => $fldarr) {
        list($fldname, $fldtype, $flddef, $flddesc) = $fldarr;
        if (is_array($fldtype) || (substr($fldtype, 0, 2) !== 'm_')) {
            $row = sqlQuery("SELECT count(*) AS count FROM globals WHERE gl_name=?", [$fldid]);
            if (empty($row['count'])) {
                sqlStatement(
                    "INSERT INTO globals(gl_name, gl_index, gl_value) VALUES (?,?,?)",
                    [$fldid, 0, $flddef]
                );
            }
        }
    }
}

echo "Done.".PHP_EOL;

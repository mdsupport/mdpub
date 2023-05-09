<?php

/* Common post-Version upgrade fix for resetting admin password(offline)
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("load_globals.php");

use Mdsupport\Mdpub\DevObj\DevDB;

echo "Resetting password for 'admin'.".PHP_EOL;

$objDB = new DevDB();

// Use DevDB update transaction
$objDB->execUpdates([
    'users_secure' => [
        'set' => [
            'password' => '$2a$05$MKtnxYsfFPlb2mOW7Qzq2Oz61S26s5E80Yd60lKdX4Wy3PBdEufNu',
        ],
        'where' => [
            'username' => 'admin'
        ],
    ],
]);

echo "Done.".PHP_EOL;

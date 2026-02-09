<?php
/**
 * Shell to maintain emr session authenticated externally
 * Adapter config expected like following array
 *   [
 *     'adapter' => 'GitHub',
 *     'keys' => [
 *         'key' => 'Ov23liaNvlxBQWonRcf8', // Required: your GitHub application id
 *         'secret' => '4f3fc29e0bb4937038e4baca899ae60f9c71cc6d'  // Required: your GitHub application secret
 *     ],
 *     'enabled' => true,
 *     'force_login' => true,
 *     'fa' => 'github bg-dark text-light',
 *     'type' => 'OAuth2',
 *  ]
 * 
 * Copyright (C) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 *
 * @package   Mdhl7
 * @author    MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Get Autoloader
$comp_loader = null;
$vendor_parentOS = dirname(__FILE__, 2);
while ($vendor_parentOS !== dirname($vendor_parentOS)) {
    if (file_exists("$vendor_parentOS/vendor/autoload.php")) {
        $comp_loader = require_once("$vendor_parentOS/vendor/autoload.php");
        break;
    }
    $vendor_parentOS = dirname($vendor_parentOS);
}
require_once("$vendor_parentOS/vendor/adodb/adodb-php/adodb.inc.php");

use Hybridauth\Exception\Exception;
use Hybridauth\HttpClient\Util as HAUtil;
use Mdsupport\Mdpub\DevObj\DevFile;
use Mdsupport\Mdpub\DevObj\DevSession;

$mdSession = new DevSession();
// Session must have adapter chosend by user
$haAdapter = ($mdSession->hauthAdapter ?? 'GitHub');

// TBD : Use DevConfig based on DevObject 
$objSelf = new DevFile();
// Get my adapter config - should be only one record
$objCompRecord = $objSelf->getComponents(['comp_type' => $haAdapter])[0];
$haAdapterConfig = json_decode($objCompRecord['comp_json'], true);

// Insert standard callback to self
$haAdapterConfig['callback'] = HAUtil::getCurrentUrl();

try {
    // Instantiate GitHub's adapter directly
    $adapterClass = "Hybridauth\\Provider\\$haAdapter";
    $adapter = new $adapterClass($haAdapterConfig);

    // Attempt to authenticate the user with GitHub
    $adapter->authenticate();

    // Returns a boolean of whether the user is connected with GitHub
    $isConnected = $adapter->isConnected();
 
    if ($isConnected) {
        // Retrieve the user's profile
        $userProfile = $adapter->getUserProfile();
    
        // Inspect profile's public attributes
        $mdSession->hauthuserProfile = $userProfile;
        $mdSession->hauthuserEmail = $userProfile->email;
        $mdSession->hauthuserEmailVerified = $userProfile->emailVerified;
        
        // After processing, you can manually redirect the user to a final destination page
        Hybridauth\HttpClient\Util::redirect('index.php');
    } else {
        // Disconnect the adapter (log out)
        $adapter->disconnect();
    }
}
catch(\Exception $e){
    print_r($haAdapterConfig);
    echo 'Oops, we ran into an issue! ' . $e->getMessage();
}
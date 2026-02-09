<?php
/**
 * Shell to maintain application session authenticated externally 
 *
 * Copyright (C) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 *
 * @package   mdpub
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

$vendor_parentUrl = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', $vendor_parentOS);

use DebugBar\StandardDebugBar;
use DebugBar\DataCollector\MessagesCollector;
use Mdsupport\Mdpub\Htm\HtmPageAssets;
use Mdsupport\Mdpub\DevObj\DevSession;
use Mdsupport\Mdpub\DevObj\DevDB;
use Hybridauth\HttpClient\Util as HAUtil;

$objScript = new HtmPageAssets([
    'components' => [
        // Test components
        'meta.htm',
        'common*.*',
        "$vendor_parentOS/vendor/adodb/adodb-php/adodb.inc.php",
    ]
]);

require_once("$vendor_parentOS/vendor/adodb/adodb-php/adodb.inc.php");
$objScript->insertPHP();

$debugbar = new StandardDebugBar();
$debugbarRenderer = $debugbar->getJavascriptRenderer("$vendor_parentUrl/vendor/php-debugbar/php-debugbar/resources/");

$mdSession = new DevSession();

if (($_GET['session'] ?? '') == 'new') {
    $mdSession->end();
    $mdSession->start();
}

$debugbar->addCollector(new MessagesCollector('Flow'));
//$debugbar['$_SESSION']->addMessage($_SESSION, 'Init');

if (!$mdSession->has('hauth')) {
    $mdSession->hauthAdapter = "GitHub";
    $mdSession->hauth = HAUtil::getCurrentUrl(); //"$vendor_parentUrl/Social/hauth.php";
    $debugbar['Flow']->addMessage($mdSession->hauth, 'Redirect');
    $debugbar->stackData();
    // var_dump($_SESSION);
    HAUtil::redirect("$vendor_parentUrl/Social/hauth.php");
}

$debugbar['Flow']->addMessage($_SESSION, 'After hauth');
$debugbar['Flow']->addMessage($mdSession->hauthuser, 'Redirect');

// Check email matches in multiple tables
$effectiveEmail = $mdSession->hauthuserEmailVerified;
if ($effectiveEmail === true) {
    $effectiveEmail = $mdSession->hauthuserEmail;
}

$ifDB = new DevDB();
$accountMatches = $ifDB->execSql([
    'sql' =>
    'SELECT * FROM (
            SELECT 0 as seq, "users" AS source, id AS id, email AS email, CONCAT(lname,", ",fname) AS name
                FROM users u 
                WHERE u.`active` = 1
            UNION
            SELECT 1 as seq, "patient_data" AS source, pid AS id, email AS email, CONCAT(lname,", ",fname) AS name 
                FROM patient_data p 
                WHERE p.deceased_date IS NULL
        ) as emails',
    'where' => [ 'LOWER(emails.email)' => strtolower($effectiveEmail) ],
    'sfx' => 'ORDER BY emails.seq',
    'return' => 'array',
]);
$debugbar['messages']->addMessage($accountMatches); // 'email matched = '.count($accountMatches));
$msgH4 = "Email match failed.";
if (isset($accountMatches[0]['name'])) {
    $msgH4 = "Hello {$accountMatches[0]['name']}";
}
?>
<html>
    <head>
        <?php $objScript->insertCSS(); ?>
    
        <?php echo $debugbarRenderer->renderHead() ?>
    </head>
    <body>
      <h4><?php echo $msgH4 ?></h4>
    
    <?php $objScript->insertJS(); ?>
    <?php echo $debugbarRenderer->render(); ?>
    </body>
</html>

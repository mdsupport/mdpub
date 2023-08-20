<?php

/* OpenEMR sites
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

function getSettings() {
    // Composer
    $ix = 1;
    while (true) {
        // Installation path prefix
        $osInstPath = dirname(__FILE__, $ix++);
        if (file_exists("$osInstPath/vendor/autoload.php")) {
            break;
        }
    }
    
    require_once "$osInstPath/vendor/autoload.php";
    
    // Use wordpress cli tools to handle cli argv
    $strict = in_array('--strict', $_SERVER['argv']);
    $arguments = new \cli\Arguments(compact('strict'));
    
    $arguments->addFlag(array('verbose', 'v'), 'Turn on verbose output');
    $arguments->addFlag('version', 'Display the version');
    $arguments->addFlag(array('quiet', 'q'), 'Disable all output');
    $arguments->addFlag(array('help', 'h'), 'Show this help screen');
    
    $arguments->addOption(array('zipIn', 'z'), array(
        'default'     => getcwd().'/Loinc_2.75.zip',
        'description' => 'Set the staged Loinc package archive - Loinc_<version>.zip'));
    $arguments->addOption(array('site', 's'), array(
        'default'     => 'default',
        'description' => 'Set the EMR site'));
    
    $arguments->parse();
    if ($arguments['help']) {
        echo $arguments->getHelpScreen();
        echo "\n\n";
    }
    
    $objSettings = json_decode($arguments->asJSON());
    $objSettings->osInstPath = $osInstPath;
    return $objSettings;
    
}

function getOeSiteConfigs($osSitePath) {
    $osConfigs = glob("$osSitePath/sqlconf.php");
    $siteConfigs = [];
    foreach ($osConfigs as $config) {
        preg_match('/\/sites\/(.+?)\/sqlconf\.php/', $config, $site);
        $siteConfigs[$site[1]] = $config;
    }
    return $siteConfigs;
}

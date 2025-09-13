<?php

/* OpenEMR sites
 *
 * @package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

function getSettings($aOptions) {
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
    
    $arguments->addOption(array('site', 's'), array(
        'default'     => 'default',
        'description' => 'Set the EMR site'));
    $arguments->addOption(
        ['config', 'c'],
        [
            'default'     => "$osInstPath/sites/{$arguments->getArguments()['site']}/sqlconf.php",
            'description' => 'sqlconf.php'
        ]
        );
    
    // Incorporate script specific options
    foreach ($aOptions as $argOption) {
        $arguments->addOption($argOption['arg'], $argOption['props']);
    }

    $arguments->parse();
    if ($arguments['help']) {
        echo $arguments->getHelpScreen();
        echo "\n\n";
    }
    $objSettings = json_decode($arguments->asJSON());

    // Check sql config
    if (!file_exists($objSettings->config)) {
        unset($objSettings->config);
    }
    $objSettings->osInstPath = $osInstPath;
    
    return $objSettings;
    
}

function getOeSiteConfigs($sqlConfig) {
    $osConfigs = glob($sqlConfig);
    $siteConfigs = [];
    foreach ($osConfigs as $config) {
        preg_match('/\/sites\/(.+?)\/sqlconf\.php/', $config, $site);
        $siteConfigs[$site[1]] = $config;
    }
    return $siteConfigs;
}

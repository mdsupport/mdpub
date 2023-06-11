<?php
/**
 * Extend DevObjects functionality to Files.
 *
 * Copyright (C) 2015-2023 MD Support <mdsupport@users.sourceforge.net>
*
* @package   OpenEMR
* @author    MD Support <mdsupport@users.sourceforge.net>
* @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\DevObj;

class DevFile extends DevObject
{
    private $ws = [];
    private $DebugMessagesStore = false;
    
    /**
     * File object constructor
     * @param string $strFile - Filename or URL
     * @param string $obj_version - Version to apply for the object
     */
    function __construct($strFile = null, $obj_version = '')
    {
        // Store properties
        $this->ws['obj_id'] = (empty($strFile) ? $this->makeObjId($strFile) : $strFile);
        $this->ws['obj_version'] = $obj_version;

        parent::__construct($this->ws);
        // Check if debugger is enabled
        $strDebugger = $this->hasDebugger();
        if ($strDebugger) {
            require_once ($GLOBALS['fileroot'].$strDebugger);
            $this->DebugMessagesStore = getDebugMessagesStore();
        }
    }

    /**
     * Utility function to convert __FILE__ style input to string ready to be used as obj_id
     * This includes converting paths in windows environment to unix
     * @param string $str_file - Handles strings as full os file path, url 
     * @return string ready for use as obj_id
     */
    private function makeObjId($str_file)
    {
        // Set default as a call to globals.php(level 0) by main script(level 1)
        if (empty($str_file)) {
            $call_stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $str_file = $call_stack[count($call_stack)-1]['file'];
        }
        $ws = [];
        $ws['osmount'] = dirname(__DIR__, 5);
        $ws['urlpfx'] = str_replace(dirname(__DIR__, 6), '', $ws['osmount']);
        $ws['urlpfx'] = str_replace(DIRECTORY_SEPARATOR, "/", $ws['urlpfx']);
        $this->ws = array_merge($this->ws, $ws);
        // Strip os or url to application root
        foreach ($ws as $pfxtype => $pfx) {
            $pfxlen = strlen($pfx);
            if (substr($str_file, 0, $pfxlen) == $pfx) {
                $str_file = substr($str_file, $pfxlen);
            }
        }
        // Replace with PHP_OS_FAMILY == Windows in 7.2
        if (stripos(PHP_OS, 'WIN') === 0) {
            $str_file = str_replace(DIRECTORY_SEPARATOR, "/", $str_file);
        }

        return $str_file;
    }

    /**
     * Use of inactive DevFile objects is not supported.
     * 
     * {@inheritDoc}
     * @see \Mdsupport\Mdpub\DevObj\DevObject::isActive()
     */
    public function isActive()
    {
        return parent::isActive();
    }

/**
 * By default this provides access to commonly used attributes of the files.
 * {@inheritDoc}
 * @see \Mdsupport\Mdpub\DevObj\DevObject::get()
 * @param boolean $allCols - Return all columns
 */
    public function get($allCols = false)
    {
        if ($this->strUuid()) {
            $recFull = parent::get();
        } else {
            $recFull = $this->ws;
        }
        if ($allCols) return $recFull;

        // Commonly needed attributes
        $cols = ['obj_id', 'obj_version', 'obj_desc'];
        $aaReturn = array_intersect_key($recFull, array_flip($cols));
        return $aaReturn;
    }

    public function getResources($aaFilter = [])
    {
        if (empty($aaFilter['comp_type'])) {
            $aaFilter['comp_type'] = 'resource';
        }
        if (!property_exists($this, 'resources')) {
            $this->resources = $this->getComponents([
                'comp_type' => 'resource'
            ]);
        }

        $aaResources = [];
        if ((empty($this->resources)) || (!($this->resources))) return $aaResources;
        foreach ($this->resources as $rowNum => $rowCols) {
            $matched = true;
            foreach ($aaFilter as $devCompCol => $devCompFilter) {
                if ($devCompCol == 'comp_json') {
                    $devCompFilter = json_decode($devCompFilter, true);
                    $rowColsJson = json_decode($rowCols[$devCompCol], true);
                    foreach ($devCompFilter as $jsonKey => $jsonValue) {
                        $matched = $matched && ($resDetails[$jsonKey]==$rowColsJson[$jsonKey]);
                    }
                } else {
                    $matched = $matched && ($devCompFilter==$rowCols[$devCompCol]);
                }
            }
            if ($matched) {
                $aaResources[$rowNum] = (empty($aaFilter['value']) ? $rowCols : $resDetails[$aaFilter['value']]);
            }
        }

        return $aaResources;
    }

    /**
     * Loads component scripts for the file
     */
    public function loadDevObjs($aaFilter = []) {
        $res = $this->getResources($aaFilter);
        if (!$res) return false;

        // TBD : Refine specialized actions
        foreach ($res as $objDevComponent) {
            // Get component details
            $compProperty = json_decode($objDevComponent['comp_json']);
            if ($compProperty->type == 'require_once') {
                // TBD : Implement absPath and relPath methods
                require_once ($GLOBALS['fileroot'].$objDevComponent['comp_obj_id']);
                if (property_exists($compProperty, 'fn')) {
                    $retPrint .= call_user_func($compProperty->fn, $this);
                }
            }
        }

        return $retPrint;
    }

    /**
     * Checks and optionally performs redirection steps as setup in database
     * Currently setup to limit this for php files and requires special setup of related components.
     * Two scenarios checked here are :
     * inject code - by capturing output of scripts standard code and inserting additional functions using fnInject
     * redirect - Soft redirect to another file with same request parameters using fnRedirect
     * 
     * @param boolean $testMode - If true, perform redirection per configuration in database
     */
    public function redirectDevFile($testMode=true)
    {
        // TBD - Confirm the object exists
        if (($this->isActive()) && (strtolower(substr($this->ws['obj_id'],-4)) == '.php')) {
            $thisFile = $this->get(true);
            $actionType = 'fnInject';
            // For php files always check if there is a 'inject' or 'redirect' component
            $objDevComp = $this->getResources([
                'comp_obj_type' => $thisFile['obj_type'],
                'comp_obj_id' => $thisFile['obj_id'],
                'comp_obj_version'=> $thisFile['obj_version'],
                'comp_json' => ['fn' => 'fnInject']
            ]);
            if (!objDevComp) {
                $actionType = 'fnRedirect';
                $objDevComp = $this->getResources([
                    'comp_obj_type' => $thisFile['obj_type'],
                    'comp_json' => ['fn' => 'fnRedirect']
                ]);
            }
        }
        if ((is_object(objDevComp)) && (!$testMode)) {
            $this->loadDevObjs($objDevComp->get());
        }
        return $objDevComp;
        // Remove functionality from zsfx.interface.globals.php
    }

    /**
     * Automatically initialize debugbar.
     */
    private function hasDebugger()
    {
        // Individual files need to have setting : debug=true.
        // Get component for DevObject DevFile of type 'setting'
        $recDebug = $this->objDevDB->execSql([
            'sql' => '
                SELECT scr.obj_id, cls.comp_obj_id debugger
                FROM dev_component cls
                INNER JOIN dev_component scr ON scr.obj_type=cls.obj_id AND scr.obj_version=cls.obj_version
                INNER JOIN dev_obj obj ON scr.obj_type=obj.obj_type AND scr.obj_id=obj.obj_id AND scr.obj_version=obj.obj_version
            ',
            'where' => [
                "cls.obj_type" => 'DevObject',
                "cls.comp_type" => 'setting',
                "JSON_EXTRACT(cls.comp_json, '$.type')" => "debug",
                "scr.comp_type" => 'setting',
                "JSON_EXTRACT(scr.comp_json, '$.debug')" => "true",
                "obj.active" => 1,
            ],
            'sfx' => '
                AND scr.obj_type=scr.comp_obj_type
                AND scr.obj_id=scr.comp_obj_id
                AND scr.obj_version=scr.comp_obj_version
            ',
            'return' => 'array',
        ]);
        if (count($recDebug) > 0) {
            return $recDebug[0]['debugger'];
        }
        return false;
    }

    public function getDebugMessagesStore()
    {
        return $this->DebugMessagesStore;
    }

    /**
     * Generic debug option without causing any exceptions
     * @param object $varMsg
     */
    public function debug($varMsg)
    {
        if ($this->DebugMessagesStore) {
            $this->DebugMessagesStore->add($varMsg);
        }
    }

    /**
     * Much of DevFile functionality is dependent on auto_append_file feature.
     * If a script terminates with 'exit()', the specified file will not be appened.
     * This static function provides the parameter for require_once if the append file is to be included manually.
     */
    public static function getDefaultAppendFile($aaWhere=[]) {
        // Static Function has limited access to other objects/methods
        $objDB = new DevDB();
        // Get component for DevObject DevFile of type 'setting'
        $thisClass = get_called_class();
        if ($pos = strrpos($thisClass, '\\')) {
            $thisClass = substr($thisClass, $pos + 1);
        }

        $aaWhere = array_merge([
            'obj_type' => 'DevObject',
            'obj_id' => $thisClass,
            'obj_version' => '',
            'comp_obj_type' => $thisClass,
            'comp_obj_version' => '',
            'comp_type' => 'setting',
            "JSON_EXTRACT(comp_json, '$.type')" => 'auto_append_file',
        ],
            $aaWhere
        );
        $aaSettings = $objDB->execSql([
            'sql' => 'SELECT comp_obj_id FROM dev_component',
            'where' => $aaWhere,
            'return' => 'array',
        ]);

        return ($GLOBALS['fileroot'].$aaSettings[0]['comp_obj_id']);
    }
}

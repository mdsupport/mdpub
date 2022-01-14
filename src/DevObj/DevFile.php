<?php
/**
 * Extend DevObjects functionality to Files.
 *
 * Copyright (C) 2015-2022 MD Support <mdsupport@users.sourceforge.net>
*
* @package   OpenEMR
* @author    MD Support <mdsupport@users.sourceforge.net>
* @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\DevObj;

class DevFile extends DevObject
{
    private $ws = [];
    
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

        // TBD - Confirm the object exists and is active in storage
        // Remove functionality from zsfx.interface.globals.php

        // printf('%s constructed as %s.  ', $strFile, print_r($rs, true));
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

    // Provide access to commonly used attributes of the files
    public function get()
    {
        $recFull = parent::get();
        // Commonly needed attributes
        $cols = ['obj_id', 'obj_version', 'obj_desc'];
        $aaReturn = array_intersect_key($recFull, array_flip($cols));
        return $aaReturn;
    }

    public function getResources($aaFilter = [])
    {
        if (!property_exists($this, 'resources')) {
            $this->resources = $this->getComponents('resource');
        }
        $aaResources = [];
        if ((empty($this->resources)) || (!($this->resources))) return $aaResources;
        foreach ($this->resources as $rowNum => $rowCols) {
            $resDetails = json_decode($rowCols['comp_json'], true);
            $matched = true;
            foreach ($aaFilter['match'] as $filterKey => $filterValue) {
                $matched = $matched && ($resDetails[$filterKey]==$filterValue);
            }
            if ($matched) {
                $aaResources[$rowNum] = (empty($aaFilter['value']) ? $rowCols : $resDetails[$aaFilter['value']]);
            }
        }
        return $aaResources;
    }
    /**
     * Get effective URL for the object
     * @return string - For file or empty object types, returns URL without webserver root.<br>
     * Currently returns false for all other objects. 
     */
    public function getURL()
    {
        if (!($this->isActive())) return false;

        $rs = $this->get();
        $obj_id = $rs['obj_id'];
        // Check if there is any 'redirect' mapping for this devobject
        // TBD : Remove EMR sql function. getComponents returns full records as assoc array
        $rs = sqlQuery(
            'SELECT dt.* FROM dev_component dc
            INNER JOIN dev_obj dt ON dc.comp_obj_id=dt.obj_id AND dc.comp_obj_version=dt.obj_version
            WHERE dc.obj_id=? AND dc.obj_version=? AND dc.comp_type=? AND dt.active=?',
            [$rs['obj_id'], $rs['obj_version'], 'redirect', 1]
        );
        if ($rs) {
            $obj_id = $rs['obj_id'];
        }
        // ** Experimental : return original directory separator(?) **
        return str_replace($rs['obj_id'], $obj_id, $this->ws['obj_id']);
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
     * Emits code to redirect to first component for the file
     */
    public function redirect()
    {
        // Get full records as assoc array from getComponents
        $rs = $this->getComponents('redirect');
        if (!$rs) return false;

        $comp_obj_id = $rs[0]['comp_obj_id'];
        $rs = $this->get();
        $new_uri = str_replace($rs['obj_id'], $comp_obj_id, $_SERVER['REQUEST_URI']);
        // error_log(sprintf('%s -> %s', $rs['obj_id'], $new_uri));
        header( "Location:$new_uri" );

        // This should not happen
        return false;
    }
}

<?php
/**
 * Core DevObjects functionality.
 *
 * Copyright (C) 2015-2022 MD Support <mdsupport@users.sourceforge.net>
*
* @package   OpenEMR
* @author    MD Support <mdsupport@users.sourceforge.net>
* @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\DevObj;

class DevObject
{
    private $objDevDB;

    private $strUuid = '';
    private $rs = [];
    private $rs_keys = [
        'obj_type' => '',
        'obj_id' => '',
        'obj_version' => '',
    ];
    
    /**
     * Dev object constructor
     * Pass any of the indexes to lookup the record.
     *
     * @param array $aaRec - Assoc array with dev_obj columns as keys.
     */
    function __construct($aaRec = [])
    {
        // Establish connection
        $this->objDevDB = new DevDB();

        // Store properties
        $this->strUuid = (empty($aaRec['strUuid']) ? '' : $aaRec['strUuid']);
        // If caller has not specified obj_type default to their class name
        if (empty($aaRec['obj_type'])) {
            $origClass = get_class($this);
            if ($pos = strrpos($origClass, '\\')) {
                $origClass = substr($origClass, $pos + 1);
            }
            $aaRec['obj_type'] = $origClass;
        }

        foreach ($this->rs_keys as $colName => $colValueInit) {
            $this->rs_keys[$colName] = (empty($aaRec[$colName]) ? '' : $aaRec[$colName]);
        }

        if (!empty($this->strUuid)) {
            $this->selectUuid();
        } elseif ((!empty($this->rs_keys['obj_id'])) && (!empty($this->rs_keys['obj_type']))) {
            $this->selectFirst();
        }
    }

/**
 * Experimental - Returns 'assoc' where key is uuid (?)
 * 
 * @return boolean|array|\Mdsupport\Mdpub\DevObj\-|boolean
 */
    private function selectUuid()
    {
        $this->rs = $this->objDevDB->execSql([
            'sql' => 'SELECT *,BIN_TO_UUID(uuid,1) strUuid FROM dev_obj WHERE uuid=UUID_TO_BIN(?, 1)',
            'bind' => [$this->strUuid],
            'return' => 'assoc'
        ]);
        if ($this->rs == false) return false;
        foreach ($this->rs_keys as $rs_key => $rs_value) {
            $this->rs_keys[$rs_key] = $this->rs[$rs_key];
        }
        return $this->rs;
    }

     /**
     * Fetch first record using keys.  If requested, create new record with specified keys.
     * 
     * @param boolean $insert - Automatically create an inactive dev_obj record if not found
     */
    private function selectFirst($insert = false)
    {
        // Check if the record exists
        $recs = $this->objDevDB->execSql([
            'sql' => 'SELECT *,BIN_TO_UUID(uuid,1) strUuid FROM dev_obj',
            'where' => $this->rs_keys,
            'return' => 'array'
        ]);
        if (($recs == false) && ($insert)) {
            $this->strUuid = $this->insert($this->rs_keys);
            return $this->selectUuid();
        } else {
            $this->rs = $recs[0];
        }
        return $this->rs[0];
    }

    protected function insert($aaDevObj) {
        // Get recordset
        $this->rs = $this->objDevDB->execSql([
            'sql' => 'SELECT * FROM dev_obj',
            'where' => $this->rs_keys,
        ]);
        $insSql = $this->objDevDB->getInsertSql($this->rs,$aaDevObj);
        if (!empty($insSql)) {
            $insOK = $this->objDevDB->execSql([
                'sql' => $insSql
            ]);
        }
        if (!$insOK) return $insOK;

        foreach ($this->rs_keys as $keyCol => $value) {
            $this->rs_keys[$keyCol] = $aaDevObj[$keyCol];
        }
        $this->rs = $this->selectFirst();
        return $this->rs['strUuid'];
    }

    /**
     * @return boolean active status of the object
     */
    protected function isActive()
    {
        return (isset($this->rs['active']) && ($this->rs['active'])=='1');
    }

    protected function strUuid()
    {
        return $this->strUuid;
    }

    /**
     * Returns database record
     */
    protected function get() {
        if (empty($this->strUuid)) {
            return [
                '_notfound' => true
            ];
        }
        return $this->rs;
    }

    /**
     * Get recordset containing all active components for the object
     * 
     * @param string comp_type - Optional filter for type of component required
     * @return object recordset - component records
     */
    protected function getComponents($aaFilter = []) {
        if (!$this->isActive()) return false;

        // Component object should be active as well
        $sqlWhere = ['dt.active' => 1];
        foreach ($this->rs_keys as $dcKey => $dcValue) {
            $sqlWhere["dc.$dcKey"] = $dcValue;
        }
        if (!empty($aaFilter)) {
            foreach ($aaFilter as $rowCol => $rowColValue) {
                if ($rowCol == 'comp_json') {
                    $rowColsJson = json_decode($rowColValue, true);
                    foreach ($rowColsJson as $jsonKey => $jsonValue) {
                        $sqlWhere["(JSON_CONTAINS(dc.comp_json, ?, '$.'.$jsonKey)=1)"] = $jsonValue;
                    }
                } else {
                    $sqlWhere["dc.$rowCol"] = $rowColValue;
                }
            }
        }

        // Important : Json values set for the component's object record are used as defaults
        $recComps = $this->objDevDB->execSql([
            'sql' => 'SELECT dc.uuid, dc.comp_obj_id, dc.comp_obj_version, dc.comp_seq,
                      JSON_MERGE_PATCH(ifnull(dt.obj_json,"{}"), ifnull(dc.comp_json,"{}")) comp_json,
                      BIN_TO_UUID(dc.uuid,1) strUuid FROM dev_component dc
                      INNER JOIN dev_obj dt ON dc.comp_obj_id=dt.obj_id AND dc.comp_obj_version=dt.obj_version',
            'where' => $sqlWhere,
            'sfx' => 'ORDER BY dc.comp_seq',
            'return' => 'array',
        ]);

        return ($recComps);
    }
}

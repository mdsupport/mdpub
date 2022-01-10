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
    private $rs_keys = [];
    
    /**
     * Dev object constructor
     * @param string $objUuid - Key to stored object
     * @param string $context - Context to apply for the object 
     */
    function __construct($objUuid = null, $objId= '', $objType = '', $context = '', $insert = false)
    {
        // Establish connection
        $this->objDevDB = new DevDB();

        // Store properties
        $this->strUuid = $objUuid;
        $this->rs_keys['obj_id'] = $objId;
        $this->rs_keys['obj_type'] = $objType;
        $this->rs_keys['obj_version'] = $context;

        if (!empty($objUuid)) {
            $this->selectUuid();
        } elseif (!empty($objId)) {
            $this->select($insert);
        }

        // TBD - Confirm the object exists and is active in storage
        // Remove functionality from zsfx.interface.globals.php
    }

    private function selectUuid()
    {
        $this->rs = $this->objDevDB->execSql([
            'sql' => 'SELECT * FROM dev_obj WHERE uuid=UUID_TO_BIN(?, 1)',
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
        $this->rs = $this->objDevDB->execSql([
            'sql' => 'SELECT *,BIN_TO_UUID(uuid,1) strUuid FROM dev_obj',
            'where' => $this->rs_keys,
            'return' => 'assoc'
        ]);
        if (($this->rs == false) && ($insert)) {
            $this->strUuid = $this->insert($this->rs_keys);
            return $this->selectUuid();
        }
        return $this->rs;
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
        return (isset($this->rs['active']) ? $this->rs['active'] : false);
    }

    /**
     * Returns database record
     */
    protected function get() {
        return $this->rs;
    }

    /**
     * Get recordset containing all active components for the object
     * 
     * @param string comp_type - Optional filter for type of component required
     * @return object recordset - component records
     */
    protected function getComponents($comp_type = null) {
        if (!$this->isActive()) return false;

        $sqlWhere = [];
        foreach ($this->rs_keys as $dcKey => $dcValue) {
            $sqlWhere["dc.$dcKey"] = $dcValue;
        }
        if (!empty($comp_type)) {
            $sqlWhere['dc.comp_type'] = $comp_type;
        }

        $recComps = $this->objDevDB->execSql([
            'sql' => 'SELECT dc.* FROM dev_component dc
                      INNER JOIN dev_obj dt ON dc.comp_obj_id=dt.obj_id AND dc.comp_obj_version=dt.obj_version',
            'where' => $sqlWhere,
            'sfx' => 'ORDER BY dc.comp_seq',
            'return' => 'assoc'
        ]);

        return ($recComps);
    }
}

<?php
/**
 * Core functionality to encapsulate database access for DevSQL objects.
 *
 * Copyright (C) 2015-2022 MD Support <mdsupport@users.sourceforge.net>
*
* @package   Mdpub
* @author    MD Support <mdsupport@users.sourceforge.net>
* @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\DevObj;

use Dotenv\Dotenv;

class DevDB
{
    private $adb = null;

    /**
     * DevDB constructor caches EMR's connection object
     * Constuctors do not permit returning a value. So results of $objInitActs will not be available directly.
     * 
     * @param object $objInitActs - Optional parameter calls method(s) saving additional method calls.
     */
    function __construct($objInitActs = [])
    {
        $this->setConnection();

        foreach ($objInitActs as $iniMethod => $iniMethodParams) {
            if (method_exists($this, $iniMethod)) {
                call_user_func_array($this[$iniMethod], $iniMethodParams);
            }
        }
    }

    /**
     * Establish ADO Connection
     * 
     * @return \ADOConnection object|NULL
     */
    private function setConnection() {
        $connKeySrc = $this->getEnvConnKeySrc();
        if (count(preg_grep("/^if\.OpenEMR\.db/",array_keys($connKeySrc))) < 4) {
            return $GLOBALS['adodb']['db'];
        }
        $this->adb = adoNewConnection($connKeySrc['if.OpenEMR.dbdriver'] ?? 'mysqli');
        $boolConnected = $this->adb->connect(
            $connKeySrc['if.OpenEMR.dbhost'],
            $connKeySrc['if.OpenEMR.dbuser'],
            $connKeySrc['if.OpenEMR.dbuser.password'],
            $connKeySrc['if.OpenEMR.dbname']
        );
        
        return $boolConnected;
    }

    /**
     * Get ADO Connection parameters defined in .env
     *
     * @return array|NULL
     */
    private function getEnvConnKeySrc() {
        $connKeys = [];
        // Try getting connection parameters from cahched $_SESSION
        // For CLI scripts cache will not be available.
        if (php_sapi_name() == 'cli') {
            $connKeySrc = &$_ENV;
        } else {
            $connKeySrc = &$_SESSION;
        }
        $connKeys = preg_grep("/^if\.OpenEMR\.db/",array_keys($connKeySrc));
        if (count($connKeys) >= 4) {
            return $connKeySrc;
        }
        // Traverse parent nodes for .env file
        $ix = 1;
        while (!file_exists(dirname(__FILE__, $ix)."/.env")) {
            if (dirname(__FILE__, $ix) == dirname(__FILE__, $ix+1)) {
                return $connKeys;
            } else {
                $ix++;
            }
        }
        $dotenv = Dotenv::createImmutable(dirname(__FILE__, $ix));
        $dotenv->load();
        if (!empty($_ENV['if.dotenv.alt'])) {
            // Get local details from .env file stored in user's home directoty
            // Special path manipulation is needed since Dotenv->create function appends file to project directory
            $reldir = sprintf(
                '%s%s',
                (preg_replace('/(\w+)/', '..', __DIR__)),
                $_ENV['if.dotenv.alt']
                );
            $dotenv = Dotenv::createMutable(__DIR__, $reldir);
            $dotenv->load();
            unset($_ENV['if.dotenv.alt']);
        }
        if ($connKeySrc !== $_ENV) {
            // Persist keys in session (if available)
            $connKeys = preg_grep("/^if\.OpenEMR\.db/",array_keys($_ENV));
            foreach ($connKeys as $dbkey) {
                $connKeySrc[$dbkey] = $_ENV[$dbkey];
            }
        }
        return $connKeySrc;
    }
    
    private function getExecParams($aaValues, $sqlGlue="=?", $sqlSfx="=?")
    {
        if ((empty($aaValues)) || (!is_array($aaValues)) || (count($aaValues) == 0)) {
            return [ 
                'bind' => [],
                'str' => '',
                'params' => false
            ];
        }

        $sqlFields = array_keys($aaValues);
        $sql = [
            'bind' => array_values($aaValues),
            'str' => implode($sqlGlue,$sqlFields).$sqlSfx,
            'params' => true
        ];
        return $sql;
    }
    // TBD - implement equivalent of sqlstatement, sqlquery
    // Add - Use adodb standard way of create/update records for generic table

    /**
     * Execute a sql statement
     * Optionally create a dev_obj record with specified keys
     * 
     * @param array $aaQuery - Assoc array as 
     *                            sql => single execute statement,
     *                           where => [assoc array of column values],
     *                           bind => [array] for passthru binds,
     *                           sfx => optional string to add after WHERE,
     *                           return => 'array', 'assoc'
     * @return - array(of values), object or return value provided by adodb for execute.
     */
    function execSql($aaExec)
    {
        if ($aaExec['debug']) {
            var_dump($aaExec);
        }
        if ( (!is_array($aaExec)) || (empty($aaExec['sql'])) ) return false;

        if (array_key_exists('where', $aaExec)) {
            $aaExec = array_merge($aaExec, $this->getExecParams($aaExec['where'], '=? AND ', '=?'));
        } elseif (array_key_exists('bind', $aaExec)) {
            $aaExec = array_merge($aaExec, [
                'str' => '',
                'params' => false,
            ]);
        }
        // Construct sql to locate full record as SELECT * FROM tbl WHERE col=? AND col='
        $aaExec['sql'] = sprintf(
            '%s %s %s',
            $aaExec['sql'],
            ($aaExec['params'] ? 'WHERE '.$aaExec['str'] : ''),
            $aaExec['sfx']
        );

        // Hardcoded translation since only 3 valid options
        if (empty($aaExec['return'])) {
            $execResult = $this->adb->execute($aaExec['sql'], $aaExec['bind']);
        } elseif ($aaExec['return'] == 'array') {
            $execResult = $this->adb->getArray($aaExec['sql'], $aaExec['bind']);
        } elseif ($aaExec['return'] == 'assoc') {
            $this->adb->setFetchMode(ADODB_FETCH_ASSOC);
            $execResult = $this->adb->getAssoc($aaExec['sql'], $aaExec['bind']);
        } else {
            $execResult = false;
        }

        return $execResult;
    }

    /**
     * Perform table update
     * @param array $aaUpdate - Assoc array as tblName => [where => [assoc array of column values], set => [assoc array of column values]]
     * @return boolean true if all updates were successful, false if transaction had to be rolled back.
     */
    function execUpdates($aaUpdate)
    {
        if (!is_array($aaUpdate)) return false;

        $this->adb->beginTrans();
        $transOk = true;
        foreach ($aaUpdate as $tbl => $upSpecs) {
            if ((!is_array($upSpecs))
                || (empty($upSpecs['where'])) 
                || (empty($upSpecs['set']))
                || (!is_array($upSpecs['where']))
                || (!is_array($upSpecs['set']))
                || (!$transOk)
            ) {
                continue;
            }
            // Construct sql to locate full record as SELECT * FROM tbl WHERE col=? AND col=?'
            $rsSel = $this->execSql([
                'sql' => "SELECT * FROM $tbl",
                'where' => $upSpecs['where'],
            ]);
            $sqlUpdate = $this->adb->getUpdateSql($rsSel, $upSpecs['set']);
            // Helper function will filter out unchanged fields
            if (strlen($sqlUpdate)) {
                $transOk = $this->adb->execute($sqlUpdate);
            }
        }
        // if $transOk is false, commitTrans should call rollbackTrans
        $transOk = $this->adb->commitTrans($transOk);
        return $transOk;
    }
   
    /**
     * Allow access to ADODB functions
     * @param array $adoMethodArgs - Assoc array as adoMethod => array of arguments for adoMethod
     * @return ADODB response.
     */
    public function adoMethod($adoMethodArgs)
    {
        // Convenience - string with no arguments
        if (!is_array($adoMethodArgs)) {
            $adoMethodArgs = [ $adoMethodArgs => [] ];
        }
        
        // $adoMethodArgs should have single key
        $strMethod = array_key_first($adoMethodArgs);
        
        return call_user_func_array([$this->adb, $strMethod], $adoMethodArgs[$strMethod]);
        
    }
}

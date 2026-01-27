<?php

/**
 * Unified Inbox Item (Base class)
 *
 * @Package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\Tests\AutoInbox;

class clsInItem
{
    private static $aExts = [];
    
    public function __construct() {
        // TBD : Common Inbox Item setup
    }
    
    // Default menu entry
    protected function insertNavItem() {
        $strNavItem = (new \ReflectionClass($this))->getShortName();
        return sprintf('
          <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)">%s</a>
          </li>
        ',
            $strNavItem,
            );
    }
    
    public static function getTypes(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && !empty(self::$aExts)) {
            return self::$aExts;
        }
        
        self::$aExts = []; // reset
        
        $base = static::class;
        $namespace = __NAMESPACE__ . '\\';
        
        foreach (get_declared_classes() as $class) {
            
            // Must be in same namespace
            if (strpos($class, $namespace) !== 0) {
                continue;
            }
            
            // Must extend this base class
            if (!is_subclass_of($class, $base)) {
                continue;
            }
            
            // Must match prefix clsIn*
            if (!preg_match('/\\\clsIn[A-Z]/', $class)) {
                continue;
            }
            
            self::$aExts[] = $class;
        }
        
        return self::$aExts;
    }
    
    public static function callAllTypes(string $method, array $options = [])
    {
        $results = [];
        
        foreach (static::getTypes() as $class) {
            $obj = new $class($options);
            
            if (method_exists($obj, $method)) {
                $results[$class] = $obj->$method();
            }
        }
        
        return $results;
    }
}
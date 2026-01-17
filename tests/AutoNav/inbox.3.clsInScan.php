<?php

/**
 * Unified Inbox Item (Fax/Scan Item class)
 *
 * @Package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\Tests\AutoInbox;

class clsInScan extends clsInItem  
{

    function __construct($aOptions = []) {
      parent::__construct($aOptions);
    }
    
    protected function insertNavItem() {
        return sprintf('
          <li class="nav-item">
            <a class="nav-link" href="#">%s</a>
          </li>
        ',
        'Faxes',
        );
    }
    
}
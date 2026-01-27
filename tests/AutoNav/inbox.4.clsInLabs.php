<?php

/**
 * Unified Inbox Item (Patient Reminders class)
 *
 * @Package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\Tests\AutoInbox;

class clsInLabs extends clsInItem
{
    
    function __construct($aOptions = []) {
        parent::__construct($aOptions);
    }
    
    protected function insertNavItem() {
        return sprintf('
          <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" data-fetch="https://www.open-emr.org/">%s</a>
          </li>
        ',
            'Labs',
            );
    }
    
}
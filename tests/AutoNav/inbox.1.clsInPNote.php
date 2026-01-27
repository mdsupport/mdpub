<?php

/**
 * Unified Inbox Item (Patient Message/Note class)
 *
 * @Package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Mdsupport\Mdpub\Tests\AutoInbox;

class clsInPNote extends clsInItem
{
    
    function __construct($aOptions = []) {
        parent::__construct($aOptions);
    }
    
    protected function insertNavItem() {
        $id = 'dd_' . uniqid();
        return sprintf('
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="javascript:void(0)" id="%s" role="button"
              data-fetch="ajax.controller.php?type=messages"
              data-bs-toggle="dropdown" aria-expanded="false">
              %s
            </a>
            <ul class="dropdown-menu" aria-labelledby="%s">
              <li><a class="dropdown-item" href="messages.php">%s</a></li>
            </ul>
          </li>
        ',
            $id, 'Messages', $id, 'Legacy');
    }
    
}
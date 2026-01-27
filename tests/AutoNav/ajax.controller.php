<?php

/**
 * Test AJAX controller for AutoNav
 *
 * @Package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$type = $_GET['type'] ?? '';

switch ($type) {
    
    case 'messages':
        echo "<div class='p-2'>Loaded Messages via AJAX</div>";
        break;
        
    case 'reminders':
        echo "<div class='p-2'>Loaded Reminders via AJAX</div>";
        break;
        
    default:
        echo "<div class='p-2 text-danger'>Unknown request</div>";
        break;
}
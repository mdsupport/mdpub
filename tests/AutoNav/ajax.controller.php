<?php
// messages.php

// Get the string from GET or POST
$reqType = $_REQUEST['type'] ?? 'default items.';

// Sanitize for output (avoid XSS)
$what = htmlspecialchars($reqType, ENT_QUOTES, 'UTF-8');

// Return a simple response
echo "Here is the list of {$reqType}...";

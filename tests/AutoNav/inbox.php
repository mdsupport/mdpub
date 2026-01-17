<?php

/**
 * Unified Inbox
 *
 * @Package OpenEMR
 * @author MD Support <mdsupport@users.sourceforge.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
namespace Mdsupport\Mdpub\Tests\AutoInbox;

use Mdsupport\Mdpub\Htm\HtmPageAssets;

$objScript = new HtmPageAssets([
    'components' => [
        // Bootstrap requirements
        "https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css",
        "https://code.jquery.com/jquery-3.3.1.slim.min.js",
        "https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js",
        "https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js",
        // Test components
        'meta.htm',
        'common*.*',
    ]
]);

$objScript->insertPHP();

?>
<!DOCTYPE html>
<html>
<head>
  <?php 
    $objScript->insertHTM();
  ?>
  <?php 
    $objScript->insertCSS();
  ?>
</head>
<body>
<div id='container_div' class='container-fluid d-none'>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <a class="navbar-brand" href="#">Inbox</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          Dropdown
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdown">
          <a class="dropdown-item" href="#">Action</a>
          <a class="dropdown-item" href="#">Another action</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="#">Something else here</a>
        </div>
      </li>
    </ul>
    <form class="form-inline my-2 my-lg-0">
      <input class="form-control mr-sm-2" type="search" placeholder="Search" aria-label="Search">
      <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>
    </form>
  </div>
</nav>
  
  <?php 
  // 1. Capture raw nav HTML from all classes
  $raw = implode('', clsInItem::callAllTypes('insertNavItem'));
  // No flash
  $raw = "<div id='nav-items' class='d-none'>$raw</div>";

  // 2. Load into DOM
  $dom = new \DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML('<ul>'.$raw.'</ul>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
  libxml_clear_errors();
  
  $xpath = new \DOMXPath($dom);
  
  // 3. Find all top-level nav-items
  $items = $xpath->query('//li[contains(@class,"nav-item")]');
  
  $targets = [];
  $index = 0;
  
  // 4. Assign inbox-type-N targets to each nav-item
  foreach ($items as $li) {
  
      // Generate unique target
      $target = 'inbox-type-' . $index++;
      $targets[] = $target;
  
      // Assign to top-level nav-link
      $topLink = $xpath->query('.//a[contains(@class,"nav-link")]', $li)->item(0);
      if ($topLink) {
          $topLink->setAttribute('data-target', $target);
      }
  
      // Assign to ALL dropdown items inside this li
      $dropdownLinks = $xpath->query('.//a[contains(@class,"dropdown-item")]', $li);
      foreach ($dropdownLinks as $dd) {
          $dd->setAttribute('data-target', $target);
      }
  }
  
  // 5. Output modified nav HTML
  $navHtml = '';
  $ul = $dom->getElementsByTagName('ul')->item(0);
  foreach ($ul->childNodes as $node) {
      $navHtml .= $dom->saveHTML($node);
  }
  echo $navHtml;
  
  // 6. Output matching content panes
  foreach ($targets as $t) {
      echo "<div id=\"$t\" class=\"tab-content-pane d-none\">$t</div>";
  }
  ?>

  <?php 
  $objScript->insertJS();
  ?>
</body>
</html>
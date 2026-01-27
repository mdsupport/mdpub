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

$BSVERSION = '5.3.8';

$objScript = new HtmPageAssets([
    'components' => [
        // Bootstrap requirements
        "https://cdn.jsdelivr.net/npm/bootstrap@{$BSVERSION}/dist/css/bootstrap.min.css",
        // jQuery not required for BS5
    // "https://code.jquery.com/jquery-3.3.1.slim.min.js",
    // "https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js",
    "https://cdn.jsdelivr.net/npm/bootstrap@{$BSVERSION}/dist/js/bootstrap.bundle.min.js",
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
<nav class="navbar navbar-tight navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">

    <a class="navbar-brand" href="#">Inbox</a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent"
            aria-expanded="false"
            aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">

      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle"
             href="#"
             role="button"
             data-bs-toggle="dropdown"
             aria-expanded="false">
            Dropdown
          </a>

          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">Action</a></li>
            <li><a class="dropdown-item" href="#">Another action</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#">Something else here</a></li>
          </ul>
        </li>

      </ul>

      <form class="d-flex" role="search">
        <input class="form-control me-2"
               type="search"
               placeholder="Search"
               aria-label="Search">
        <button class="btn btn-outline-success" type="submit">Search</button>
      </form>

    </div>
  </div>
</nav>
<div id='container_div' class='container-fluid d-none'>
  
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
          $topLink->setAttribute('data-inbox-target', $target);
      }
  
      // Assign to ALL dropdown items inside this li
      $dropdownLinks = $xpath->query('.//a[contains(@class,"dropdown-item")]', $li);
      foreach ($dropdownLinks as $dd) {
          $dd->setAttribute('data-inbox-target', $target);
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
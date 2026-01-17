/**
 * Dynamic Search for OpenEMR edit_globals.php
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    MD Support <mdsupport@users.sf.net>
 * @copyright Copyright (c) 2025-2026 MD Support <mdsupport@users.sf.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// mdadd helpers
Element.prototype.swap = function(removeClasses, addClasses) {
    this.classList.remove(...removeClasses);
    this.classList.add(...addClasses);
    return this; // allow chaining
};

(function() {
    'use strict';
    /**
     * Initialize on DOM ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
    }

    /**
     * Setup search functionality
     */
    function setup() {
        // Some fixes to header
        let doc = document.getElementById('container_div');
        doc.swap(['container', 'mt-2'], ['container-fluid', 'm-0', 'p-0']);
        doc.querySelector('.h1')?.swap(['h1'],['h5','fw-bold']);
//        doc.querySelector('.navbar-nav').swap([], ['nav-pills', 'nav-justified']);
        
        // Set nav-items
        let navbarUl = doc.querySelector('nav.navbar ul.navbar-nav');
        if (!navbarUl) return;
        
        let navItems = doc.querySelector('#nav-items');
        if (navItems && navbarUl) {
            // Move all child nodes from the source to the destination
            while (navItems.firstChild) {
                navbarUl.appendChild(navItems.firstChild);
            }
        }
        doc.classList.remove('d-none');
        

        // -------------------------------
        // LISTENER A: CLICK HANDLER
        // -------------------------------
        navbarUl.addEventListener("click", function (e) {
            const link = e.target.closest("a");
            if (!link) return;

            const parentItem = link.closest(".nav-item");
            if (!parentItem) return;

            const isDropdownToggle = link.classList.contains("dropdown-toggle");

            // Ignore hover-triggered dropdown openings
            if (isDropdownToggle && e.detail === 0) return;

            const target = link.dataset.target;
            const fetchUrl = link.dataset.fetch;

            if (target) {
                activateTab(target, fetchUrl);
            }
        });

        // -------------------------------
        // LISTENER B: TAB ACTIVATION HANDLER
        // -------------------------------
        document.addEventListener("tabActivated", function (e) {
            const activePaneId = e.detail.targetId;

            // Find the nav-link that points to this pane
            const activeLink = navbarUl.querySelector(`[data-target="${activePaneId}"]`);
            if (!activeLink) return;

            const activeItem = activeLink.closest(".nav-item");

            // Clear all active states
            navbarUl.querySelectorAll(".nav-item").forEach(i => i.classList.remove("active"));

            // Mark the correct top-level tab active
            activeItem.classList.add("active");
        });

        // -------------------------------
        // CORE: ACTIVATE TAB + AJAX LOAD
        // -------------------------------
        function activateTab(targetId, fetchUrl) {

            // Hide all panes
            document.querySelectorAll(".tab-content-pane")
                .forEach(p => p.classList.add("d-none"));

            const pane = document.getElementById(targetId);
            if (!pane) return;

            // Show pane
            pane.classList.remove("d-none");

            // Dispatch activation event
            document.dispatchEvent(new CustomEvent("tabActivated", {
                detail: { targetId }
            }));

            // AJAX load (only once)
            if (!pane.dataset.loaded) {
                loadPaneContent(pane, fetchUrl);
            }
        }

        // -------------------------------
        // AJAX LOADING
        // -------------------------------
        function loadPaneContent(pane, fetchUrl) {
            if (!fetchUrl) return;

            fetch(fetchUrl)
                .then(r => r.text())
                .then(html => {
                    pane.innerHTML = html;
                    pane.dataset.loaded = "true";
                })
                .catch(err => {
                    pane.innerHTML = `<div class="text-danger">Error loading content</div>`;
                });
        }

        // -------------------------------
        // AUTO‑ACTIVATE FIRST TAB
        // -------------------------------
        const firstLink = navbarUl.querySelector(".nav-item .nav-link[data-target]");
        if (firstLink) {
            activateTab(firstLink.dataset.target);
        }

    }

    // Initialize
    init();
})();
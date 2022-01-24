#### Sample Modification - apps
This sample code illustrates modification to standard application using php register_shutdown_function.

##### Changes to standard code
Sample related changes are available in mdpubapps branch of mdsupport's openemr fork. (https://github.com/mdsupport/openemr/tree/mdpubapps)
It is important to run composer dump-autoload after inclusion of mdsupport/mdpub dependency.

##### Setup
Import apps.0.0.sql which sets up following :
* DevFile entries in dev_obj table for 
    - _ /interface/login/login.php _
    - _ /interface/main/calendar/index.php _
    - _ /vendor/mdsupport/mdpub/oemod/apps/interface.login.login.php.sfx_apps.php _

* Specify apss related components in dev_component table as :
    - _ /interface/login/login.php _  ** -> ** /vendor/mdsupport/mdpub/oemod/apps/interface.login.login.php.sfx_apps.php
    
* Specify html_select component in dev_component for building apps select box.

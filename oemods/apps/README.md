#### Sample Modification - apps
This sample code illustrates modification to standard application using php register_shutdown_function.

##### Changes to standard code
This project cannot include a patch for standard code. But globals.php should be modified to enable changes using DevObj using a sample fragment listed below :

```
// DevObj hook
if (class_exists('Mdsupport\Mdpub\DevObj\DevFile')) {
    $objFileScript = new Mdsupport\Mdpub\DevObj\DevFile();
}
```
##### Setup
Import apps.0.0.sql which sets up following :
* DevFile entries in dev_obj table for 
    - _ /interface/login/login.php _
    - _ /interface/main/tabs/main_screen.php _
    - _ /interface/main/calendar/index.php _
    - _ /vendor/mdsupport/oemod/apps/interface.login.login.php.shutdown_function.php _
    - _ /vendor/mdsupport/oemod/apps/interface.main.tabs.main_screen.php.shutdown_function.php _
    
    
* Specify shutdown_function components in dev_component table as :
    - _ /interface/login/login.php _  ** -> ** /vendor/mdsupport/oemod/apps/interface.login.login.php.shutdown_function.php
    - _ /interface/main/tabs/main_screen.php _  ** -> **  _ /vendor/mdsupport/oemod/apps/interface.main.tabs.main_screen.php.shutdown_function.php _
    
    
* Specify html_select component in dev_component for building apps select box.

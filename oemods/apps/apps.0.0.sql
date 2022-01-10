INSERT INTO `dev_obj`(`obj_type`,`obj_id`,`obj_desc`) VALUES
 ('DevFile','/interface/login/login.php','EMR Login')
,('DevFile','/interface/main/tabs/main_screen.php','EMR Main Screen')
,('DevFile','/interface/main/calendar/index.php','EMR Calendar')
,('DevFile','/vendor/mdsupport/oemods/apps/interface.login.login.php.shutdown_function.php','Login shutdown function for apps')
,('DevFile','/vendor/mdsupport/oemods/apps/interface.main.tabs.main_screen.php.shutdown_function.php','Main screen shutdown function for apps')
;

INSERT INTO `dev_component` (`obj_type`,`obj_id`,`comp_obj_type`,`comp_obj_id`,`comp_type`,`comp_seq`,`comp_json`) VALUES
 ('DevFile','/interface/login/login.php','DevFile','/vendor/mdsupport/oemods/apps/interface.login.login.php.shutdown_function.php','resource',0,JSON_OBJECT('type','shutdown_function','fn','inject_apps'))
,('DevFile','/interface/main/tabs/main_screen.php','DevFile','/vendor/mdsupport/oemods/apps/interface.main.tabs.main_screen.php.shutdown_function.php','resource',0,JSON_OBJECT('type','shutdown_function','fn','set_iframe'))
,('DevFile','/vendor/mdsupport/oemods/apps/interface.login.login.php.shutdown_function.php','DevFile','/interface/main/calendar/index.php','resource',1,JSON_OBJECT('type','html_select','id','strUuid','text','obj_desc'))
;

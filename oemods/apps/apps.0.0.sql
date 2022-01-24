INSERT INTO `dev_obj`(`obj_type`,`obj_id`,`obj_desc`) VALUES
 ('DevFile','/interface/login/login.php','EMR Login')
,('DevFile','/interface/main/messages/messages.php','EMR Messages')
,('DevFile','/vendor/mdsupport/mdpub/oemods/apps/interface.login.login.php.sfx_apps.php','Modify Login to include apps')
;

INSERT INTO `dev_component` (`obj_type`,`obj_id`,`comp_obj_type`,`comp_obj_id`,`comp_type`,`comp_seq`,`comp_json`) VALUES
 ('DevFile','/interface/login/login.php','DevFile','/vendor/mdsupport/mdpub/oemods/apps/interface.login.login.php.sfx_apps.php','resource',0,JSON_OBJECT('type','require_once','fnProcess','fnGetApps', 'fnRender', 'fnRenderApps'))
,('DevFile','/vendor/mdsupport/mdpub/oemods/apps/interface.login.login.php.sfx_apps.php','DevFile','/interface/main/messages/messages.php','resource',1,JSON_OBJECT('type','html_select','id','obj_id','text','obj_desc'))
;

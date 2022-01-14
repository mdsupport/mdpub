<?php
use Mdsupport\Mdpub\DevObj\DevFile;

function inject_apps($objFileScript) {
    $objFileScriptResources = $objFileScript->getResources([
        'match'=> ["type" => "html_select"],
    ]);
    // Nothing to be done if no active resources are configured.
    if (empty($objFileScriptResources)) return false;

    $htmlSel = '<option value="*OpenEMR" selected>OpenEMR</option>';
    foreach ($objFileScriptResources as $resData) {
        // TBD : Dynamic class selection
        $objDevComp = new DevFile($resData['comp_obj_id'], $resData['comp_obj_version']);
        $cols = $objDevComp->get();
        $htmlSel .= sprintf(
            '<option value="%s%s">%s</option>',
            $GLOBALS['webroot'], $cols['obj_id'], $cols['obj_desc']
        );
    }
    $htmlSel = sprintf('
        <div class="form-group">
            <label for="appChoice" class="text-right">%s</label>
            <div>
                <select class="form-control" name="appChoice">
                    %s
                </select>
            </div>
        </div>',
        xlt('App Mode'),
        $htmlSel,
    );
    return $htmlSel;
    /*
    // Call injector script to perform actions at client
    // TBD : Use script load resource with custom json
    $dataJson = json_encode([
        'html' => $htmlSel,
        'locate' => '#standard-auth-password',
        'action'=> 'insertAfter',
    ]);
    $retStr = sprintf(
        '<script src="%s%s" data-json=\'%s\'>',
        $GLOBALS['webroot'],
        '/vendor/mdsupport/mdpub/oemods/apps/interface.login.login.php.sfx_apps.js',
        $dataJson
    );
    return $retStr;
     */
}

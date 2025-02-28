<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View What's New");
$smarty = new WHMCS\Smarty(true);
$highlightTracker = new WHMCS\Notification\VersionFeatureHighlights();
$smarty->assign("features", $highlightTracker->getFeatureHighlights());
if (App::getFromRequest("modal")) {
    $smarty->assign("dismissedForAdmin", $aInt->isFeatureHighlightsDismissedUntilUpdate() ? "1" : "0");
    $output = $smarty->fetch("whatsnew_modal.tpl");
    $version = new WHMCS\Version\SemanticVersion(WHMCS\Notification\VersionFeatureHighlights::FEATURE_HIGHLIGHT_VERSION);
    $highlightsVersionTitle = $version->getMajor() . "." . $version->getMinor();
    $response = ["title" => "What's New in <span>Version " . $highlightsVersionTitle . "</span>", "body" => $output];
    $aInt->setBodyContent($response);
    $aInt->output();
}
if (App::getFromRequest("dismiss")) {
    check_token("WHMCS.admin.default");
    if (App::getFromRequest("until_next_update")) {
        $aInt->dismissFeatureHighlightsUntilUpdate();
    } else {
        $aInt->dismissFeatureHighlightsForSession();
        $aInt->removeFeatureHighlightsPermanentDismissal();
    }
    $aInt->setBodyContent(["result" => true]);
    $aInt->output();
}
if (App::getFromRequest("action") == "link-click") {
    check_token("WHMCS.admin.default");
    $linkId = App::getFromRequest("linkId");
    $linkTitle = App::getFromRequest("linkTitle");
    $currentClicks = json_decode(WHMCS\Config\Setting::getValue("WhatNewLinks"), true);
    $version = App::getVersion();
    if (!is_array($currentClicks)) {
        $currentClicks = [];
    }
    $linkName = "v" . $version->getMajor() . $version->getMinor() . "." . $linkTitle . "." . $linkId;
    if (!array_key_exists($linkName, $currentClicks)) {
        $currentClicks[$linkName] = 1;
    } else {
        $currentClicks[$linkName] += 1;
    }
    WHMCS\Config\Setting::setValue("WhatNewLinks", json_encode($currentClicks));
    WHMCS\Terminus::getInstance()->doExit();
}

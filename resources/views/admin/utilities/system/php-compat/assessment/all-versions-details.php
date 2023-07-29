<?php

$assessments = empty($assessments) ? [] : $assessments;
$panels = [];
$tabs = [];
$loader = new WHMCS\Environment\Ioncube\Loader\Loader100100();
foreach ($assessments as $versionDetail) {
    $phpVersion = $versionDetail->getPhpVersion();
    $isActivePhpVersion = $phpVersion == PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;
    $class = $isActivePhpVersion ? "class=\"active\"" : "";
    $icon = "";
    if ($isActivePhpVersion) {
        $tooltip = AdminLang::trans("phpCompatUtil.tooltipCurrentPhpVersion");
        $icon .= "<i class=\"fas fa-location\"\n    data-toggle=\"tooltip\"\n    data-original-title=\"" . $tooltip . "\"\n></i>";
        unset($tooltip);
    }
    if (!$versionDetail->isLoaderVersionSatisfied(WHMCS\Environment\Ioncube\Loader\LocalLoader::getVersion())) {
        $icon .= "<i class=\"fas fa-exclamation-circle\"></i>";
    }
    if (!$versionDetail->isPhpVersionSupported()) {
        $icon .= "<i class=\"fas fa-ban\"></i>";
    }
    $phpId = $versionDetail->getPhpVersionId();
    $tabs[] = "    <li role=\"presentation\" " . $class . ">\n        <a href=\"#tabPhp" . $phpId . "\" \n            id=\"btnPhp" . $phpId . "\" \n            aria-controls=\"tabPhp" . $phpId . "\"\n            role=\"tab\" \n            data-toggle=\"tab\"\n        >\n            " . $icon . "\n            PHP " . $phpVersion . "\n        </a>\n    </li>";
    $panelContent = $versionDetail->getHtml();
    $appendClass = $isActivePhpVersion ? " in active" : "";
    $panels[] = "    <div id=\"tabPhp" . $phpId . "\"\n        class=\"tab-pane fade" . $appendClass . "\"\n        role=\"tabpanel\"  \n        >\n        " . $panelContent . "\n    </div>";
}
echo "<p>\n    ";
echo AdminLang::trans("phpCompatUtil.selectPhpDesc");
echo "</p>\n<br/>\n<div role=\"tabpanel\">\n    <ul class=\"nav nav-tabs\" role=\"tablist\">\n        ";
echo implode("\n", $tabs);
echo "    </ul>\n    <br />\n    <div class=\"tab-content\">\n        ";
echo implode("\n", $panels);
echo "    </div>\n</div>\n";

<?php

$sslConfiguration = $sslOrder->configurationData;
$reissues = $sslConfiguration["reissues"] ?? [];
if (!$reissues) {
    return NULL;
}
$count = 0;
rsort($reissues);
echo "<div class=\"form-horizontal\">\n    <div class=\"alert alert-info\">\n        ";
echo AdminLang::trans("ssl.reissues.lastFive");
echo "    </div>\n    <div class=\"admin-tabs-v2\">\n        <div class=\"tab-content\">\n            <div class=\"tab-pane active\">\n                ";
foreach ($reissues as $reissue) {
    echo "                    <div class=\"form-group\">\n                        <label class=\"col-md-4 col-sm-6 control-label\">\n                            ";
    echo AdminLang::trans("ssl.reissues.date");
    echo "                        </label>\n                        <div class=\"col-md-8 col-sm-6\">";
    echo $reissue["date"];
    echo "</div>\n                    </div>\n                    <div class=\"form-group\">\n                        <label class=\"col-md-4 col-sm-6 control-label\">\n                            ";
    echo AdminLang::trans("ssl.reissues.validationMethod");
    echo "                        </label>\n                        <div class=\"col-md-8 col-sm-6\">\n                            ";
    echo AdminLang::trans("ssl.validationMethod." . $reissue["validationMethod"]);
    echo "                        </div>\n                    </div>\n                    ";
    if ($reissue["validationValue"]) {
        echo "                        <div class=\"form-group\">\n                            <label class=\"col-md-4 col-sm-6 control-label\">\n                                ";
        echo AdminLang::trans("ssl.reissues.validationValue");
        echo "                            </label>\n                            <div class=\"col-md-8 col-sm-6\">";
        echo $reissue["validationValue"];
        echo "</div>\n                        </div>\n                    ";
    }
    echo "                    <hr style=\"margin-top: 0 !important;\"/>\n                    ";
    $count++;
    if (5 <= $count) {
        echo "            </div>\n        </div>\n    </div>\n</div>\n";
    }
}

<?php

use WHMCS\Application\Support\Facades\Lang;

define("ADMINAREA", true);
require "../init.php";
$type = $whmcs->get_req_var("type");
$relatedId = (int) $whmcs->get_req_var("id");
$action = $whmcs->get_req_var("action");
$defaultTranslation = $whmcs->get_req_var("origvalue");
if (!$type) {
    $aInt = new WHMCS\Admin("loginonly");
    $aInt->setBodyContent(["body" => "This page cannot be accessed directly"]);
    $aInt->output();
    WHMCS\Terminus::getInstance()->doExit();
}
switch ($type) {
    case "configurable_option.name":
    case "configurable_option_option.name":
    case "product.description":
    case "product.name":
    case "product.tagline":
    case "product.short_description":
        $aInt = new WHMCS\Admin("Edit Products/Services");
        break;
    case "custom_field.description":
    case "custom_field.name":
        $customFieldType = $relatedId ? WHMCS\Database\Capsule::table("tblcustomfields")->find($relatedId, ["type"])->type : $whmcs->get_req_var("cf-type");
        switch ($customFieldType) {
            case "client":
            case "product":
                $aInt = new WHMCS\Admin("View Products/Services");
                break;
            case "support":
                $aInt = new WHMCS\Admin("Configure Support Departments");
                break;
            default:
                $aInt = new WHMCS\Admin("Configure Custom Client Fields");
        }
        break;
    case "download.description":
    case "download.title":
        $aInt = new WHMCS\Admin("Manage Downloads");
        break;
    case "product_addon.description":
    case "product_addon.name":
        $aInt = new WHMCS\Admin("Configure Product Addons");
        break;
    case "product_bundle.description":
    case "product_bundle.name":
        $aInt = new WHMCS\Admin("Configure Product Bundles");
        break;
    case "product_group.headline":
    case "product_group.name":
    case "product_group.tagline":
    case "product_group_feature.feature":
        $aInt = new WHMCS\Admin("Manage Product Groups");
        break;
    case "ticket_department.description":
    case "ticket_department.name":
        $aInt = new WHMCS\Admin("Configure Support Departments");
        break;
    default:
        $aInt->setBodyContent(["body" => "Invalid Type"]);
        $aInt->output();
        WHMCS\Terminus::getInstance()->doExit();
        $type = str_replace(".", ".{id}.", $type);
        $defaultLanguage = WHMCS\Config\Setting::getValue("Language");
        $languages = array_filter(Lang::getLanguages(), function ($value) use ($defaultLanguage) {
            if ($value == $defaultLanguage) {
                return false;
            }
            return true;
        });
        $body = "";
        $count = 1;
        $inputType = WHMCS\Language\DynamicTranslation::getInputType($type);
        foreach ($languages as $language) {
            $translation = WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => $type, "related_id" => $relatedId, "language" => $language, "input_type" => $inputType]);
            if ($action == "save") {
                $dynamicTranslation = $whmcs->get_req_var($language);
                if ($dynamicTranslation) {
                    $translation->translation = trim(WHMCS\Input\Sanitize::decode($dynamicTranslation));
                    $translation->save();
                } else {
                    if ($dynamicTranslation == "" && $translation->translation != "") {
                        $translation->delete();
                    }
                }
            }
            $language = ucfirst($language);
            $inputField = $translation->getInputField();
            $body .= "    <div class=\"col-md-4 col-sm-6 bottom-margin-5\">\n        " . $language . "<br />\n        " . $inputField . "\n    </div>";
            $count++;
        }
        if ($action == "save") {
            $aInt->setBodyContent(["dismiss" => true, "successMsgTitle" => AdminLang::trans("global.success"), "successMsg" => AdminLang::trans("global.changesuccessdesc")]);
        } else {
            $type = str_replace(".{id}", "", $type);
            $instructions = AdminLang::trans("dynamicTranslation.instructions");
            $defaultValue = AdminLang::trans("dynamicTranslation.defaultValue");
            switch ($inputType) {
                case "text":
                    $readOnlyInput = "<input type=\"text\" name=\"this_will_not_save\" readonly class=\"form-control input-sm\" value=\"" . $defaultTranslation . "\" />";
                    break;
                default:
                    $readOnlyInput = "<textarea name=\"this_will_not_save\" readonly class=\"form-control\" rows=\"" . count(explode("\n", $defaultTranslation)) . "\">" . $defaultTranslation . "</textarea>";
                    $body = "<form method=\"post\" action=\"configtranslations.php?type=" . $type . "&id=" . $relatedId . "&action=save\" class=\"form\">\n    <p class=\"font-size-sm\">" . $instructions . "</p>\n    <div class=\"row\">\n        <div class=\"col-sm-10 col-sm-offset-1\">\n            <div class=\"panel panel-info font-size-sm translate-value\">\n                <div class=\"panel-heading\">" . $defaultValue . "</div>\n                <div class=\"panel-body\">\n                    " . $readOnlyInput . "\n                </div>\n            </div>\n        </div>\n    </div>\n    <div class=\"row font-size-sm\">\n        " . $body . "\n    </div>\n</form>";
                    $aInt->setBodyContent(["body" => $body]);
            }
        }
        $aInt->output();
}

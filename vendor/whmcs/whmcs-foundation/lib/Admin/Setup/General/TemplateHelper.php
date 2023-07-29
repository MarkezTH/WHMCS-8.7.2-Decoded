<?php

namespace WHMCS\Admin\Setup\General;

class TemplateHelper
{
    public static function updateOrderFormAssignmentForTheme($themeName)
    {
        $concerns = static::themeCompatibility($themeName);
        if ($concerns["needsReassign"]) {
            \WHMCS\Product\Group::whereIn("orderfrmtpl", $concerns["needsReassign"])->update(["orderfrmtpl" => $concerns["best"]]);
            $default = \WHMCS\View\Template\OrderForm::getDefault();
            if (!$default || in_array($default->getName(), $concerns["needsReassign"])) {
                \WHMCS\View\Template\OrderForm::setDefault($concerns["best"]);
            }
        }
    }

    public static function themeCompatibility($themeName)
    {
        $data = [];
        $theme = \WHMCS\View\Template\Theme::find($themeName);
        if ($theme) {
            $data["incompatible"] = [];
            $incompat = \WHMCS\View\Template\OrderForm::getIncompatibleWithTheme($theme);
            foreach ($incompat as $orderform) {
                $name = $orderform->getName();
                $data["incompatible"][] = $name;
            }
            $data["needsReassign"] = [];
            if ($incompat) {
                $data["needsReassign"] = \WHMCS\Product\Group::query()->select("orderfrmtpl")->whereIn("orderfrmtpl", $data["incompatible"])->groupBy("orderfrmtpl")->pluck("orderfrmtpl")->toArray();
            }
            $currentDefault = \WHMCS\View\Template\OrderForm::getDefault();
            $currentDefaultName = $currentDefault->getName();
            $currentIncompatible = in_array($currentDefaultName, $data["incompatible"]);
            if ($currentIncompatible && !in_array($currentDefaultName, $data["needsReassign"])) {
                $data["needsReassign"][] = $currentDefaultName;
            }
            $best = \WHMCS\View\Template\OrderForm::getBestCompatibleWithTheme($theme);
            $data["best"] = $best->getName();
            $data["productOrderFormHtml"] = static::adminAreaOrderFormRadioHTML(\WHMCS\View\Template\OrderForm::all(), $theme, $currentDefaultName);
            if ($data["needsReassign"]) {
                $data["incompatibleListHtml"] = \AdminLang::trans("general.orderformIncompatAssigned") . "<ul>" . "<li>" . implode("</li>\n<li>", $data["needsReassign"]) . "</li>" . "</ul>" . \AdminLang::trans("general.orderformChangingTo", [":name" => $best->getDisplayName()]);
            }
        }
        return $data;
    }

    public static function adminAreaOrderFormRadioHTML(\Illuminate\Support\Collection $orderForms, \WHMCS\View\Template\TemplateSetInterface $theme, $selected = "")
    {
        $html = "";
        $systemDefaultOrderForm = \WHMCS\Config\Setting::getValue("OrderFormTemplate");
        if (!$systemDefaultOrderForm) {
            $systemDefaultOrderForm = \WHMCS\View\Template\OrderForm::defaultName();
        }
        $compatUtil = new \WHMCS\View\Template\CompatUtil();
        foreach ($orderForms as $template) {
            $popout = $disabled = $disabledClass = $checked = "";
            $friendlyName = $template->getDisplayName();
            if ($template->getName() == $systemDefaultOrderForm) {
                $friendlyName .= " (<strong>" . \AdminLang::trans("global.default") . "</strong>)";
            }
            $compat = $compatUtil->isCompat($template, $theme);
            if ($compat === true) {
                $opacity = "100%";
                if ($selected == $template->getName() || empty($selected) && $template->isDefault()) {
                    $checked = " checked";
                }
            } else {
                $disabled = " disabled=\"disabled\"";
                $disabledClass = " disabled textgrey";
                $opacity = "45%";
            }
            $concerns = $compatUtil->incompatibilityConcerns($template, $theme);
            if ($concerns) {
                $popout = " data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . $concerns . "\"";
            }
            $html .= "    <div style=\"float:left;padding:10px;text-align:center;\">\n        <label class=\"radio-inline" . $disabledClass . "\"" . $popout . ">\n            <img src=\"" . $template->getThumbnailWebPath() . "\" \n                width=\"165\" \n                height=\"90\" \n                style=\"opacity: " . $opacity . "; border:5px solid #fff;\" \n                alt=\"" . $template->getName() . "\"/><br />\n            <input id=\"orderformtemplate-" . $template->getName() . "\" \n                type=\"radio\" \n                name=\"orderformtemplate\" \n                value=\"" . $template->getName() . "\"" . $checked . $disabled . " /> " . $friendlyName . "\n        </label>\n    </div>";
        }
        return $html;
    }
}

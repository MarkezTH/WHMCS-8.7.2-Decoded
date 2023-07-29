<?php

$flashMessage = WHMCS\FlashMessages::get();
if ($flashMessage) {
    echo WHMCS\View\Helper::alert($flashMessage["text"], $flashMessage["type"]);
}
if ($hasMcPermission) {
    echo "    <div class=\"hidden\">\n        ";
    $this->insert("../marketconnect/shared/header-account");
    echo "    </div>\n    ";
    $this->insert("../marketconnect/shared/header-body");
}
echo "\n<div class=\"admin-tabs-v2 constrained-width\">\n    <form id=\"frmAddAddon\" method=\"post\" action=\"";
echo routePath("admin-setup-product-addon-create");
echo "\" class=\"form-horizontal\">\n        <input type=\"hidden\" name=\"description\" id=\"inputDescription\">\n        <input type=\"hidden\" name=\"email\" id=\"inputEmail\">\n        <input type=\"hidden\" name=\"feature\" id=\"inputFeature\">\n        <div class=\"col-lg-9 col-lg-offset-3 col-md-8 col-sm-offset-4\">\n            <h2>";
echo AdminLang::trans("addons.createnew");
echo "</h2>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputGroup\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
echo AdminLang::trans("fields.addonType");
echo "<br>\n                <small>";
echo AdminLang::trans("addons.addonTypeDescription");
echo "</small>\n            </label>\n            <div class=\"col-lg-9 col-sm-8\">\n                <input type=\"hidden\" name=\"type\" value=\"standard\" id=\"inputAddonType\">\n                <div class=\"multi-select-blocks product-creation-types clearfix\">\n                    <div class=\"block\">\n                        <div class=\"type active\" data-type=\"standard\" id=\"addonTypeStandard\">\n                            <i class=\"fas fa-server\"></i>\n                            <span>";
echo AdminLang::trans("addons.independent");
echo "</span>\n                        </div>\n                    </div>\n                    <div class=\"block\">\n                        <div class=\"type\" data-type=\"feature\" id=\"addonTypeFeature\">\n                            <i class=\"fas fa-cube\"></i>\n                            <span>";
echo AdminLang::trans("addons.addOnFeature");
echo "</span>\n                        </div>\n                    </div>\n                </div>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputAddonName\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
echo AdminLang::trans("addons.name");
echo "<br>\n                <small>";
echo AdminLang::trans("addons.nameDescription");
echo "</small>\n            </label>\n            <div class=\"col-lg-5 col-sm-6\">\n                <input type=\"text\" class=\"form-control\" name=\"name\" id=\"inputAddonName\" required>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputAddonModule\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
echo AdminLang::trans("fields.module");
echo "<br>\n                <small>";
echo AdminLang::trans("products.moduleDescription");
echo "</small>\n            </label>\n            <div class=\"col-lg-3 col-sm-5\">\n                <input type=\"hidden\" name=\"module\">\n                <select name=\"module\" class=\"form-control select-inline\" id=\"inputAddonModule\">\n                    <option value=\"\" data-standard=\"1\">\n                        ";
echo AdminLang::trans("global.noModule");
echo "                    </option>\n                    ";
echo "<optgroup label=\"" . AdminLang::trans("global.popularModules") . "\">";
foreach ($promotedModules as $module) {
    if ($moduleList->has($module)) {
        $moduleInterface->load($module);
        $attributes = ["data-standard=\"1\""];
        $moduleInterface->load($module);
        if ($moduleInterface->functionExists(WHMCS\Admin\Setup\AddonSetup::GET_ADD_ON_FEATURES_FUNCTION)) {
            $attributes[] = "data-feature=\"1\"";
        }
        if ($module == $inputModule) {
            $attributes[] = "selected=\"selected\"";
        }
        $attributes = implode(" ", $attributes);
        echo "<option value=\"" . $module . "\"" . $attributes . ">" . $moduleList[$module] . "</option>";
    }
}
echo "</optgroup>";
echo "<optgroup label=\"" . AdminLang::trans("global.otherModules") . "\">";
foreach ($moduleList as $module => $displayName) {
    if (!$promotedModules->contains($module)) {
        $attributes = ["data-standard=\"1\""];
        $moduleInterface->load($module);
        if ($moduleInterface->functionExists(WHMCS\Admin\Setup\AddonSetup::GET_ADD_ON_FEATURES_FUNCTION)) {
            $attributes[] = "data-feature=\"1\"";
        }
        if ($module == $inputModule) {
            $attributes[] = "selected=\"selected\"";
        }
        $attributes = implode(" ", $attributes);
        echo "<option value=\"" . $module . "\"" . $attributes . ">" . $displayName . "</option>";
    }
}
echo "</optgroup>                </select>\n                <i class=\"fal fa-info-circle\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"";
echo AdminLang::trans("global.someUnavailableForAddOnFeatures");
echo "\"></i>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputHidden\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
echo AdminLang::trans("products.createAsHidden");
echo "<br>\n                <small>";
echo AdminLang::trans("products.createAsHiddenDescription");
echo "</small>\n            </label>\n            <div class=\"col-lg-5 col-sm-6\">\n                <input type=\"hidden\" name=\"hidden\" value=\"0\">\n                <input type=\"checkbox\" class=\"slide-toggle\" name=\"hidden\" id=\"inputHidden\" value=\"1\" checked>\n            </div>\n        </div>\n        <div class=\"form-group\">\n            <label for=\"inputAssign\" class=\"col-lg-3 col-sm-4 control-label\">\n                ";
echo AdminLang::trans("addons.autoAssign");
echo "<br>\n                <small>";
echo AdminLang::trans("addons.autoAssignDescription");
echo "</small>\n            </label>\n            <div class=\"col-lg-5 col-sm-6\">\n                <input type=\"hidden\" name=\"assign\" id=\"inputHiddenAssign\" value=\"0\">\n                <input type=\"checkbox\" class=\"slide-toggle\" name=\"assign\" id=\"inputAssign\" value=\"1\" disabled=\"disabled\">\n            </div>\n        </div>\n\n        <div class=\"btn-container\">\n            <button type=\"submit\" class=\"btn btn-primary\" id=\"btnContinue\">\n                ";
echo AdminLang::trans("global.continue");
echo " &raquo;\n            </button>\n        </div>\n\n        <br>\n        <div class=\"alert-container\">\n            <div class=\"alert alert-success predefined-notice invisible\">\n                <i class=\"fas fa-info-circle fa-fw\"></i>\n                ";
echo AdminLang::trans("addons.predefinedNotice");
echo "            </div>\n        </div>\n    </form>\n</div>\n\n<div class=\"predefined-addons-wrapper\"\">\n    <h2>";
echo AdminLang::trans("addons.predefinedHeading");
echo "</h2>\n    <input type=\"hidden\" name=\"lastSelectedService\" value=\"\">\n    <div class=\"container-fluid multi-select-blocks\">\n        ";
$i = 0;
if ($predefinedAddons) {
    foreach ($predefinedAddons as $predefinedAddon) {
        if (!empty($predefinedAddon["module"])) {
            echo "                    <div class=\"addon-container predefined-addon-container";
            echo $i < 6 ? "" : " hidden";
            echo "\"\n                         data-module=\"";
            echo $predefinedAddon["module"];
            echo "\"\n                         data-name=\"";
            echo $predefinedAddon["addonname"];
            echo "\"\n                         data-description=\"";
            echo $predefinedAddon["addondescription"] ?? "";
            echo "\"\n                         data-email=\"";
            echo $predefinedAddon["welcomeemail"] ?? "";
            echo "\"\n                         data-feature=\"";
            echo $predefinedAddon["featureaddon"] ?? "";
            echo "\"\n                    >\n                        <div class=\"addon-product\">\n                            <div class=\"img-icon\">\n                                ";
            $iconType = $predefinedAddon["icontype"];
            $iconValue = $predefinedAddon["iconvalue"];
            if ($iconType === "url") {
                echo "<img src=\"" . $iconValue . "\">";
            } else {
                if ($iconType === "fa") {
                    echo "<i class=\"" . $iconValue . "\"></i>";
                } else {
                    echo "<i class=\"fad fa-cube\">";
                }
            }
            echo "                            </div>\n                            <span class=\"addon-product-title\">\n                            <strong>";
            echo $predefinedAddon["paneltitle"];
            echo "</strong>\n                            ";
            $labelType = $predefinedAddon["labeltype"];
            $labelValue = $predefinedAddon["labelvalue"] ?? "default";
            if ($labelValue) {
                echo "<span class=\"label label-" . $labelType . "\">" . $labelValue . "</span>";
            }
            echo "                        </span>\n                            <p>";
            echo $predefinedAddon["paneldescription"];
            echo "</p>\n                        </div>\n                    </div>\n                    ";
        } else {
            if ($predefinedAddon["service"]) {
                echo "                    <div class=\"addon-container marketconnect-addon-container";
                echo $i < 6 ? "" : " hidden";
                echo "\"\n                         data-service=\"";
                echo $predefinedAddon["service"];
                echo "\"\n                    >\n                        <div class=\"addon-product";
                echo $predefinedAddon["active"] ? " disabled" : "";
                echo "\">\n                            <span class=\"img-icon\"><img src=\"";
                echo $predefinedAddon["iconvalue"];
                echo "\"></span>\n                            <span class=\"addon-product-title\">\n                                <strong>";
                echo $predefinedAddon["paneltitle"];
                echo "</strong>\n                                ";
                if ($predefinedAddon["active"]) {
                    echo "<span class=\"label label-default\">" . AdminLang::trans("addons.predefinedActive") . "</span>";
                }
                echo "                            </span>\n                            <p>";
                echo $predefinedAddon["paneldescription"];
                echo "</p>\n                        </div>\n                    </div>\n                    ";
            }
        }
        $i++;
    }
}
echo "    </div>\n    <span class=\"expand-collapse";
echo 6 < $i ? "" : " hidden";
echo "\">\n        <a href=\"#\" data-value=\"expand\">";
echo AdminLang::trans("global.expandAll");
echo "</a>\n    </span>\n</div>\n\n<style>\n    .predefined-addons-wrapper {\n        padding-top: 7px;\n        padding-left: 7px;\n        padding-right: 7px;\n        background-color:#f6f6f6;\n        margin: 30px -15px -15px\n    }\n    .predefined-addons-wrapper h2 {\n        margin: 0;\n        padding: 10px 20px;\n    }\n    .predefined-addons-wrapper .addon-container {\n        float: left;\n        padding: 7px;\n        width: 100%;\n    }\n    .predefined-addons-wrapper .addon-product {\n        padding: 10px;\n        background-color: #fff;\n        border: 1px solid #eee;\n        border-radius: 3px;\n        font-size: 0.9em;\n    }\n    .predefined-addons-wrapper div.addon-product:hover {\n        background-color: #f5faff;\n        cursor: pointer;\n    }\n    .predefined-addons-wrapper div.addon-product.disabled:hover {\n        background-color: #fff;\n        cursor: default;\n    }\n    .predefined-addons-wrapper .addon-product .img-icon {\n        width: 50px;\n        height: 50px;\n        float: left;\n        margin-right: 10px;\n        padding: 5px;\n        text-align: center;\n    }\n    .predefined-addons-wrapper .addon-product .img-icon img {\n        max-width: 100%;\n        max-height: 100%;\n    }\n    .predefined-addons-wrapper .addon-product .img-icon i {\n        font-size: 3em;\n        color: #ccc;\n    }\n    .predefined-addons-wrapper .addon-product p {\n        height: 34px;\n        margin: 0;\n        display: -webkit-box;\n        -webkit-line-clamp: 2;\n        -webkit-box-orient: vertical;\n        overflow: hidden;\n    }\n    .predefined-addons-wrapper .addon-product label {\n        display: inline-block;\n    }\n    .predefined-addons-wrapper .addon-product .addon-product-title {\n        display: block;\n        text-overflow: ellipsis;\n        white-space: nowrap;\n        overflow: hidden;\n    }\n    .predefined-addons-wrapper .addon-product .addon-product-title span {\n        display: inline-block;\n    }\n    .expand-collapse {\n        display:block;\n        text-align:right;\n        padding:0 15px 15px;\n        font-size:0.92em;\n    }\n    @media (min-width:768px) {\n        .predefined-addons-wrapper .addon-container {\n            width: 50%;\n        }\n    }\n    @media (min-width:1200px) {\n        .predefined-addons-wrapper .addon-container {\n            width: 33.3%;\n        }\n    }\n</style>\n\n<script>\n    jQuery(document).ready(function() {\n        function limitOptionsByType(type) {\n            var moduleElement = jQuery('#inputAddonModule'),\n                dataType = 'data-' + type + '=\"1\"';\n            moduleElement.find('option').prop('disabled', true).addClass('disabled');\n            moduleElement.find('option[' + dataType + ']').prop('disabled', false).removeClass('disabled');\n            if (moduleElement.find(\":selected\").data(type) !== 1) {\n                moduleElement.find(\":selected\").prop(\"selected\", false);\n                jQuery.growl.warning(\n                    {\n                        title: '',\n                        message: '";
echo AdminLang::trans("addons.invalidModuleForType");
echo "'\n                    }\n                );\n            }\n        }\n        jQuery('.product-creation-types .type').click(function(e) {\n            var type = jQuery(this).data('type'),\n                assignToggle = jQuery('#inputAssign');\n            if (jQuery(this).hasClass('disabled')) {\n                return false;\n            }\n            jQuery('.product-creation-types .type').removeClass('active');\n            jQuery(this).addClass('active');\n            jQuery('#inputAddonType').val(type);\n            limitOptionsByType(type);\n            if (type === 'feature') {\n                assignToggle.bootstrapSwitch('disabled', false).bootstrapSwitch('state', true);\n            } else {\n                assignToggle.bootstrapSwitch('state', false).bootstrapSwitch('disabled', true);\n            }\n        });\n\n        jQuery('.predefined-addon-container').click(function() {\n            var addonTypes = jQuery('.product-creation-types .type'),\n                addonProduct = jQuery(this).find('.addon-product'),\n                moduleElement = jQuery('#inputAddonModule'),\n                assignToggle = jQuery('#inputAssign'),\n                assignHidden = jQuery('#inputHidden'),\n                alertContainer = jQuery('.alert-container');\n            if (addonProduct.hasClass('active')) {\n                addonProduct.removeClass('active');\n                addonTypes.removeClass('disabled');\n                moduleElement.prop('disabled', false);\n                assignHidden.bootstrapSwitch('disabled', false);\n                assignToggle.bootstrapSwitch('disabled', false);\n                jQuery('#inputHiddenAssign').prop('value', '');\n                jQuery('#inputDescription').prop('value', '');\n                jQuery('#inputEmail').prop('value', '');\n                jQuery('#inputFeature').prop('value', '');\n                alertContainer.find('.predefined-notice').addClass('invisible');\n            } else {\n                var inputType = jQuery('#inputAddonType'),\n                    dataModule = jQuery(this).data('module');\n                jQuery('.addon-product').removeClass('active');\n                addonProduct.addClass('active');\n                if (inputType.value !== 'feature') {\n                    addonTypes.removeClass('active');\n                    jQuery('.product-creation-types .type[data-type=\"feature\"]').addClass('active');\n                    inputType.val('feature');\n                }\n                addonTypes.addClass('disabled');\n                if (!jQuery('#inputAddonName').val()) {\n                    jQuery('#inputAddonName').val(jQuery(this).data('name'));\n                }\n                moduleElement.find('option[value=\"' + dataModule + '\"]').prop('selected', true);\n                moduleElement.prop('disabled', true);\n                limitOptionsByType('feature');\n                jQuery('input[name=\"module\"]').prop('value', dataModule);\n                if (assignToggle.bootstrapSwitch('state')) {\n                    assignToggle.bootstrapSwitch('disabled', true);\n                } else {\n                    assignToggle.bootstrapSwitch('disabled', false)\n                        .bootstrapSwitch('state', true)\n                        .bootstrapSwitch('disabled', true);\n                }\n                if (assignHidden.bootstrapSwitch('state')) {\n                    assignHidden.bootstrapSwitch('state', false)\n                        .bootstrapSwitch('disabled', true);\n                } else {\n                    assignHidden.bootstrapSwitch('disabled', true);\n                }\n                jQuery('#inputHiddenAssign').prop('value', '1');\n                jQuery('#hidden').prop('value', '');\n                jQuery('#inputDescription').prop('value', jQuery(this).data('description'));\n                jQuery('#inputEmail').prop('value', jQuery(this).data('email'));\n                jQuery('#inputFeature').prop('value', jQuery(this).data('feature'));\n                alertContainer.find('.predefined-notice').removeClass('invisible');\n            }\n        });\n        jQuery('.expand-collapse a').click(function() {\n            var linkValue = jQuery(this).data('value');\n            if (linkValue === 'expand') {\n                jQuery('.addon-container').each(function() {\n                    jQuery(this).removeClass('hidden');\n                });\n                jQuery(this).text('";
echo AdminLang::trans("global.collapseAll");
echo "').data('value', 'collapse');\n            } else if (linkValue === 'collapse') {\n                jQuery('.addon-container').each(function(index) {\n                    if (index > 5) {\n                        jQuery(this).addClass('hidden');\n                    }\n                });\n                jQuery(this).text('";
echo AdminLang::trans("global.expandAll");
echo "').data('value', 'expand');\n            }\n            return false;\n        });\n        jQuery('.marketconnect-addon-container').click(function() {\n            var serviceName = jQuery(this).data('service');\n            if (jQuery(this).find('.addon-product').hasClass('disabled')) {\n                return false;\n            }\n            jQuery('input[name=\"lastSelectedService\"]').val(serviceName);\n            openModal(\n                'marketconnect.php',\n                'action=showLearnMore&service=' + serviceName,\n                '',\n                'modal-lg',\n                'modal-mc-service'\n            );\n        });\n        jQuery('body').on('click', '.sa-button-container .sa-confirm-button-container button', function() {\n            var serviceName = jQuery('input[name=\"lastSelectedService\"]').val(),\n                serviceUrl = 'marketconnect.php?manage=' + serviceName,\n                servicePanel = jQuery('.marketconnect-addon-container[data-service=\"' + serviceName + '\"]');\n            if (jQuery('.sweet-alert > p').text() === 'Service activated successfully!') {\n                servicePanel.find('.addon-product')\n                    .addClass('disabled');\n                servicePanel.find('.addon-product-title')\n                    .append('<span class=\"label label-default\">";
echo AdminLang::trans("addons.predefinedActive");
echo "</span>');\n                window.location.href = serviceUrl;\n            }\n        });\n    });\n</script>\n";

<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("Configure Payment Gateways", false);
$aInt->title = AdminLang::trans("setup.gateways");
$aInt->sidebar = "config";
$aInt->icon = "offlinecc";
$aInt->helplink = "Payment Gateways";
$aInt->requireAuthConfirmation();
$aInt->requiredFiles(["gatewayfunctions", "modulefunctions"]);
if (App::getFromRequest("manage") && App::getFromRequest("gateway")) {
    $gatewayString = preg_replace("/\\W+/u", "", strip_tags(strtolower(App::getFromRequest("gateway"))));
    redir("manage=" . $gatewayString . "#m_" . $gatewayString);
}
$GatewayValues = $GatewayConfig = $ActiveGateways = [];
$DisabledGateways = $AllGateways = $noConversion = [];
$includedmodules = [];
$noConfigFound = [];
$gatewayInterface = new WHMCS\Module\Gateway();
$AllGateways = $gatewayInterface->getList();
$ActiveGateways = $gatewayInterface->getActiveGateways();
$numgateways = count($ActiveGateways);
$DisabledGateways = array_filter($AllGateways, function ($gateway) use($ActiveGateways) {
    return !in_array($gateway, $ActiveGateways);
});
foreach ($AllGateways as $key => $gatewayModuleName) {
    if (!in_array($gatewayModuleName, $includedmodules)) {
        $gatewayInterface->load($gatewayModuleName);
        if (!in_array($gatewayModuleName, $ActiveGateways) && $gatewayInterface->getMetaDataValue("AllowActivation") === false) {
            unset($AllGateways[$key]);
        } else {
            $includedmodules[] = $gatewayModuleName;
            try {
                $GatewayConfig[$gatewayModuleName] = $gatewayInterface->getConfiguration();
            } catch (Exception $e) {
                $noConfigFound[] = $gatewayModuleName;
            }
            if (in_array($gatewayModuleName, $ActiveGateways)) {
                $noConversion[$gatewayModuleName] = $gatewayInterface->getMetaDataValue("noCurrencyConversion");
                $GatewayValues[$gatewayModuleName] = $gatewayInterface->loadSettings();
                if ($gatewayInterface->functionExists("admin_area_actions")) {
                    $additionalButtons = $gatewayInterface->call("admin_area_actions");
                    $additionalConfig = [];
                    $buttons = [];
                    foreach ($additionalButtons as $data) {
                        if (!is_array($data)) {
                            throw new WHMCS\Exception\Module\NotServicable("Invalid Function Return");
                        }
                        $href = $data["href"];
                        $methodName = $data["actionName"];
                        $buttonName = $data["label"];
                        $additionalHtmlOutput = $data["additionalHtmlOutput"];
                        $classes = ["btn", "btn-default", "right-margin-5"];
                        $additionalAttributes = [];
                        if (array_key_exists("id", $data)) {
                            $additionalAttributes[] = "id=\"" . $data["id"] . "\"";
                        }
                        if (array_key_exists("target", $data)) {
                            $additionalAttributes[] = "target=\"" . $data["target"] . "\"";
                        }
                        if (array_key_exists("dataAttributes", $data) && is_array($data["dataAttributes"])) {
                            foreach ($data["dataAttributes"] as $key => $value) {
                                $additionalAttributes[] = "data-" . $key . "=\"" . $value . "\"";
                            }
                        }
                        if (!empty($data["modal"]) && $data["modal"] === true) {
                            $classes[] = "open-modal";
                            $additionalAttributes[] = "data-modal-title=\"" . $buttonName . "\"";
                            if (!empty($data["modalSize"])) {
                                $additionalAttributes[] = "data-modal-size=\"" . $data["modalSize"] . "\"";
                            }
                        }
                        if (!empty($data["disabled"])) {
                            $classes[] = "disabled";
                        }
                        if ($methodName) {
                            $href = routePath("admin-setup-payments-gateways-action", $gatewayModuleName, $methodName);
                        }
                        $additionalAttributes = implode(" ", $additionalAttributes);
                        $classes = implode(" ", $classes);
                        $button = "<a href=\"" . $href . "\" class=\"" . $classes . "\" " . $additionalAttributes . ">\n    " . $buttonName . "\n</a>";
                        if ($additionalHtmlOutput) {
                            $button .= " " . $additionalHtmlOutput;
                        }
                        $buttons[] = $button;
                    }
                    if (0 < count($buttons)) {
                        $additionalConfig["additional_available_actions"] = ["FriendlyName" => "Available Actions", "Type" => "html", "Description" => implode("", $buttons)];
                        $GatewayConfig[$gatewayModuleName] += $additionalConfig;
                    }
                }
            }
        }
    }
}
$lastorder = count($ActiveGateways);
$action = $whmcs->get_req_var("action");
if ($action == "onboarding" && in_array($gateway, $includedmodules)) {
    $gatewayInterface->load($gateway);
    if ($gatewayInterface->getMetaDataValue("apiOnboarding")) {
        echo $gatewayInterface->getOnBoardingRedirectHtml();
        throw new WHMCS\Exception\ProgramExit();
    }
}
if ($action == "activate" && in_array($gateway, $includedmodules)) {
    check_token("WHMCS.admin.default");
    $gatewayInterface = new WHMCS\Module\Gateway();
    $gatewayInterface->load($gateway);
    if ($gatewayInterface->getMetaDataValue("apiOnboarding")) {
        echo $gatewayInterface->getOnBoardingRedirectHtml();
        throw new WHMCS\Exception\ProgramExit();
    }
    WHMCS\Module\GatewaySetting::gateway($gateway)->delete();
    $lastorder++;
    $gatewayInterface->activate();
    try {
        $gatewayInterface->loadSettings();
        $gatewayInterface->call("post_activation");
    } catch (Exception $e) {
    }
    WHMCS\Session::delete("calinkupdatecc");
    redir("activated=" . $gateway . "#m_" . $gateway);
}
$newgateway = App::getFromRequest("newgateway");
if ($action == "deactivate" && in_array($newgateway, $includedmodules)) {
    check_token("WHMCS.admin.default");
    $gatewayInterface = new WHMCS\Module\Gateway();
    $gatewayInterface->load($gateway);
    $gatewayInterface->deactivate(["oldGateway" => $gateway, "newGateway" => $newgateway, "newGatewayName" => $GatewayConfig[$newgateway]["FriendlyName"]["Value"]]);
    WHMCS\Session::delete("calinkupdatecc");
    WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => true, "module" => $gateway, "message" => AdminLang::trans("gateways.deactivatesuccess")]);
}
if ($action == "save" && in_array($module, $includedmodules)) {
    check_token("WHMCS.admin.default");
    $field = App::getFromRequest("field");
    $GatewayConfig[$module]["visible"] = ["Type" => "yesno"];
    $GatewayConfig[$module]["name"] = ["Type" => "text"];
    $GatewayConfig[$module]["convertto"] = ["Type" => "text"];
    $gateway = new WHMCS\Module\Gateway();
    $gateway->load($module);
    $params = [];
    try {
        $existingParams = $gatewayInterface->getParams();
        $dataToSave = $paramsToValidate = [];
        foreach ($GatewayConfig[$module] as $confname => $values) {
            if ($values["Type"] != "System") {
                $valueToSave = array_key_exists($confname, $field) ? WHMCS\Input\Sanitize::decode(trim($field[$confname])) : "";
                if ($values["Type"] == "password") {
                    $updatedPassword = interpretMaskedPasswordChangeForStorage($valueToSave, $GatewayValues[$module][$confname] ?? "");
                    if ($updatedPassword === false) {
                        $valueToSave = $GatewayValues[$module][$confname];
                    }
                }
                $paramsToValidate[$confname] = $valueToSave;
                $dataToSave[] = ["gateway" => $module, "setting" => $confname, "value" => $valueToSave];
            }
        }
        $gateway->call("config_validate", $paramsToValidate);
        foreach ($dataToSave as $data) {
            WHMCS\Module\GatewaySetting::setValue($data["gateway"], $data["setting"], $data["value"]);
        }
        $gateway->loadSettings();
        $gatewayName = $GatewayConfig[$module]["FriendlyName"]["Value"];
        logAdminActivity("Gateway Module Configuration Modified: '" . $gatewayName . "'");
        try {
            $gateway->call("config_post_save", ["existing" => $existingParams]);
        } catch (Exception $e) {
            $error = $e->getMessage();
            if (!$error) {
                $error = "An unknown error occurred with the configuration check.";
            }
            WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => false, "message" => AdminLang::trans($error)]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if (!$error) {
            $error = "An unknown error occurred with the configuration check.";
        }
        WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => false, "message" => AdminLang::trans($error) . "<br>" . AdminLang::trans("gateways.changesUnsaved")]);
    }
    WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => true, "message" => AdminLang::trans("gateways.savesuccess")]);
}
if ($action == "move") {
    check_token("WHMCS.admin.default");
    if (empty($ActiveGateways)) {
        WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => false, "message" => AdminLang::trans("gateways.noGatewaysActive")]);
    }
    $moduleOrder = App::getFromRequest("order");
    if (empty($moduleOrder) || !is_array($moduleOrder)) {
        WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => false, "message" => AdminLang::trans("gateways.changesUnsaved")]);
    }
    $activeGatewayModels = WHMCS\Module\GatewaySetting::setting("name")->whereIn("gateway", $ActiveGateways)->orderBy("order", "ASC")->orderBy("id", "ASC")->get();
    $friendlyGatewaysArray = [];
    foreach ($moduleOrder as $key => $value) {
        $activeGatewayModel = $activeGatewayModels->where("gateway", $value)->first();
        $friendlyGatewaysArray[] = $activeGatewayModel->value;
        $newOrder = $key + 1;
        if ($activeGatewayModel->order != $newOrder) {
            $activeGatewayModel->order = $newOrder;
            $activeGatewayModel->save();
        }
    }
    if (!empty($friendlyGatewaysArray)) {
        logAdminActivity(sprintf("Gateway Module Sorting Changed: %s", implode(", ", $friendlyGatewaysArray)));
    }
    WHMCS\Http\Message\JsonResponse::factoryOutputWithExit(["success" => true, "message" => AdminLang::trans("gateways.sortchangesuccess")]);
}
$result = select_query("tblcurrencies", "id,code", "", "code", "ASC");
$i = 0;
$currenciesarray[$i] = mysql_fetch_assoc($result);
if ($currenciesarray[$i]) {
    array_pop($currenciesarray);
    $promoHelper = new WHMCS\View\Admin\Marketplace\PromotionHelper();
    $promoHelper->hookIntoPage($aInt);
    $paypalCheckoutActive = false;
    if ($promoHelper->isPromoFetchRequest()) {
        $response = $promoHelper->fetchPromoContent($whmcs->get_req_var("partner"), $whmcs->get_req_var("promodata"));
        $aInt->setBodyContent($response);
    } else {
        $paypalInterface = new WHMCS\Module\Gateway();
        $paypalInterface->load("paypalcheckout");
        $paypalButtons = $paypalInterface->call("admin_area_actions", ["featuredPage" => true]);
        $paypalCheckoutActive = $paypalInterface->isLoadedModuleActive();
        ob_start();
        $showGatewayConfig = false;
        if (App::getFromRequest("activated") || App::getFromRequest("deactivated") || App::getFromRequest("error") || WHMCS\Session::exists("SortStatus") || App::getFromRequest("manage")) {
            $showGatewayConfig = true;
        }
        if ($whmcs->get_req_var("deactivated")) {
            infoBox(AdminLang::trans("global.success"), AdminLang::trans("gateways.deactivatesuccess"));
        }
        if (WHMCS\Session::exists("SortStatus")) {
            list($state, $message) = explode("|", WHMCS\Session::getAndDelete("SortStatus"));
            infoBox(AdminLang::trans("global." . $state), $message, $state);
            unset($message);
            unset($state);
        }
        if (App::getFromRequest("obfailed")) {
            infoBox("Gateway Activation Failed", "The system could not activate the payment gateway successfully. Please try again or contact support.", "error");
        }
        echo WHMCS\View\Asset::jsInclude("Sortable.min.js");
        if (0 < count($noConfigFound)) {
            $noConfigMessage = AdminLang::trans("gateways.noConfigFound");
            echo "        <div class=\"alert alert-info text-center\">";
            echo $noConfigMessage;
            echo "            <ul style=\"display: inline-block; text-align: left;\">\n                ";
            foreach ($noConfigFound as $failedModule) {
                echo "                <li>\n                    ";
                echo "modules" . DIRECTORY_SEPARATOR . "gateways" . DIRECTORY_SEPARATOR . $failedModule . ".php";
                echo "                </li>\n                ";
            }
            echo "            </ul>\n        </div>\n    ";
        }
        echo "    <div class=\"alert alert-info alert-visit-apps\">\n        <span class=\"pull-left\">";
        echo AdminLang::trans("gateways.visitAppsIntegrationsDesc");
        echo "</span>\n        <a class=\"btn btn-default btn-visit-apps pull-right\" href=\"";
        echo routePath("admin-apps-category", "payments");
        echo "\">\n            <i class=\"fas fa-arrow-right\" aria-hidden=\"true\"></i>\n            ";
        echo AdminLang::trans("gateways.visitAppsIntegrationsButton");
        echo "        </a>\n    </div>\n    ";
        $hero = NULL;
        $apps = new WHMCS\Apps\App\Collection();
        $category = (new WHMCS\Apps\Category\Collection())->getCategoryBySlug("payments");
        if (!empty($category)) {
            $hero = $category->getHero($apps);
        }
        if (!empty($hero)) {
            echo "    <div class=\"category-hero-container\">\n        ";
            if ($hero->hasRemoteUrl()) {
                echo "        <a href=\"";
                echo urlencode($hero->getRemoteUrl());
                echo "\" target=\"_blank\" class=\"app-external-url\">\n        ";
            } else {
                if ($hero->hasTargetAppKey()) {
                    echo "        <a href=\"";
                    echo routePath("admin-apps-info", $hero->getTargetAppKey());
                    echo "\" class=\"app-inner open-modal\" data-modal-class=\"app-info-modal\" data-modal-size=\"modal-lg\">\n        ";
                }
            }
            echo "            <img src=\"";
            echo escape($hero->getImageUrl());
            echo "\">\n        </a>\n    </div>\n    ";
        }
        echo "    <div class=\"management-container\" id=\"managementContainer\">\n        ";
        $count = 1;
        $newgateways = "";
        $shownGateways = WHMCS\Module\GatewaySetting::setting("name")->orderBy("order", "ASC")->get();
        echo $infobox ? $infobox . "<br />" : "";
        foreach ($shownGateways as $shownGateway) {
            $module = $shownGateway->gateway;
            $order = $shownGateway->order;
            echo "            <form method=\"post\" action=\"";
            echo $whmcs->getPhpSelf();
            echo "?action=save\" id=\"config";
            echo $module;
            echo "Form\">\n                <input type=\"hidden\" name=\"module\" value=\"";
            echo $module;
            echo "\">\n                ";
            $isModuleDisabled = false;
            if (isset($GatewayConfig[$module])) {
                $modName = $GatewayConfig[$module]["FriendlyName"]["Value"];
            } else {
                $modName = $module;
                $isModuleDisabled = true;
            }
            $gatewayExpanded = false;
            if (in_array($module, [App::getFromRequest("activated"), App::getFromRequest("manage")])) {
                $gatewayExpanded = true;
            }
            $infobox = "";
            $passedParams = [];
            if ($whmcs->get_req_var("activated") == $module) {
                infoBox(AdminLang::trans("global.success"), AdminLang::trans("gateways.activatesuccess"));
            }
            echo "            <div class=\"panel-group\">\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\"  role=\"tab\"  id=\"";
            echo $module . "GatewayHeading";
            echo "\">\n                        <div aria-expanded=\"";
            echo $gatewayExpanded ? "true" : "false";
            echo "\"\n                             aria-controls=\"";
            echo $module . "GatewayCollapse";
            echo "\"\n                             aria-label=\"";
            echo AdminLang::trans("gateways.configureDesc");
            echo "\"\n                        >\n                            <h4 class=\"panel-title pull-left\">\n                                <span data-toggle=\"tooltip\"\n                                      data-placement=\"right\"\n                                      title=\"";
            echo AdminLang::trans("gateways.moveDesc");
            echo "\"\n                                >\n                                    <i class=\"fas fa-arrows\"></i>\n                                </span>\n                                <a aria-expanded=\"";
            echo $gatewayExpanded ? "true" : "false";
            echo "\"\n                                   aria-controls=\"";
            echo $module . "GatewayCollapse";
            echo "\"\n                                   role=\"button\"\n                                   data-toggle=\"collapse\"\n                                   href=\"#";
            echo $module . "GatewayCollapse";
            echo "\"\n                                   name=\"m_";
            echo $module;
            echo "\"\n                                >\n                                    ";
            echo $modName;
            echo "                                </a>\n                            </h4>\n                            <div class=\"heading-actions pull-right\">\n                                <span data-toggle=\"tooltip\"\n                                      data-placement=\"left\"\n                                      title=\"";
            echo AdminLang::trans("gateways.configureDesc");
            echo "\"\n                                >\n                                    <i aria-hidden=\"true\" class=\"fas fa-edit\"></i>\n                                </span>\n                            </div>\n                            <div class=\"clearfix\"></div>\n                        </div>\n                    </div>\n                    <div id=\"";
            echo $module . "GatewayCollapse";
            echo "\"\n                         class=\"panel-collapse collapse";
            echo $gatewayExpanded ? " in" : "";
            echo "\"\n                         role=\"tabpanel\"\n                         aria-labelledby=\"";
            echo $module . "GatewayHeading";
            echo "\"\n                    >\n                        <div class=\"panel-body\">\n                            ";
            if ($infobox) {
                echo $infobox;
            }
            if ($isModuleDisabled === true) {
                echo "                                <p style=\"border: 2px solid red; padding: 10px\">\n                                    <strong>";
                echo AdminLang::trans("gateways.moduleunavailable");
                echo "</strong>\n                                </p>\n                                <a href=\"#\"\n                                   type=\"button\"\n                                   onclick=\"deactivateGW('";
                echo $module;
                echo "', '";
                echo $module;
                echo "'); return false;\"\n                                   class=\"btn btn-danger btn-deactivate-gateway\"\n                                >";
                echo AdminLang::trans("global.deactivate");
                echo "</a>\n                                ";
            } else {
                echo "                                <table class=\"form\"\n                                       id=\"Payment-Gateway-Config-";
                echo $module;
                echo "\"\n                                       width=\"100%\"\n                                       border=\"0\"\n                                       cellspacing=\"2\"\n                                       cellpadding=\"3\"\n                                >\n                                    <tr>\n                                        <td width=\"300\" class=\"fieldlabel\">\n                                            ";
                echo AdminLang::trans("gateways.showonorderform");
                echo "                                        </td>\n                                        <td class=\"fieldarea\">\n                                            <input type=\"checkbox\"\n                                                   name=\"field[visible]\"\n                                                ";
                echo $GatewayValues[$module]["visible"] ? "checked" : "";
                echo "                                            />\n                                        </td>\n                                    </tr>\n                                    <tr>\n                                        <td class=\"fieldlabel\">\n                                            ";
                echo AdminLang::trans("gateways.displayname");
                echo "                                        </td>\n                                        <td class=\"fieldarea\">\n                                            <input type=\"text\"\n                                                   name=\"field[name]\"\n                                                   size=\"30\"\n                                                   class=\"form-control input-inline input-300\"\n                                                   value=\"";
                echo htmlspecialchars($GatewayValues[$module]["name"]);
                echo "\"\n                                            />\n                                        </td>\n                                    </tr>\n                                    ";
                foreach ($GatewayConfig[$module] as $confname => $values) {
                    if ($values["Type"] != "System") {
                        $values["Name"] = "field[" . $confname . "]";
                        if (isset($GatewayValues[$module][$confname])) {
                            $values["Value"] = $GatewayValues[$module][$confname];
                        }
                        if (isset($passedParams[$module][$confname])) {
                            $values["Value"] = $passedParams[$module][$confname];
                        }
                        echo "<tr><td class=\"fieldlabel\">" . $values["FriendlyName"] . "</td><td class=\"fieldarea\">" . moduleConfigFieldOutput($values) . "</td></tr>";
                    }
                }
                if (1 < count($currenciesarray) && !$noConversion[$module]) {
                    echo "<tr><td class=\"fieldlabel\">" . AdminLang::trans("gateways.currencyconvert") . "</td><td class=\"fieldarea\">" . "<select name=\"field[convertto]\" " . "class=\"form-control select-inline\"><option value=\"\">" . AdminLang::trans("global.none") . "</option>";
                    foreach ($currenciesarray as $currencydata) {
                        echo "<option value=\"" . $currencydata["id"] . "\"";
                        if (isset($GatewayValues[$module]["convertto"]) && $currencydata["id"] == $GatewayValues[$module]["convertto"]) {
                            echo " selected";
                        }
                        echo ">" . $currencydata["code"] . "</option>";
                    }
                    echo "</select></td></tr>";
                }
                if (array_key_exists("UsageNotes", $GatewayConfig[$module]) && $GatewayConfig[$module]["UsageNotes"]["Value"]) {
                    echo "<tr>\n    <td class=\"fieldlabel\"></td>\n    <td>\n        <div class=\"alert alert-info clearfix\" role=\"alert\" style=\"margin:0;\">\n            <i class=\"fas fa-info-circle fa-3x pull-left fa-fw\"></i>\n            <div style=\"margin-left: 56px;\">" . $GatewayConfig[$module]["UsageNotes"]["Value"] . "</div>\n        </div>\n    </td>\n</tr>";
                }
                echo "                                        <tr>\n                                            <td class=\"fieldlabel\"></td>\n                                            <td class=\"fieldarea\">\n                                                <button type=\"submit\" class=\"btn btn-primary\">\n                                                    <i class=\"fas fa-spinner fa-spin hidden\"></i>\n                                                    ";
                echo AdminLang::trans("global.savechanges");
                echo "                                                </button>\n                                                ";
                if ($numgateways != "1") {
                    echo "                                                    <a href=\"#\"\n                                                       type=\"button\"\n                                                       onclick=\"deactivateGW('";
                    echo $module;
                    echo "', '";
                    echo $GatewayConfig[$module]["FriendlyName"]["Value"];
                    echo "'); return false;\"\n                                                       class=\"btn btn-danger btn-deactivate-gateway\"\n                                                    >\n                                                        ";
                    echo AdminLang::trans("global.deactivate");
                    echo "                                                    </a>\n                                                    ";
                }
                echo "                                            </td>\n                                        </tr>\n                                    </table>\n                                    ";
            }
            echo "                            </div>\n                        </div>\n                    </div>\n                </div>\n            </form>\n\n            ";
            if ($count != $order) {
                WHMCS\Module\GatewaySetting::gateway($module)->setting("name")->update(["order" => $count]);
            }
            $count++;
            $newgateways .= "<option value=\"" . $module . "\">" . $GatewayConfig[$module]["FriendlyName"]["Value"] . "</option>";
        }
        if (count($ActiveGateways) < 1) {
            echo "<p class=\"alert alert-danger\"><strong>" . AdminLang::trans("gateways.noGatewaysActive") . "</strong> " . AdminLang::trans("gateways.activateGatewayFirst") . "</p>";
        }
        echo "    </div>\n    <p class=\"text-center text-muted\">\n        <small>\n            ";
        echo AdminLang::trans("gateways.visitMarketplaceDesc", [":marketplaceURI" => "https://marketplace.whmcs.com/?utm_source=inproduct&utm_medium=configgateways"]);
        echo "        </small>\n    </p>\n\n";
        $unexpectedErrorString = AdminLang::trans("global.unexpectedError");
        $jquerycode .= "(function() {\n    var oldArray = [];\n    var arrowsElement;\n    var moveIcon = jQuery('.panel-title *[data-toggle=\"tooltip\"]');\n    new Sortable(managementContainer, {\n        animation: 150,\n        ghostClass: 'ghost',\n        handle: '.fa-arrows',\n        filter: '.disabled',\n        onStart: function(event) {\n            oldArray = [];\n            moveIcon.tooltip('destroy');\n            jQuery('form', event.target).each(function() {\n                oldArray.push(jQuery('input[name=\"module\"]', this).val());\n            });\n        },\n        onEnd: function(event) {\n            var newArray = [];\n            var hasChanged = false;\n            moveIcon.tooltip();\n            jQuery('form', event.target).each(function() {\n                newArray.push(jQuery('input[name=\"module\"]', this).val());\n            });\n            for (var i = 0; i < newArray.length; i++) {\n                if (newArray[i] != oldArray[i]) {\n                    hasChanged = true;\n                    break;\n                }\n            }\n            if (hasChanged) {\n                var arrowsElement = jQuery('.fa-arrows', event.item);\n                jQuery('.panel-title .fa-arrows').addClass('disabled');\n                arrowsElement.removeClass('fa-arrows').addClass('fa-spinner fa-spin');\n                WHMCS.http.jqClient.jsonPost({\n                    url: 'configgateways.php?action=move',\n                    data: {\n                        token: csrfToken,\n                        order: newArray\n                    },\n                    success: function(data) {\n                        if (data.success) {\n                            jQuery.growl.notice({\n                                title: '',\n                                message: data.message\n                            });\n                        } else {\n                            jQuery.growl.warning({\n                                title: '',\n                                message: data.message\n                            });\n                        }\n                    },\n                    fail: function() {\n                        jQuery.growl.warning({\n                            title: '',\n                            message: '" . $unexpectedErrorString . "'\n                        });\n                    },\n                    always: function() {\n                        arrowsElement.removeClass('fa-spinner fa-spin').addClass('fa-arrows');\n                       jQuery('.management-container .fa-arrows').removeClass('disabled');\n                    }\n                });\n            }\n        }\n    });\n    jQuery(document).on('click', '.management-container .panel-heading', function(event) {\n        var controllerDiv = jQuery(event.target).closest('.panel-heading').find('div[aria-controls]');\n        var controlledDiv = jQuery('div[id=\"' + controllerDiv.attr('aria-controls') + '\"]');\n        if (jQuery(event.target).is('.fa-arrows')) {\n            return;\n        }\n        controlledDiv.collapse('toggle');\n        controllerDiv.attr('aria-expanded', controlledDiv.attr('aria-expanded'));\n    });\n    jQuery('form[id^=config]').submit(function(event) {\n        event.preventDefault();\n        saveGateway(event.currentTarget);\n    });\n})();";
        $jscode .= "function saveGateway(element) {\n    var spinnerElement = jQuery('.fa-spinner', element);\n    jQuery(element).addClass('disabled').attr('disabled', 'disabled');\n    spinnerElement.removeClass('hidden');\n    WHMCS.http.jqClient.jsonPost({\n        url: 'configgateways.php?action=save',\n        data: jQuery(element).closest('form').serialize(),\n        success: function(data) {\n            if (data.success) {\n                jQuery.growl.notice({\n                    title: '',\n                    message: data.message\n                });\n            } else {\n                jQuery.growl.warning({\n                    title: '',\n                    message: data.message\n                });\n            }\n        },\n        fail: function() {\n            jQuery.growl.warning({\n                title: '',\n                message: '" . $unexpectedErrorString . "'\n            });\n        },\n        always: function() {\n            spinnerElement.addClass('hidden');\n            jQuery(element).removeClass('disabled').removeAttr('disabled');\n        }\n    });\n}\nfunction deactivateGateway(element) {\n    var spinnerElement = jQuery('.fa-spinner', element);\n    jQuery(element).addClass('disabled').attr('disabled', 'disabled');\n    spinnerElement.removeClass('hidden');\n    WHMCS.http.jqClient.jsonPost({\n        url: 'configgateways.php?action=deactivate',\n        data: jQuery('#frmDeactivateGateway').serialize(),\n        success: function(data) {\n            var deactivateButtons = jQuery('.btn-deactivate-gateway');\n            \$('#modalDeactivateGateway').modal('hide');\n            jQuery('.management-container > form input[value=\"' + data.module + '\"]')\n                .closest('form')\n                .remove();\n            if (deactivateButtons.length < 3) {\n                deactivateButtons.remove();\n            }\n            jQuery.growl.notice({\n                title: '',\n                message: data.message\n            });\n        },\n        fail: function() {\n            jQuery.growl.warning({\n                title: '',\n                message: '" . $unexpectedErrorString . "'\n            });\n        },\n        always: function() {\n            spinnerElement.addClass('hidden');\n            jQuery(element).removeClass('disabled').removeAttr('disabled');\n        }\n    });\n}";
        $jscode .= "var gatewayOptions = \"" . addslashes($newgateways) . "\";\nfunction deactivateGW(module,friendlyname) {\n    \$(\"#inputDeactivateGatewayName\").val(module);\n    \$(\"#inputFriendlyGatewayName\").val(friendlyname);\n    \$(\"#inputNewGateway\").html(gatewayOptions);\n    \$(\"#inputNewGateway option[value='\"+module+\"']\").remove();\n    \$(\"#modalDeactivateGateway\").modal(\"show\");\n}";
        echo $aInt->modal("DeactivateGateway", AdminLang::trans("gateways.deactivatemodule"), "<p>" . AdminLang::trans("gateways.deactivatemoduleinfo") . "</p>\n<form method=\"post\" action=\"configgateways.php?action=deactivate\" id=\"frmDeactivateGateway\">\n    <input type=\"hidden\" name=\"gateway\" value=\"\" id=\"inputDeactivateGatewayName\">\n    <input type=\"hidden\" name=\"friendlygateway\" value=\"\" id=\"inputFriendlyGatewayName\">\n    <div class=\"text-center\">\n        <select id=\"inputNewGateway\" name=\"newgateway\" class=\"form-control select-inline\">\n            " . $newgateways . "\n        </select>\n    </div>\n</form>", [["id" => "DeactivateGateway-Deactivate", "title" => "<i class=\"fas fa-spinner fa-spin hidden\"></i>\n" . AdminLang::trans("gateways.deactivate"), "onclick" => "deactivateGateway(this);", "class" => "btn-primary"], ["title" => AdminLang::trans("supportreq.cancel")]]);
        echo $aInt->modal("PaypalUnlinkAccount", AdminLang::trans("paypalCheckout.unlinkAccount"), "", [["title" => AdminLang::trans("global.yes"), "id" => "PaypalUnlinkAccount-Yes", "onclick" => "", "class" => "btn btn-primary"], ["title" => AdminLang::trans("global.cancel")]]);
        $content = ob_get_contents();
        ob_end_clean();
        $aInt->content = $content;
        $aInt->jquerycode = $jquerycode;
        $aInt->jscode = $jscode;
    }
    $aInt->display();
} else {
    $i++;
}

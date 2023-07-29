<?php

namespace WHMCS\Admin\Setup;

class ProductSetup
{
    protected $product = NULL;
    protected $moduleInterface = NULL;
    protected $mode = NULL;
    const VALIDATION_CHECKS = ["validateSlugIsUnique", "validateSlugFormat"];

    protected function getProduct($productId)
    {
        if (is_null($this->product)) {
            $this->product = \WHMCS\Product\Product::findOrFail($productId);
            $this->mode = NULL;
        }
        return $this->product;
    }

    protected function getModuleSetupRequestMode()
    {
        if (!$this->mode) {
            $hasSimpleMode = $this->hasSimpleConfigMode();
            if (!$hasSimpleMode) {
                $mode = "advanced";
            } else {
                $mode = \App::getFromRequest("mode");
                if (!$mode) {
                    $mode = "simple";
                }
            }
            $this->mode = $mode;
        }
        return $this->mode;
    }

    protected function getModuleInterface()
    {
        if (is_null($this->moduleInterface)) {
            $module = \App::isInRequest("module") ? \App::getFromRequest("module") : $this->product->module;
            $this->moduleInterface = new \WHMCS\Module\Server();
            if (!$this->moduleInterface->load($module)) {
                throw new \Exception("Invalid module");
            }
        }
        return $this->moduleInterface;
    }

    protected function hasSimpleConfigMode()
    {
        $moduleInterface = $this->getModuleInterface();
        if ($moduleInterface->functionExists("ConfigOptions")) {
            $configArray = $moduleInterface->call("ConfigOptions", ["producttype" => $this->product->type]);
            foreach ($configArray as $values) {
                if (array_key_exists("SimpleMode", $values) && $values["SimpleMode"]) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function getModuleSettingsFields()
    {
        $mode = $this->getModuleSetupRequestMode();
        $moduleInterface = $this->getModuleInterface();
        if ($moduleInterface->isMetaDataValueSet("NoEditModuleSettings") && $moduleInterface->getMetaDataValue("NoEditModuleSettings")) {
            return [];
        }
        $isSimpleModeRequest = false;
        $noServerFound = false;
        $params = [];
        if ($mode == "simple") {
            $isSimpleModeRequest = true;
            $serverId = (int) \App::getFromRequest("server");
            if (!$serverId) {
                $serverId = getServerID($moduleInterface->getLoadedModule(), \App::isInRequest("servergroup") ? \App::getFromRequest("servergroup") : $this->getProduct(\App::getFromRequest("id"))->serverGroupId);
                if (!$serverId && $moduleInterface->getMetaDataValue("RequiresServer") !== false) {
                    $noServerFound = true;
                } else {
                    $params = $moduleInterface->getServerParams($serverId);
                }
            }
        }
        $moduleInterface = $this->getModuleInterface();
        $configArray = $moduleInterface->call("ConfigOptions", ["producttype" => $this->product->type, "isAddon" => false]);
        $i = 0;
        $isConfigured = false;
        foreach ($configArray as $key => &$values) {
            $i++;
            if (!array_key_exists("FriendlyName", $values)) {
                $values["FriendlyName"] = $key;
            }
            $values["Name"] = "packageconfigoption[" . $i . "]";
            $variable = "moduleConfigOption" . $i;
            $values["Value"] = \App::isInRequest($values["Name"]) ? \App::getFromRequest($values["Name"]) : $this->product->{$variable};
            if ($values["Value"] !== "") {
                $isConfigured = true;
            }
        }
        unset($values);
        $i = 0;
        $fields = [];
        foreach ($configArray as $key => $values) {
            $i++;
            if (!$isConfigured) {
                $values["Value"] = NULL;
            }
            if ($mode == "advanced" || $mode == "simple" && array_key_exists("SimpleMode", $values) && $values["SimpleMode"]) {
                $dynamicFetchError = NULL;
                $supportsFetchingValues = false;
                if (in_array($values["Type"], ["text", "dropdown", "radio"]) && $isSimpleModeRequest && !empty($values["Loader"])) {
                    if ($noServerFound) {
                        $dynamicFetchError = "No server found so unable to fetch values";
                    } else {
                        $supportsFetchingValues = true;
                        try {
                            $loader = $values["Loader"];
                            $values["Options"] = $loader($params);
                            if ($values["Type"] == "text") {
                                $values["Type"] = "dropdown";
                                if ($values["Value"] && !array_key_exists($values["Value"], $values["Options"])) {
                                    $values["Options"][$values["Value"]] = ucwords($values["Value"]);
                                }
                            }
                        } catch (\WHMCS\Exception\Module\InvalidConfiguration $e) {
                            $dynamicFetchError = \AdminLang::trans("products.serverConfigurationInvalid");
                        } catch (\Exception $e) {
                            $dynamicFetchError = $e->getMessage();
                        }
                    }
                }
                $html = moduleConfigFieldOutput($values);
                if (!is_null($dynamicFetchError)) {
                    $html .= "<i id=\"errorField" . $i . "\" class=\"fas fa-exclamation-triangle icon-warning\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . $dynamicFetchError . "\"></i>";
                }
                if ($supportsFetchingValues) {
                    $html .= "<i id=\"refreshField" . $i . "\" class=\"fas fa-sync icon-refresh\" data-product-id=\"" . \App::getFromRequest("id") . "\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"" . \AdminLang::trans("products.refreshDynamicInfo") . "\"></i>";
                }
                $fields[$values["FriendlyName"]] = $html;
            }
        }
        return $fields;
    }

    protected function getModuleActionsHtml()
    {
        $moduleInterface = $this->getModuleInterface();
        if (!$moduleInterface) {
            return "";
        }
        $moduleActions = $moduleInterface->callIfExists("EventActions", []);
        $html = "";
        $savedSetting = [];
        $productModuleSetting = $this->product->getModuleConfigurationSetting("moduleActions")->value;
        if (is_string($productModuleSetting) && 0 < strlen($productModuleSetting)) {
            $savedSetting = json_decode($productModuleSetting, true) ?? [];
        }
        unset($productModuleSetting);
        $titleRendered = 0;
        foreach ($moduleActions as $actionName => $actionData) {
            $html = "<table class=\"form module-settings\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" id=\"tblModuleActionSettings\">";
            if (!empty($actionData["AllowAdmin"])) {
                $html .= "<tr><td class=\"fieldlabel\" width=\"20%\">" . ($titleRendered++ ? "&nbsp" : \AdminLang::trans($actionData["FriendlyName"])) . "</td>" . "<td class=\"fieldarea\">" . "<select class=\"form-control select-inline module-action-control module-action-input\" " . " data-actor=\"client\" " . " name=\"module_actions[" . $actionName . "][admin]\">" . "<option value=\"0\">" . \AdminLang::trans("wptk.disallowAdminInstall") . "</option>" . "<option value=\"1\" " . (!empty($savedSetting[$actionName]["admin"]) ? " selected " : "") . ">" . \AdminLang::trans("wptk.allowAdminInstall") . "</option>" . "</select>" . "</td></tr>";
            }
            if (!empty($actionData["AllowClient"])) {
                $html .= "<tr><td class=\"fieldlabel\" width=\"20%\">" . ($titleRendered++ ? "&nbsp" : \AdminLang::trans($actionData["FriendlyName"])) . "</td>" . "<td class=\"fieldarea\">" . "<select class=\"form-control select-inline module-action-control module-action-input\" " . " data-actor=\"client\" " . " name=\"module_actions[" . $actionName . "][client]\">" . "<option value=\"0\">" . \AdminLang::trans("wptk.disallowClientInstall") . "</option>" . "<option value=\"1\" " . (!empty($savedSetting[$actionName]["client"]) ? " selected " : "") . ">" . \AdminLang::trans("wptk.allowClientInstall") . "</option>" . "</select>" . "</td></tr>";
            }
            if (!empty($actionData["Events"])) {
                $html .= "<tr><td class=\"fieldlabel\" width=\"20%\">&nbsp;</td><td class=\"fieldarea\"><select class=\"form-control select-inline module-action-control module-action-input\"  data-actor=\"auto\"  name=\"module_actions[" . $actionName . "][auto]\">" . "<option value=\"0\">" . \AdminLang::trans("wptk.disallowAutomaticInstall") . "</option>" . "<option value=\"1\" " . (!empty($savedSetting[$actionName]["auto"]) ? " selected " : "") . ">" . \AdminLang::trans("wptk.allowAutomaticInstall") . "</option>" . "</select>" . "</td></tr>";
            }
            $actionParamsData = $actionData["Params"] ?? [];
            $existingFieldNames = $this->product->customFields->pluck("fieldName")->toArray();
            foreach ($actionParamsData as $paramName => $paramData) {
                $disabled = in_array($paramData["Description"], $existingFieldNames) ? " disabled " : "";
                $value = NULL;
                if (isset($savedSetting[$actionName]["params"][$paramName])) {
                    $value = $savedSetting[$actionName]["params"][$paramName];
                } else {
                    if (isset($paramData["Default"])) {
                        $value = $paramData["Default"];
                    }
                }
                $html .= "<tr class=\"module-action-param-row\" data-action-type=\"auto\" " . (empty($savedSetting[$actionName]["auto"]) ? " style=\"display: none\" " : "") . "><td class=\"fieldlabel\" width=\"20%\">" . $paramData["Description"] . "</td>" . "<td class=\"fieldarea\">" . "<input type=\"" . $paramData["Type"] . "\" class=\"form-control select-inline module-action-input\" style=\"min-width: 16em; margin-right: 10px\" " . " name=\"module_actions[" . $actionName . "][params][" . $paramName . "]\" " . " value=\"" . $value . "\"" . (!empty($paramData["Disabled"]) ? " disabled " : "") . ">" . "<button type=\"button\" class=\"btn btn-default btn-create-module-action-custom-field\" " . " data-product-id=\"" . $this->product->id . "\" data-field-name=\"" . $paramData["Description"] . "\" " . " data-field-type=\"" . $paramData["Type"] . "\" " . $disabled . " style=\"height: 30px; margin: 0 5px 3px 0; padding: 2px 10px\" title=\"" . \AdminLang::trans("global.optional") . "\">" . \AdminLang::trans("wptk.createCustomField") . "</button>" . "<span class=\"small\">" . \AdminLang::trans("global.optional") . "</span>" . "</td></tr>";
            }
            $html .= "</table>";
        }
        return $html;
    }

    public function createModuleActionCustomField($productId, \WHMCS\Http\Message\ServerRequest $request)
    {
        $product = \WHMCS\Product\Product::find($productId);
        $fieldName = trim($request->get("field_name"));
        $fieldType = trim($request->get("field_type"));
        if ($fieldName === "") {
            return ["error" => "Invalid field name", "reload" => false];
        }
        $existingCustomField = $product->customFields()->where("fieldname", $fieldName)->first();
        if ($existingCustomField) {
            return ["error" => "Field already exists", "reload" => false];
        }
        $customField = new \WHMCS\CustomField();
        $customField->type = "product";
        $customField->relatedId = $product->id;
        $customField->fieldName = $fieldName;
        $customField->fieldType = $fieldType ?: "text";
        $customField->description = "";
        $customField->fieldOptions = [];
        $customField->regularExpression = "";
        $customField->adminOnly = "";
        $customField->required = "";
        $customField->showOnOrderForm = "on";
        $customField->showOnInvoice = $fieldType === "password" ? "" : "on";
        $customField->sortOrder = 0;
        $customField->save();
        return ["success" => true, "successMsg" => \AdminLang::trans("global.success")];
    }

    public function getModuleSettings($productId)
    {
        $whmcs = \App::self();
        $metricsHtml = NULL;
        $product = $this->getProduct($productId);
        $fields = $this->getModuleSettingsFields();
        $i = 1;
        $html = "<table class=\"form module-settings\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\" id=\"tblModuleSettings\"><tr>";
        foreach ($fields as $friendlyName => $fieldOutput) {
            $i++;
            $html .= "<td class=\"fieldlabel\" width=\"20%\">" . $friendlyName . "</td>" . "<td class=\"fieldarea\">" . $fieldOutput . "</td>";
            if ($i % 2 !== 0) {
                $html .= "</tr><tr>";
            }
        }
        $html .= "</tr></table>";
        $html .= $this->getModuleActionsHtml();
        $moduleInterface = $this->getModuleInterface();
        $enabled = \WHMCS\UsageBilling\Product\UsageItem::ofRelated($product)->ofModule($moduleInterface)->pluck("id", "metric");
        $metricProvider = $moduleInterface->call("MetricProvider");
        if ($metricProvider instanceof \WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface) {
            $metrics = $metricProvider->metrics();
            $numberOfMetrics = count($metrics);
            if ($numberOfMetrics == 1) {
                $columnWidth = 12;
            } else {
                if ($numberOfMetrics == 2) {
                    $columnWidth = 6;
                } else {
                    $columnWidth = 4;
                }
            }
            $metricsHtml = "<div class=\"row\">";
            foreach ($metrics as $metric) {
                $metricsHtml .= "<div class=\"col-md-" . $columnWidth . "\">" . "<div class=\"metric\">" . "<div>" . "<span class=\"name\">" . $metric->displayName() . "</span>" . "<span class=\"toggle\">" . "<input type=\"checkbox\" class=\"metric-toggle\" data-metric=\"" . $metric->systemName() . "\"" . (isset($enabled[$metric->systemName()]) ? " checked" : "") . " ></span>" . "</div>" . "<span class=\"pricing\">" . "<a href=\"#\" class=\"btn-link open-metric-pricing\" data-metric=\"" . $metric->systemName() . "\">" . \AdminLang::trans("usagebilling.configurepricing") . "</a></span>" . "</div>" . "</div>";
            }
            $metricsHtml .= "</div>";
        }
        return ["content" => $html, "mode" => $this->mode, "metrics" => $metricsHtml];
    }

    public static function formatSubDomainValuesToEnsureLeadingDotAndUnique($subDomains = [])
    {
        array_walk($subDomains, function (&$value, $key) {
            if ($value && substr($value, 0, 1) != ".") {
                $value = "." . $value;
            }
        });
        return array_unique($subDomains);
    }

    protected function getUsageItem($productId, \WHMCS\Http\Message\ServerRequest $request)
    {
        $product = \WHMCS\Product\Product::find($productId);
        if (!$product) {
            throw new \WHMCS\Exception("Invalid product ID.");
        }
        $moduleInterface = $this->getModuleInterface();
        $requestMetricName = $request->get("metric", "");
        $usageItem = \WHMCS\UsageBilling\Product\UsageItem::firstOrNewByRelations($requestMetricName, $product, $moduleInterface);
        $metric = $usageItem->getModuleMetric();
        if (!$usageItem->exists) {
            $usageItem->save();
        }
        return $usageItem;
    }

    public function toggleMetric($productId, \WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        $usageItem = $this->getUsageItem($productId, $request);
        $enabledText = \App::getFromRequest("enable", NULL);
        if (empty($enabledText) || strtolower(trim($enabledText)) === "false") {
            $usageItem->isHidden = true;
        } else {
            $usageItem->isHidden = false;
        }
        $usageItem->save();
        if (!$usageItem->isHidden) {
            $schema = $usageItem->pricingSchema;
            if ($schema->count() < 1) {
                $usageItem->createPriceSchemaZero();
            }
        }
        return ["success" => true];
    }

    public function getMetricPricing($productId, \WHMCS\Http\Message\ServerRequest $request)
    {
        $usageItem = $this->getUsageItem($productId, $request);
        $helper = new Product\MetricPriceViewHelper();
        $html = $helper->getMetricPricingModalBody($usageItem);
        return ["body" => $html];
    }

    public function saveMetricPricing($productId, \WHMCS\Http\Message\ServerRequest $request)
    {
        check_token("WHMCS.admin.default");
        try {
            $usageItem = $this->getUsageItem($productId, $request);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Database\QueryException && \WHMCS\Config\Setting::getValue("SQLErrorReporting")) {
                logActivity("SQL Error: " . $e->getMessage());
            }
            return ["error" => true, "errorMsgTitle" => "", "errorMsg" => \AdminLang::trans("global.couldNotProcessRequest")];
        }
        $existingBrackets = $usageItem->pricingSchema;
        $oldBracketIds = [];
        foreach ($existingBrackets as $bracket) {
            $oldBracketIds[] = $bracket->id;
        }
        $schemaType = $request->get("schemaType", \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_SIMPLE);
        $isSimple = $schemaType === \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface::TYPE_SIMPLE;
        $pricingByCurrency = $request->get("pricing", []);
        $bracketFloors = $request->get("above", []);
        $pricingDetails = [];
        $minimumCycle = "monthly";
        $metric = $usageItem->getModuleMetric();
        $units = $metric->units();
        $errors = [];
        $iterationCount = 0;
        $usedBracketFloors = [];
        $included = $units->roundForType($request->get("included", 0));
        $usageItem->included = $included;
        try {
            $usageItem->save();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e instanceof \Illuminate\Database\QueryException && \WHMCS\Config\Setting::getValue("SQLErrorReporting")) {
                logActivity("SQL Error: " . $e->getMessage());
            }
            return ["error" => true, "errorMsgTitle" => "", "errorMsg" => \AdminLang::trans("global.couldNotProcessRequest")];
        }
        foreach ($pricingByCurrency as $currencyId => $minimumTermBracketPricings) {
            $iterationCount++;
            foreach ($minimumTermBracketPricings as $bracketId => $amount) {
                if ($bracketId >= 1) {
                    if (1 < $bracketId && $isSimple) {
                    } else {
                        $nextIndex = $bracketId + 1;
                        if (isset($bracketFloors[$nextIndex])) {
                            $nextStartNumber = str_replace(",", "", $bracketFloors[$nextIndex]);
                        } else {
                            $nextStartNumber = NULL;
                        }
                        $startNumber = str_replace(",", "", $bracketFloors[$bracketId]);
                        $floor = abs($startNumber);
                        $formattedFloor = $units->roundForType($floor);
                        if (!is_null($nextStartNumber)) {
                            $ceiling = abs($nextStartNumber);
                            $formattedCeiling = $units->roundForType($ceiling);
                        } else {
                            $formattedCeiling = 0;
                        }
                        $amount = str_replace(",", "", $amount);
                        $formattedAmount = number_format(round($amount, 2), 2, ".", "");
                        if ($iterationCount == 1 && in_array($formattedFloor, $usedBracketFloors)) {
                            $errors["duplicate"] = \AdminLang::trans("usagebilling.duplicateerror");
                        }
                        $usedBracketFloors[] = $formattedFloor;
                        if (!valueIsZero($formattedAmount) && strlen($formattedAmount) < strlen($amount)) {
                            $errors["price"] = \AdminLang::trans("usagebilling.precisionerror.price");
                        }
                        if (strlen($formattedFloor) < strlen($floor)) {
                            if ($units->type() == $units::TYPE_INT) {
                                $errors["range"] = \AdminLang::trans("usagebilling.precisionerror.rangewholenumber");
                            } else {
                                $errors["range"] = \AdminLang::trans("usagebilling.precisionerror.rangeprecision", [":precision" => $units->formatForType(0)]);
                            }
                        }
                        $pricingDetails[$bracketId]["price"][$currencyId][$minimumCycle] = $formattedAmount;
                        $pricingDetails[$bracketId]["floor"] = $formattedFloor;
                        $pricingDetails[$bracketId]["ceiling"] = $formattedCeiling;
                        $pricingDetails[$bracketId]["type"] = $schemaType;
                    }
                }
            }
        }
        if ($errors) {
            return ["error" => true, "errorMsgTitle" => "", "errorMsg" => implode("<br/>\n", $errors)];
        }
        $usageItem->createPriceSchema($pricingDetails);
        \WHMCS\UsageBilling\Pricing\Product\Bracket::whereIn("id", $oldBracketIds)->each(function ($model) {
            $model->delete();
        });
        return ["dismiss" => true, "success" => true, "successMsgTitle" => "", "successMsg" => \AdminLang::trans("usagebilling.pricingsaved")];
    }
}

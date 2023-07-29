<?php

namespace WHMCS\Module;

class Fraud extends AbstractModule
{
    protected $type = self::TYPE_FRAUD;
    const SKIP_MODULES = ["SKIPPED", "CREDIT"];

    public function getActiveModules()
    {
        return \WHMCS\Database\Capsule::table("tblfraud")->where("setting", "Enable")->where("value", "!=", "")->distinct("fraud")->pluck("fraud")->all();
    }

    public function load($module, $globalVariable = NULL)
    {
        if (in_array($module, self::SKIP_MODULES)) {
            return false;
        }
        return parent::load($module);
    }

    public function getSettings()
    {
        return \WHMCS\Database\Capsule::table("tblfraud")->where("fraud", $this->getLoadedModule())->pluck("value", "setting")->all();
    }

    public function call($function, $params = [])
    {
        $params = array_merge($params, $this->getSettings());
        return parent::call($function, $params);
    }

    public function doFraudCheck($orderid, $userid = "", $ip = "")
    {
        $params = [];
        $params["ip"] = $ip ? $ip : \App::getRemoteIp();
        $params["forwardedip"] = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? NULL;
        $userid = (int) $userid;
        if (!$userid) {
            $userid = \WHMCS\Session::get("uid");
        }
        $clientsdetails = getClientsDetails($userid);
        $params["clientsdetails"] = $clientsdetails;
        $params["clientsdetails"]["countrycode"] = $clientsdetails["phonecc"];
        $order = \WHMCS\Order\Order::find($orderid);
        $params["orderid"] = $order->id;
        $params["order"] = ["id" => $order->id, "order_number" => $order->orderNumber, "amount" => $order->amount, "payment_method" => $order->paymentMethod, "promo_code" => $order->promoCode];
        if (!defined("ADMINAREA")) {
            $params["sessionId"] = session_id();
            $params["userAgent"] = $_SERVER["HTTP_USER_AGENT"] ?? NULL;
            $params["acceptLanguage"] = $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? NULL;
        }
        $hookResponses = run_hook("PreFraudCheck", $params);
        foreach ($hookResponses as $hookResponse) {
            $params = array_merge($params, $hookResponse);
        }
        $response = $this->call("doFraudCheck", $params);
        $output = "";
        if ($response) {
            if (version_compare($this->getAPIVersion(), "1.2", ">=")) {
                $responseData = is_array($response["data"]) ? $response["data"] : [];
                $output = json_encode($responseData);
            } else {
                foreach ($response as $key => $value) {
                    if (!in_array($key, ["userinput", "error", "title", "description"])) {
                        $output .= $key . " => " . $value . "\n";
                    }
                }
            }
        }
        $order->fraudModule = $this->getLoadedModule();
        $order->fraudOutput = $output;
        $order->save();
        $response["fraudoutput"] = $output;
        return $response;
    }

    public function processResultsForDisplay($orderid, $fraudoutput = "")
    {
        if ($orderid && !$fraudoutput) {
            $data = get_query_vals("tblorders", "fraudoutput", ["id" => $orderid, "fraudmodule" => $this->getLoadedModule()]);
            $fraudoutput = $data["fraudoutput"];
        }
        $results = $this->call("processResultsForDisplay", ["data" => $fraudoutput]);
        $fraudResults = \WHMCS\Input\Sanitize::makeSafeForOutput($results);
        if (version_compare($this->getAPIVersion(), "1.2", ">=") && is_string($results)) {
            $return = $results;
        } else {
            $return = "<table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\"><tr>";
            $i = 0;
            foreach ($fraudResults as $key => $value) {
                $i++;
                $colspan = "";
                $width = "";
                $end = "";
                if ($key == "Explanation") {
                    $colspan = " colspan=\"3\"";
                    $i = 2;
                } else {
                    $width = " width=\"20%\"";
                }
                if ($i == 2) {
                    $end = "</tr><tr>";
                    $i = 0;
                }
                $return .= "<td class=\"fieldlabel\" width=\"30%\">" . $key . "</td>" . "<td class=\"fieldarea\"" . $colspan . $width . ">" . $value . "</td>" . $end;
            }
            $return .= "</tr></table>";
        }
        return $return;
    }

    public function getAdminActivationForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configfraud.php")->setMethod(\WHMCS\View\Form::METHOD_GET)->setParameters(["fraud" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.activate"))];
    }

    public function getAdminManagementForms($moduleName)
    {
        return [(new \WHMCS\View\Form())->setUriPrefixAdminBaseUrl("configfraud.php")->setMethod(\WHMCS\View\Form::METHOD_GET)->setParameters(["fraud" => $moduleName])->setSubmitLabel(\AdminLang::trans("global.manage"))];
    }

    public function getConfiguration()
    {
        return $this->call("getConfigArray");
    }

    public function updateConfiguration($parameters = [])
    {
        if (!$this->isActivated()) {
            throw new \WHMCS\Exception\Module\NotActivated("Module not active");
        }
        $moduleSettings = $this->call("getConfigArray");
        $settingsToSave = [];
        $logChanges = false;
        if (0 < count($parameters)) {
            foreach ($parameters as $key => $value) {
                if (array_key_exists($key, $moduleSettings)) {
                    $settingsToSave[$key] = $value;
                    $logChanges = true;
                }
            }
        }
        if (0 < count($settingsToSave)) {
            $this->saveSettings($settingsToSave, $logChanges);
        }
    }

    public function saveSettings($newSettings = [], $logChanges = true)
    {
        $moduleName = $this->getLoadedModule();
        $moduleSettings = $this->call("getConfigArray");
        $previousSettings = $this->getSettings();
        $settingsToSave = [];
        $changes = [];
        foreach ($moduleSettings as $key => $values) {
            if ($values["Type"] != "System") {
                if (isset($newSettings[$key])) {
                    $settingsToSave[$key] = $newSettings[$key];
                } else {
                    if ($values["Type"] == "yesno") {
                        $settingsToSave[$key] = "";
                    } else {
                        if (isset($values["Default"])) {
                            $settingsToSave[$key] = $values["Default"];
                        }
                    }
                }
                if ($values["Type"] == "password" && isset($newSettings[$key]) && isset($previousSettings[$key])) {
                    $updatedPassword = interpretMaskedPasswordChangeForStorage($newSettings[$key], $previousSettings[$key]);
                    if ($updatedPassword === false) {
                        $settingsToSave[$key] = $previousSettings[$key];
                    } else {
                        $changes[] = "'" . $key . "' value modified";
                    }
                }
                if ($values["Type"] == "yesno") {
                    if (!empty($settingsToSave[$key]) && $settingsToSave[$key] !== "off" && $settingsToSave[$key] !== "disabled") {
                        $settingsToSave[$key] = "on";
                    } else {
                        $settingsToSave[$key] = "";
                    }
                    if (empty($previousSettings[$key])) {
                        $previousSettings[$key] = "";
                    }
                    if ($previousSettings[$key] != $settingsToSave[$key]) {
                        $newSetting = $settingsToSave[$key] ?: "off";
                        $oldSetting = $previousSettings[$key] ?: "off";
                        $changes[] = "'" . $key . "' changed from '" . $oldSetting . "' to '" . $newSetting . "'";
                    }
                } else {
                    if (empty($settingsToSave[$key])) {
                        $settingsToSave[$key] = "";
                    }
                    if (empty($previousSettings[$key])) {
                        $previousSettings[$key] = "";
                    }
                    if ($values["Type"] != "password") {
                        if (!$previousSettings[$key] && $settingsToSave[$key]) {
                            $changes[] = "'" . $key . "' set to '" . $settingsToSave[$key] . "'";
                        } else {
                            if ($previousSettings[$key] != $settingsToSave[$key]) {
                                $changes[] = "'" . $key . "' changed from '" . $previousSettings[$key] . "' to '" . $settingsToSave[$key] . "'";
                            }
                        }
                    }
                }
            }
        }
        foreach ($settingsToSave as $setting => $value) {
            \WHMCS\Database\Capsule::table("tblfraud")->updateOrInsert(["fraud" => $this->getLoadedModule(), "setting" => $setting], ["value" => $value]);
        }
        if ($changes && $logChanges) {
            logAdminActivity("Fraud Module Configuration Modified: '" . $this->getDisplayName() . "' - " . implode(". ", $changes) . ".");
        }
        return $this;
    }

    public function isActivated()
    {
        return (bool) in_array($this->getLoadedModule(), $this->getActiveModules());
    }
}

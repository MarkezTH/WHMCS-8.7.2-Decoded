<?php

namespace WHMCS\Service\Automation;

class AddonAutomation
{
    protected $action = "";
    protected $addon = NULL;
    protected $aliasActions = ["CancelAccount" => "TerminateAccount", "Fraud" => "TerminateAccount"];
    protected $error = "";
    protected $response = "";
    protected $supportedActions = ["CreateAccount" => "AddonActivation", "ProvisionAddOnFeature" => "AddonActivation", "SuspendAccount" => "AddonSuspended", "SuspendAddOnFeature" => "AddonSuspended", "UnsuspendAccount" => "AddonUnsuspended", "UnsuspendAddOnFeature" => "AddonUnsuspended", "TerminateAccount" => "AddonTerminated", "DeprovisionAddOnFeature" => "AddonTerminated", "CancelAccount" => "AddonCancelled", "Fraud" => "AddonFraud", "Renew" => "", "ChangePassword" => "", "LoginLink" => "", "ChangePackage" => "", "CustomFunction" => "", "ClientArea" => "", "AddOnFeatureSingleSignOn" => ""];
    protected $ssoFunctions = ["AddOnFeatureSingleSignOn"];
    const RESULT_SUCCESS = "success";

    public static function factory($addon)
    {
        $self = new self();
        if ($addon instanceof \WHMCS\Service\Addon) {
            $self->addon = $addon;
        } else {
            $self->addon = \WHMCS\Service\Addon::findOrFail($addon);
        }
        return $self;
    }

    protected function setAction($action)
    {
        $this->action = $action;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getResponse()
    {
        return $this->response;
    }

    protected function addError($error)
    {
        $this->error = $error;
    }

    protected function addResponse($response)
    {
        $this->response = $response;
    }

    public function runAction($action = "", $extra = [])
    {
        if (!array_key_exists($action, $this->supportedActions)) {
            throw new \WHMCS\Exception\Module\NotServicable("Invalid Action");
        }
        $this->setAction($action == "CustomFunction" ? $extra : $action);
        if ($action == "CreateAccount") {
            $result = $this->addon->legacyProvision();
        } else {
            if (in_array($action, $this->ssoFunctions)) {
                $server = \WHMCS\Module\Server::factoryFromModel($this->addon);
                $result = $server->call($action);
            } else {
                if (!function_exists("ModuleCallFunction")) {
                    require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "modulefunctions.php";
                }
                switch ($action) {
                    case "CustomFunction":
                    case "SuspendAccount":
                        $variables = [$this->addon->serviceId, $extra, $this->addon->id];
                        break;
                    default:
                        $variables = [$this->addon->serviceId, $this->addon->id];
                        if (function_exists("Server" . $action)) {
                            $result = call_user_func_array("Server" . $action, $variables);
                        } else {
                            $result = ModuleCallFunction($action, $this->addon->serviceId, [], $this->addon->id);
                        }
                }
            }
        }
        if (in_array($action, $this->ssoFunctions)) {
            if (!empty($result["success"])) {
                $this->addResponse($result["redirectTo"]);
                $result = "success";
            } else {
                $result = $result["errorMsg"];
            }
        }
        switch ($result) {
            case "success":
                $this->runHook();
                return true;
                break;
            default:
                $this->addError($result);
                return false;
        }
    }

    protected function runHook()
    {
        if ($this->supportedActions[$this->getAction()]) {
            \HookMgr::run($this->supportedActions[$this->getAction()], ["id" => $this->addon->id, "userid" => $this->addon->clientId, "serviceid" => $this->addon->serviceId, "addonid" => $this->addon->addonId]);
        }
    }

    public function provisionAddOnFeature()
    {
        if ($this->runAction("ProvisionAddOnFeature")) {
            $this->addon->status = \WHMCS\Service\Status::ACTIVE;
            $this->addon->save();
            return self::RESULT_SUCCESS;
        }
        return $this->getError();
    }

    public function deprovisionAddOnFeature()
    {
        if ($this->runAction("DeprovisionAddOnFeature")) {
            $this->addon->status = \WHMCS\Service\Status::TERMINATED;
            $this->addon->terminationDate = \WHMCS\Carbon::today()->toDateString();
            $this->addon->save();
            return self::RESULT_SUCCESS;
        }
        return $this->getError();
    }

    public function suspendAddOnFeature()
    {
        if ($this->runAction("SuspendAddOnFeature")) {
            $this->addon->status = \WHMCS\Service\Status::SUSPENDED;
            $this->addon->save();
            return self::RESULT_SUCCESS;
        }
        return $this->getError();
    }

    public function unsuspendAddOnFeature()
    {
        if ($this->runAction("UnsuspendAddOnFeature")) {
            $this->addon->status = \WHMCS\Service\Status::ACTIVE;
            $this->addon->save();
            return self::RESULT_SUCCESS;
        }
        return $this->getError();
    }

    public function singleSignOnAddOnFeature()
    {
        if ($this->runAction("AddOnFeatureSingleSignOn")) {
            return $this->getResponse();
        }
        throw new \WHMCS\Exception\Module\SingleSignOnError($this->getError());
    }

    public function provision()
    {
        if ($this->addon->provisioningType === \WHMCS\Product\Addon::PROVISIONING_TYPE_FEATURE) {
            return $this->provisionAddOnFeature() == self::RESULT_SUCCESS;
        }
        return $this->runAction("CreateAccount");
    }
}

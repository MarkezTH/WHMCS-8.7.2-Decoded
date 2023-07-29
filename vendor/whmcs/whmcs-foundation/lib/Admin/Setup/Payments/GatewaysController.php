<?php

namespace WHMCS\Admin\Setup\Payments;

class GatewaysController
{
    public function handleOnboardingReturn(\WHMCS\Http\Message\ServerRequest $request)
    {
        $adminBaseUrl = \App::getSystemURL() . \App::get_admin_folder_name() . DIRECTORY_SEPARATOR;
        $gateway = $request->get("gateway");
        $json = $request->get("json");
        $gatewayInterface = new \WHMCS\Module\Gateway();
        $gatewayInterface->load($gateway);
        if ($gatewayInterface->functionExists("onboarding_response_handler")) {
            try {
                $response = $gatewayInterface->call("onboarding_response_handler", ["request" => $request, "gatewayInterface" => $gatewayInterface]);
                if (is_array($response)) {
                    if ($gatewayInterface->isLoadedModuleActive()) {
                        $method = "updateConfiguration";
                    } else {
                        $method = "activate";
                    }
                    $gatewayInterface->{$method}($response);
                }
                if ($json) {
                    return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
                }
                $action = $gatewayInterface->isLoadedModuleActive() ? "updated" : "activated";
                return new \Laminas\Diactoros\Response\RedirectResponse($adminBaseUrl . "configgateways.php?" . $action . "=" . $gateway . "#m_" . $gateway);
            } catch (\Exception $e) {
                if ($json) {
                    return new \WHMCS\Http\Message\JsonResponse(["success" => false]);
                }
                return new \Laminas\Diactoros\Response\RedirectResponse($adminBaseUrl . "configgateways.php?obfailed=1");
            }
        }
        if ($json) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "notsupported" => true]);
        }
        return new \Laminas\Diactoros\Response\RedirectResponse($adminBaseUrl . "configgateways.php?obnotsupported=1");
    }

    public function callAdditionalFunction(\WHMCS\Http\Message\ServerRequest $request)
    {
        $gateway = $request->get("gateway");
        $method = $request->get("method");
        $gatewayInterface = new \WHMCS\Module\Gateway();
        if ($gatewayInterface->load($gateway) && $gatewayInterface->functionExists("admin_area_actions")) {
            $additionalFunctions = $gatewayInterface->call("admin_area_actions");
            foreach ($additionalFunctions as $data) {
                if (!is_array($data)) {
                    throw new \WHMCS\Exception\Module\NotServicable("Invalid Function Return");
                }
                $methodName = $data["actionName"];
                if ($methodName == $method) {
                    return new \WHMCS\Http\Message\JsonResponse($gatewayInterface->call($method, ["gatewayInterface" => $gatewayInterface]));
                }
            }
        }
        throw new \WHMCS\Payment\Exception\InvalidModuleException("Invalid Access Attempt");
    }
}

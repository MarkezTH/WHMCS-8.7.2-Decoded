<?php

namespace WHMCS\Admin\Billing\Dispute;

class DisputeController
{
    public function index($MessageInterface, $request)
    {
        $currentUser = (new \WHMCS\Authentication\CurrentUser())->admin();
        $activeGateways = \WHMCS\Gateways::getActiveGateways();
        $supportedGateways = [];
        foreach ($activeGateways as $activeGateway) {
            try {
                $gatewayInterface = \WHMCS\Module\Gateway::factory($activeGateway);
                if (!$gatewayInterface->functionExists("ListDisputes")) {
                    throw new \WHMCS\Exception\Module\NotServicable("Unsupported Gateway");
                }
                $supportedGateways[] = $activeGateway;
            } catch (\Throwable $e) {
            }
        }
        $disputeCollections = $authenticationFailures = [];
        foreach ($supportedGateways as $supportedGateway) {
            $gatewayInterface = \WHMCS\Module\Gateway::factory($supportedGateway);
            $name = $gatewayInterface->getDisplayName();
            try {
                $disputeCollections[$name] = $gatewayInterface->call("ListDisputes");
            } catch (\Throwable $e) {
                if ($e instanceof \Stripe\Exception\AuthenticationException || $e instanceof \WHMCS\Module\Gateway\Paypalcheckout\Exception\AuthError) {
                    $authenticationFailures[] = $gatewayInterface->getDisplayName();
                } else {
                    throw $e;
                }
            }
        }
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("disputes.listDisputes"))->setSidebarName("billing")->setHelpLink("Disputes");
        $content = view("admin.billing.disputes.list", ["disputeCollections" => $disputeCollections, "authenticationFailures" => $authenticationFailures, "supportedGateways" => $supportedGateways, "currentUser" => $currentUser]);
        $view->setBodyContent($content);
        return $view;
    }

    public function view($MessageInterface, $request)
    {
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("disputes.viewDispute"))->setSidebarName("billing")->setHelpLink("Disputes");
        $gateway = $request->get("gateway");
        $disputeId = $request->get("disputeId");
        $gatewayInterface = \WHMCS\Module\Gateway::factory($gateway);
        $dispute = $gatewayInterface->call("FetchDispute", ["disputeId" => $disputeId]);
        $currentUser = (new \WHMCS\Authentication\CurrentUser())->admin();
        $flash = get_flash_message();
        if ($flash["type"] === "error") {
            $flash["type"] = "danger";
        }
        $content = view("admin.billing.disputes.view", ["dispute" => $dispute, "gateway" => $gatewayInterface->getDisplayName(), "currentUser" => $currentUser, "flash" => $flash]);
        $view->setBodyContent($content);
        return $view;
    }

    public function submitEvidence($MessageInterface, $request)
    {
        $gateway = $request->get("gateway");
        $disputeId = $request->get("disputeId");
        $gatewayInterface = \WHMCS\Module\Gateway::factory($gateway);
        $dispute = $gatewayInterface->call("FetchDispute", ["disputeId" => $disputeId]);
        $evidence = [];
        $error = [];
        $disputeEvidence = collect($dispute->getEvidence());
        $disputeEvidence = $disputeEvidence->reject(function ($evidenceItem) use($request, $dispute) {
            $key = $evidenceItem["name"];
            if ($dispute->getEvidenceType($key) == "custom") {
                foreach ($dispute->getCustomData($key, true) as $customData) {
                    $customKey = $key . "-" . $customData->name;
                    if (!$request->has($customKey) && empty($_FILES[$customKey])) {
                        return true;
                    }
                    return false;
                }
            }
            return !$request->has($key) && empty($_FILES[$key]);
        });
        foreach ($disputeEvidence as $evidenceItem) {
            $requestItems = [];
            $name = $evidenceItem["name"];
            $disputeType = $dispute->getEvidenceType($name);
            if ($disputeType === "custom") {
                foreach ($dispute->getCustomData($name, true) as $customData) {
                    $customName = $name . "-" . $customData->name;
                    $requestItems[] = ["name" => $customName, "type" => $customData->type];
                }
            } else {
                $requestItems[] = ["name" => $name, "type" => $dispute->getEvidenceType($name)];
            }
            foreach ($requestItems as $requestItem) {
                $requestItemName = $requestItem["name"];
                if ($request->has($requestItemName)) {
                    $evidence[$requestItemName] = $request->get($requestItemName);
                } else {
                    if ($requestItem["type"] === "file" && !empty($_FILES[$requestItemName])) {
                        if ($disputeType === "custom") {
                            $evidence[$requestItemName] = $_FILES[$requestItemName]["name"];
                            $evidence["file"] = $_FILES[$requestItemName];
                        } else {
                            try {
                                $file = $gatewayInterface->call("UploadFile", ["file" => $_FILES[$requestItemName]]);
                                $evidence[$requestItemName] = $file;
                            } catch (\Throwable $e) {
                                $error[] = $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
        $evidence = array_filter($evidence);
        if ($evidence) {
            try {
                $gatewayInterface->call("UpdateDispute", ["disputeId" => $disputeId, "evidence" => $evidence]);
            } catch (\Throwable $e) {
                $error[] = $e->getMessage();
            }
        }
        $response = new \WHMCS\Http\RedirectResponse(fqdnRoutePath("admin-billing-disputes-view", $gateway, $disputeId));
        if ($error) {
            $response->withError(implode("<br>", $error));
        } else {
            $response->withSuccess(\AdminLang::trans("disputes.disputeUpdated"));
        }
        return $response;
    }

    public function submit($MessageInterface, $request)
    {
        $gateway = $request->get("gateway");
        $disputeId = $request->get("disputeId");
        $gatewayInterface = \WHMCS\Module\Gateway::factory($gateway);
        try {
            $gatewayInterface->call("SubmitDispute", ["disputeId" => $disputeId]);
            return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
        } catch (\Throwable $t) {
            return new \WHMCS\Http\Message\JsonResponse(["errorMsg" => $t->getMessage()]);
        }
    }

    public function close($MessageInterface, $request)
    {
        $gateway = $request->get("gateway");
        $disputeId = $request->get("disputeId");
        $gatewayInterface = \WHMCS\Module\Gateway::factory($gateway);
        try {
            $gatewayInterface->call("CloseDispute", ["disputeId" => $disputeId]);
            return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
        } catch (\Throwable $t) {
            return new \WHMCS\Http\Message\JsonResponse(["errorMsg" => $t->getMessage()]);
        }
    }
}

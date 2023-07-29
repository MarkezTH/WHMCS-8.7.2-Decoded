<?php

namespace WHMCS\User\Validation\ValidationCom;

class ValidationComController
{
    protected function getValidationCom($ValidationCom)
    {
        return new ValidationCom();
    }

    public function tokenStatus($JsonResponse, $request)
    {
        $user = \WHMCS\User\User::find($request->get("requestorid"));
        if (!$user) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "status" => \AdminLang::trans("user.notFound")]);
        }
        $validationCom = $this->getValidationCom();
        $validationCom->refreshStatusForUser($user);
        $statusForOutput = $validationCom->getStatusForOutput($user);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "status" => \AdminLang::trans("validationCom.status." . $statusForOutput), "label" => $validationCom->getStatusColor($statusForOutput), "tooltip" => \AdminLang::trans("validationCom.tooltip." . $statusForOutput), "lastUpdated" => $user->validation->updatedAt->diffForHumans()]);
    }

    public function tokenGenerate($JsonResponse, $request)
    {
        $user = \WHMCS\User\User::find($request->get("requestorid"));
        if (!$user) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "status" => \AdminLang::trans("user.notFound")]);
        }
        $validationCom = $this->getValidationCom();
        $validationCom->initiateForUser($user);
        $user->refresh();
        $validationCom->sendVerificationEmail($user);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "viewDetailsUrl" => $validationCom->getViewUrlForUser($user), "status" => \AdminLang::trans("validationCom.status." . $user->validation->status), "label" => $validationCom->getStatusColor($user->validation->status), "tooltip" => \AdminLang::trans("validationCom.tooltip." . $user->validation->status), "lastUpdated" => $user->validation->createdAt->diffForHumans()]);
    }

    public function tokenDelete($JsonResponse, $request)
    {
        $user = \WHMCS\User\User::find($request->get("requestorid"));
        if (!$user) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "status" => \AdminLang::trans("user.notFound")]);
        }
        try {
            $user->validation->delete();
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "status" => \AdminLang::trans("validationCom.error.clearStatus")]);
        }
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }

    public function deactivateService($JsonResponse, $request)
    {
        $this->getValidationCom()->deleteSettings();
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }

    public function configureModal($JsonResponse, $request)
    {
        $responseData = ["body" => view("admin.setup.fraud.partials.validationcom-config"), "disableSubmit" => true];
        return new \WHMCS\Http\Message\JsonResponse($responseData);
    }

    public function configureSave($JsonResponse, $request)
    {
        $validationCom = $this->getValidationCom();
        $validationCom->setEnabled((bool) $request->get("coreEnabled"));
        $validationCom->setAutoEnabled((bool) $request->get("autoEnabled"));
        $validationCom->setUploadTypes((array) $request->get("uploadTypes"));
        $validationCom->saveSettings();
        logAdminActivity("Validation.com service configuration modified.");
        return new \WHMCS\Http\Message\JsonResponse(["dismiss" => true]);
    }

    public function signup($JsonResponse, $request)
    {
        $validationCom = $this->getValidationCom();
        try {
            $responseUrl = $validationCom->initiateSignup();
        } catch (\WHMCS\Exception\License\LicenseError $e) {
            $errorMessage = \AdminLang::trans("validationCom.error.licenseData");
        } catch (\WHMCS\Exception\Module\MalformedResponseException $e) {
            $errorMessage = \AdminLang::trans("validationCom.error.signupUrl");
        }
        if ($errorMessage) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "status" => $errorMessage]);
        }
        $responseUrlHost = parse_url($validationCom->getViewHost(), PHP_URL_HOST);
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "location" => $responseUrl, "display" => strpos($responseUrl, $responseUrlHost) !== false ? "popup" : "modal"]);
    }

    public function eventCallback($ResponseInterface, $request)
    {
        $response = new \Laminas\Diactoros\Response();
        $requestBody = $request->getBody()->getContents();
        logModuleCall("validationCom", "Event Callback", "", $requestBody);
        $validationCom = $this->getValidationCom();
        $eventData = $validationCom->getValidCallbackData($requestBody);
        if (is_null($eventData)) {
            logActivity("Validation.com event callback payload is not valid");
            return $response;
        }
        $trackingId = $eventData["TrackingId"] ?? NULL;
        if ($trackingId && stripos($trackingId, "AgentCreation-") === 0) {
            return $response;
        }
        if (!isset($eventData["Token"]) || !array_key_exists("Status", $eventData)) {
            logActivity("Validation.com event callback is missing required data");
            return $response;
        }
        $validationRecord = \WHMCS\User\User\UserValidation::where("token", $eventData["Token"])->first();
        if (!$validationRecord) {
            logActivity("Validation.com event callback token is invalid: " . $eventData["Token"]);
            return $response;
        }
        switch ($eventData["Event"]) {
            case "documents.submitted":
                $validationRecord->status = ValidationCom::STATUS_SUBMITTED;
                try {
                    $submittedAt = new \WHMCS\Carbon($eventData["DateSubmitted"]);
                } catch (\Throwable $e) {
                    $submittedAt = NULL;
                }
                $validationRecord->submittedAt = $submittedAt;
                break;
            case "documents.reviewed":
                $validationRecord->status = ValidationComApiClient::STATUS_MAP[$eventData["Status"]] ?? ValidationCom::STATUS_NOT_REVIEWED;
                if ($validationRecord->status !== ValidationCom::STATUS_NOT_REVIEWED) {
                    try {
                        $reviewedAt = new \WHMCS\Carbon($eventData["DateReviewed"]);
                    } catch (\Throwable $e) {
                        $reviewedAt = NULL;
                    }
                    $validationRecord->reviewedAt = $reviewedAt;
                }
                break;
            default:
                if ($validationRecord->isDirty()) {
                    $validationRecord->save();
                }
                return new \Laminas\Diactoros\Response();
        }
    }

    public function linkCallback($ResponseInterface, $request)
    {
        $response = new \Laminas\Diactoros\Response();
        $requestBody = $request->getBody()->getContents();
        logModuleCall("validationCom", "Link Callback", "", $requestBody);
        $validationCom = $this->getValidationCom();
        $linkPayload = $validationCom->getValidCallbackData($requestBody);
        if (is_null($linkPayload)) {
            logActivity("Validation.com link failed: invalid callback data or signature");
            return $response;
        }
        if (!(isset($linkPayload["api_client_id"]) && isset($linkPayload["api_client_secret"]) && isset($linkPayload["category_id"]))) {
            logActivity("Validation.com link failed: callback data lacks one or more required attributes");
            return $response;
        }
        $validationCom->setClientAuth($linkPayload["api_client_id"], $linkPayload["api_client_secret"]);
        $validationCom->setCategoryId((int) $linkPayload["category_id"]);
        $validationCom->setEnabled(true);
        $validationCom->setAutoEnabled(true);
        $validationCom->saveSettings();
        return $response;
    }

    public function linkCompleteCallback($request)
    {
        $requestBody = $request->getBody()->getContents();
        logModuleCall("validationCom", "Link Complete Callback", "", $requestBody);
        $html = "<script>\n    var openerWindow = window.opener || window.parent;\n\n    openerWindow.completeValidationComLinkWorkflow();\n</script>";
        return $html;
    }

    public function clientCompleteCallback($request)
    {
        $requestBody = $request->getBody()->getContents();
        logModuleCall("validationCom", "Client Complete Callback", "", $requestBody);
        $html = "<script>\n    var openerWindow = window.opener || window.parent;\n\n    openerWindow.completeValidationComClientWorkflow();\n</script>";
        return $html;
    }
}

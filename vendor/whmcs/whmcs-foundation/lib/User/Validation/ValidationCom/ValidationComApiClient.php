<?php

namespace WHMCS\User\Validation\ValidationCom;

class ValidationComApiClient
{
    private $useSandboxApi = NULL;
    private $guzzle = NULL;
    private $clientId = NULL;
    private $clientSecret = NULL;
    const SIGNUP_URL_PRODUCTION = "https://validation.com/";
    const SIGNUP_URL_SANDBOX = "https://sandbox.validation.com/";
    const API_URL_PRODUCTION = "https://api.validation.com/";
    const API_URL_SANDBOX = "https://sandboxapi.validation.com/";
    const SUBMIT_HOST_PRODUCTION = "https://submit.validation.com";
    const SUBMIT_HOST_SANDBOX = "https://sandboxsubmit.validation.com";
    const VIEW_HOST_PRODUCTION = "https://partner.validation.com";
    const VIEW_HOST_SANDBOX = "https://sandboxpartner.validation.com";
    const STATUS_MAP = NULL;

    public function __construct($clientId = NULL, $clientSecret = NULL, $useSandboxApi = false)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->useSandboxApi = $useSandboxApi;
    }

    protected function getHttpClient($Client)
    {
        if (!$this->guzzle) {
            $this->guzzle = new \GuzzleHttp\Client([\GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false, "base_uri" => $this->useSandboxApi ? self::API_URL_SANDBOX : self::API_URL_PRODUCTION]);
        }
        return $this->guzzle;
    }

    protected function apiCall($action = NULL, $postData = NULL, $extraHeaders)
    {
        $params = [\GuzzleHttp\RequestOptions::TIMEOUT => 30, \GuzzleHttp\RequestOptions::HEADERS => []];
        if ($this->clientId) {
            $params[\GuzzleHttp\RequestOptions::HEADERS]["clientId"] = $this->clientId;
            $params[\GuzzleHttp\RequestOptions::HEADERS]["clientSecret"] = $this->clientSecret;
        }
        $method = "GET";
        if (!is_null($postData)) {
            $params[\GuzzleHttp\RequestOptions::BODY] = json_encode($postData);
            $params[\GuzzleHttp\RequestOptions::HEADERS]["Content-Type"] = "application/json";
            $method = "POST";
        }
        if (!is_null($extraHeaders)) {
            $params[\GuzzleHttp\RequestOptions::HEADERS] = array_merge($params[\GuzzleHttp\RequestOptions::HEADERS], $extraHeaders);
        }
        $action = ltrim($action, "/");
        $responseContent = $this->getHttpClient()->request($method, $action, $params)->getBody()->getContents();
        $responseData = json_decode($responseContent, true);
        logModuleCall("validationCom", $action, "", $responseContent, $responseData);
        if (is_null($responseData)) {
            throw new \WHMCS\Exception("Invalid response from ValidationCom: " . $responseContent);
        }
        return $responseData;
    }

    protected function getValidationCom($ValidationCom)
    {
        return new ValidationCom();
    }

    protected function getTimeNow($Carbon)
    {
        return \WHMCS\Carbon::now();
    }

    public function initiateSignup($licenseAuthPayload)
    {
        $data = ["authenticity_payload" => $licenseAuthPayload, "category" => "Orders", "category_webhook_url" => fqdnRoutePath("validation_com-event-callback"), "email_address" => \WHMCS\User\Admin::getAuthenticatedUser()->email, "link_webhook_url" => fqdnRoutePath("validation_com-link-callback"), "link_completion_redirect_url" => fqdnRoutePath("validation_com-link-complete-callback"), "client_completion_redirect_url" => fqdnRoutePath("validation_com-client-complete-callback"), "date" => $this->getTimeNow()->toIso8601String()];
        foreach ($this->getValidationCom()->getPromos() as $key => $value) {
            $data["promo_" . $key] = (int) $value;
        }
        $signupPayload = base64_encode(json_encode($data));
        $signupSignature = $this->getValidationCom()->signPayload($signupPayload);
        $signupRequest = $signupPayload . "." . $signupSignature;
        $response = $this->getHttpClient()->request("POST", "whmcs.php", ["base_uri" => $this->useSandboxApi ? self::SIGNUP_URL_SANDBOX : self::SIGNUP_URL_PRODUCTION, "body" => $signupRequest]);
        $redirectUri = $response->getHeaderLine("Location");
        if (!$redirectUri) {
            throw new \WHMCS\Exception\Module\MalformedResponseException("Invalid response from ValidationComApiClient signup initiation");
        }
        return $redirectUri;
    }

    public function getCategories()
    {
        return $this->apiCall("categories");
    }

    public function generateToken($categoryId, $trackingId, $uploadTypes)
    {
        $postFields = ["UploadTypesIds" => $uploadTypes, "CategoryId" => $categoryId, "TrackingId" => $trackingId];
        $response = $this->apiCall("tokens/generate", $postFields);
        if (!isset($response["token"])) {
            throw new \WHMCS\Exception("Invalid token in response from Validation.com");
        }
        return (string) $response["token"];
    }

    public function getTokenStatus($token)
    {
        $response = $this->apiCall("tokens/status", NULL, ["token" => $token]);
        if (is_null($response["status"])) {
            $statusString = ValidationCom::STATUS_NOT_REVIEWED;
        } else {
            $statusString = self::STATUS_MAP[$response["status"]] ?? ValidationCom::STATUS_NOT_REVIEWED;
        }
        return ["status" => $statusString, "reviewed_at" => $response["dateReviewed"] ? new \WHMCS\Carbon($response["dateReviewed"]) : NULL, "submitted_at" => $response["dateSubmitted"] ? new \WHMCS\Carbon($response["dateSubmitted"]) : NULL];
    }

    public function getSubmitHost()
    {
        return $this->useSandboxApi ? self::SUBMIT_HOST_SANDBOX : self::SUBMIT_HOST_PRODUCTION;
    }

    public function getViewHost()
    {
        return $this->useSandboxApi ? self::VIEW_HOST_SANDBOX : self::VIEW_HOST_PRODUCTION;
    }
}

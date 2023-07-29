<?php

namespace WHMCS\User\Validation\ValidationCom;

class ValidationCom implements \WHMCS\User\Validation\UserValidationInterface
{
    use \WHMCS\User\Validation\UserValidationClientBannerTrait;
    protected $settings = NULL;
    protected $apiClient = NULL;
    protected $domain = NULL;
    const CLIENT_BANNER_DISMISS_VAR_NAME = "DismissUserValidationBanner";
    const STATUS_FAILED = "failed";
    const STATUS_REVIEW_REQUESTED = "reviewRequested";
    const STATUS_VALIDATED = "validated";
    const STATUS_NOT_REVIEWED = "notReviewed";
    const STATUS_SUBMITTED = "submitted";
    const STATUS_NOT_REQUESTED = "notRequested";
    const UPLOAD_TYPE_PHOTO_ID = 1;
    const UPLOAD_TYPE_PASSPORT = 2;
    const UPLOAD_TYPE_SELFIE = 3;
    const UPLOAD_TYPE_UTILITY_BILL = 5;
    const UPLOAD_TYPE_CC_FRONT = 6;
    const UPLOAD_TYPE_PHOTO_ID_FRONT = 7;
    const UPLOAD_TYPE_COMPANY_ID = 8;
    const UPLOAD_TYPE_BUSINESS_CARD = 9;
    const UPLOAD_TYPE_DL = 10;
    const UPLOAD_TYPE_DL_FRONT = 11;
    const UPLOAD_TYPE_2ND_PHOTO_ID = 12;
    const UPLOAD_TYPE_2ND_PHOTO_ID_FRONT = 13;
    const UPLOAD_TYPE_PROOF_OF_PAYMENT = 14;
    const UPLOAD_TYPE_TOP_HALF_UTILITY = 17;
    const UPLOAD_TYPES_MAP = NULL;
    const UPLOAD_TYPES_DEFAULT = NULL;
    const SETTING_NAME = "UserValidation";

    public function __construct($settings = NULL)
    {
        $this->settings = $settings ?? $this->loadSettings();
    }

    protected function loadSettings()
    {
        return json_decode(decrypt(\WHMCS\Config\Setting::getValue(static::SETTING_NAME)), true) ?? [];
    }

    public function discoverDomain()
    {
        $systemUrl = \WHMCS\Config\Setting::getValue("SystemURL");
        if (strlen($systemUrl) == 0) {
            return "";
        }
        $host = parse_url($systemUrl, PHP_URL_HOST);
        if (is_null($host) || $host === false) {
            return "";
        }
        return $host;
    }

    public function getDomain()
    {
        return $this->ensureDomain();
    }

    public function setDomain($self, $domain)
    {
        if (!$this->isValidDomain($domain)) {
            throw new \InvalidArgumentException("Invalid domain name");
        }
        $this->domain = $domain;
        return $this;
    }

    protected function isValidDomain($domain)
    {
        return 0 < strlen($domain) && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    protected function assertDomain()
    {
        if (strlen($this->domain) == 0) {
            throw new \WHMCS\Exception\Module\MalformedResponseException("The licensed domain was not found. The System URL may be invalid.");
        }
        return $this->domain;
    }

    protected function ensureDomain()
    {
        if (is_null($this->domain)) {
            try {
                $this->setDomain($this->discoverDomain());
            } catch (\InvalidArgumentException $e) {
            }
            $this->assertDomain();
        }
        return $this->domain;
    }

    public function getClientAuth()
    {
        return ["clientId" => $this->settings["ClientId"] ?? "", "clientSecret" => str_repeat("*", strlen($this->settings["ClientSecret"] ?? ""))];
    }

    public function setClientAuth($clientId, $clientSecret)
    {
        $this->settings["ClientId"] = $clientId;
        $this->settings["ClientSecret"] = $clientSecret;
    }

    public function getCategoryId()
    {
        if (isset($this->settings["CategoryId"]) && is_numeric($this->settings["CategoryId"])) {
            return (int) $this->settings["CategoryId"];
        }
        return NULL;
    }

    public function setCategoryId($categoryId)
    {
        $this->settings["CategoryId"] = $categoryId;
    }

    public function updateCategoryList()
    {
        $categories = $this->getApiClient()->getCategories();
        $this->settings["categories"] = $categories;
    }

    public function saveSettings()
    {
        \WHMCS\Config\Setting::setValue(static::SETTING_NAME, encrypt(json_encode($this->settings)));
    }

    protected function getApiClient($ValidationComApiClient)
    {
        if (!$this->apiClient) {
            $this->apiClient = new ValidationComApiClient($this->settings["ClientId"] ?? NULL, $this->settings["ClientSecret"] ?? NULL, (bool) \DI::make("config")->use_validation_com_sandbox);
        }
        return $this->apiClient;
    }

    public function deleteSettings()
    {
        \WHMCS\Config\Setting::deleteValue(static::SETTING_NAME);
    }

    public function isSignedUp()
    {
        return !empty($this->settings);
    }

    public function isEnabled()
    {
        return (bool) ($this->settings["coreEnabled"] ?? false);
    }

    public function setEnabled($value)
    {
        $this->settings["coreEnabled"] = $value;
    }

    public function isAutoEnabled()
    {
        return (bool) ($this->settings["autoEnabled"] ?? false);
    }

    public function setAutoEnabled($value)
    {
        $this->settings["autoEnabled"] = $value;
    }

    public function getUploadTypes()
    {
        return $this->settings["uploadTypes"] ?? self::UPLOAD_TYPES_DEFAULT;
    }

    public function setUploadTypes($value)
    {
        $validUploadTypes = array_keys(self::UPLOAD_TYPES_MAP);
        $uploadTypes = array_map(function ($item) use($validUploadTypes) {
            if (!is_numeric($item)) {
                throw new \WHMCS\Exception("Invalid upload type");
            }
            $item = (int) $item;
            if (!in_array($item, $validUploadTypes)) {
                throw new \WHMCS\Exception("Invalid upload type value");
            }
            return $item;
        }, $value);
        if (empty($uploadTypes)) {
            $uploadTypes = self::UPLOAD_TYPES_DEFAULT;
        }
        $this->settings["uploadTypes"] = $uploadTypes;
    }

    protected function getLicenseAuthToken()
    {
        $domain = $this->ensureDomain();
        $domainTokens = \DI::make("license")->getKeyData("DomainAuthenticityTokens");
        if (isset($domainTokens[$domain]["validation.com"])) {
            return $domainTokens[$domain]["validation.com"];
        }
        return NULL;
    }

    protected function assertLicenseAuthToken()
    {
        $token = $this->getLicenseAuthToken();
        if (is_null($token)) {
            throw new \WHMCS\Exception\License\LicenseError("License information for validation.com not found. The System URL may be invalid.");
        }
        return $token;
    }

    public function getPromos()
    {
        $promos = \DI::make("license")->getKeyData("Promos");
        if (isset($promos["validation.com"])) {
            return $promos["validation.com"];
        }
        return [];
    }

    protected function decodeLicenseAuthPayload($licenseAuthToken)
    {
        list($licenseEncAuthPayload) = explode(".", $licenseAuthToken, 2);
        return json_decode(base64_decode($licenseEncAuthPayload), true);
    }

    public function signPayload($payload = NULL, $extraAuthenticityPayload)
    {
        $licAuthToken = $this->getLicenseAuthToken();
        list($signingKey) = explode(".", $licAuthToken, 2);
        if (!is_null($extraAuthenticityPayload)) {
            $signingKey = hash_hmac("sha256", $extraAuthenticityPayload, $signingKey);
        }
        return hash_hmac("sha256", $payload, $signingKey);
    }

    public function getValidCallbackData($callbackData)
    {
        list($encodedPayload, $dataSignature) = explode(".", $callbackData, 2);
        $payload = json_decode(base64_decode($encodedPayload), true);
        if (!isset($payload["authenticity_payload"])) {
            return NULL;
        }
        $calculatedSignature = $this->signPayload($encodedPayload, $payload["authenticity_payload"]);
        if (!hash_equals($calculatedSignature, $dataSignature)) {
            return NULL;
        }
        return $payload;
    }

    public function initiateSignup()
    {
        return $this->getApiClient()->initiateSignup($this->decodeLicenseAuthPayload($this->assertLicenseAuthToken()));
    }

    public function initiateForUser($user)
    {
        $trackingId = "WHMCS-" . $user->id;
        $uploadTypes = $this->settings["uploadTypes"] ?? self::UPLOAD_TYPES_DEFAULT;
        $categoryId = $this->getCategoryId();
        if (is_null($categoryId)) {
            throw new \WHMCS\Exception("Missing category from Validation.com, cannot initiate request");
        }
        if (!$user->validation) {
            $validationRecord = new \WHMCS\User\User\UserValidation();
            $validationRecord->requestorId = $user->id;
            $validationRecord->token = $this->getApiClient()->generateToken($categoryId, $trackingId, $uploadTypes);
            $validationRecord->status = self::STATUS_NOT_REVIEWED;
            $validationRecord->save();
            $user->validation()->save($validationRecord);
        }
    }

    public function sendVerificationEmail($user)
    {
        try {
            $token = $user->validation->token ?? NULL;
            if (!$token) {
                throw new \WHMCS\Exception\Mail\SendFailure("No verification token associated with User.");
            }
            $emailer = \WHMCS\Mail\Emailer::factoryByTemplate("User Identity Verification", $user->id, ["verification_url" => $this->getSubmitHost() . "/submit/" . $token]);
            $emailer->send();
        } catch (\WHMCS\Exception\Mail\SendHookAbort $e) {
            logActivity("User Identity Verification Message Sending Aborted by Hook - UserID: " . $user->id);
        } catch (\WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            logActivity("User Identity Verification Message Sending Aborted by Configuration - UserID: " . $user->id);
        } catch (\WHMCS\Exception\Mail\SendFailure $e) {
            logActivity("Could not send User Identity Verification message to " . $user->fullName . ". Error: " . $e->getMessage());
            return false;
        }
        return true;
    }

    public function refreshStatusForUser($user)
    {
        if (!$user->validation) {
            return NULL;
        }
        $apiClient = $this->getApiClient();
        $statusData = $apiClient->getTokenStatus($user->validation->token);
        $user->validation->status = $statusData["status"];
        if ($statusData["submitted_at"]) {
            $user->validation->submittedAt = $statusData["submitted_at"];
        }
        if (in_array($user->validation->status, [self::STATUS_FAILED, self::STATUS_VALIDATED], true)) {
            $user->validation->reviewedAt = $statusData["reviewed_at"];
        }
        $user->validation->save();
    }

    public function isRequestComplete($user)
    {
        return $user->validation instanceof \WHMCS\User\User\UserValidation && !empty($user->validation->reviewedAt) && in_array($user->validation->status, [self::STATUS_FAILED, self::STATUS_VALIDATED]);
    }

    public function getSubmitHost()
    {
        return $this->getApiClient()->getSubmitHost();
    }

    public function getViewHost()
    {
        return $this->getApiClient()->getViewHost();
    }

    public function getSubmitUrlForUser($user)
    {
        $token = $user->validation->token ?? NULL;
        return $token ? $this->getSubmitHost() . "/submit-frame/" . $token : NULL;
    }

    public function getViewUrlForUser($user)
    {
        $token = $user->validation->token ?? NULL;
        return $token ? $this->getApiClient()->getViewHost() . "/submission-detail/" . $token : NULL;
    }

    public function getStatusColor($status)
    {
        switch ($status) {
            case "notReviewed":
                $statusColor = "info";
                break;
            case "reviewRequested":
                $statusColor = "warning";
                break;
            case "validated":
                $statusColor = "success";
                break;
            case "failed":
                $statusColor = "danger";
                break;
            case "notRequested":
            default:
                $statusColor = "default";
                return $statusColor;
        }
    }

    public function getStatusForOutput($user)
    {
        $userValidation = $user->validation;
        $validationStatus = "notRequested";
        $overriddenStatuses = ["submitted", "notReviewed"];
        if ($userValidation) {
            if ($userValidation->status && !in_array($userValidation->status, $overriddenStatuses)) {
                $validationStatus = $userValidation->status;
            } else {
                if ($userValidation->submittedAt) {
                    $validationStatus = "reviewRequested";
                } else {
                    $validationStatus = "notReviewed";
                }
            }
        }
        return $validationStatus;
    }
}

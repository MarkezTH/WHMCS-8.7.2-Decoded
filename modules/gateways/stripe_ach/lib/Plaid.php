<?php

namespace WHMCS\Module\Gateway\StripeAch;

class Plaid
{
    protected $clientId = "";
    protected $secretKey = "";
    protected $environment = NULL;
    protected $existingToken = "";
    protected $linkToken = "";
    const API_ENDPOINT_SUFFIX = ".plaid.com";
    const PLAID_JS_URL = "https://cdn.plaid.com/link/v2/stable/link-initialize.js";
    const ENV_SANDBOX = "sandbox";
    const ENV_DEVELOPMENT = "development";
    const ENV_PRODUCTION = "production";
    const ALLOWED_ENVIRONMENTS = NULL;

    public static function factory($params)
    {
        $plaid = new self();
        $plaid->initialiseParams($params);
        return $plaid;
    }

    public function initialiseParams($params)
    {
        if (empty($params)) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration("No params provided");
        }
        if (!empty($params["plaidClientId"])) {
            $this->setClientIdKey($params["plaidClientId"]);
        }
        if (!empty($params["plaidSecret"])) {
            $this->setSecretKey($params["plaidSecret"]);
        }
        if (!empty($params["plaidMode"])) {
            $this->setEnvironment($params["plaidMode"]);
        }
        return $this;
    }

    public function setEnvironment($value)
    {
        if ($value) {
            $value = strtolower(trim($value));
        }
        if ($this->isValidEnvironmentMode($value)) {
            $this->environment = $value;
            return $this;
        }
        throw new Exception\InvalidEnvironment($value . " is not a valid environment. Should be one of: " . implode(", ", self::ALLOWED_ENVIRONMENTS));
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function setSecretKey($value)
    {
        $this->secretKey = $value;
        return $this;
    }

    public function getSecretKey()
    {
        return $this->secretKey;
    }

    public function setClientIdKey($value)
    {
        $this->clientId = $value;
        return $this;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function setExistingToken($value)
    {
        $this->existingToken = $value;
        return $this;
    }

    public function getExistingToken()
    {
        return $this->existingToken;
    }

    public function setStoredLinkToken($token)
    {
        $this->linkToken = $token;
        return $this;
    }

    public function getStoredLinkToken()
    {
        return $this->linkToken;
    }

    protected function isValidEnvironmentMode($value)
    {
        return in_array($value, self::ALLOWED_ENVIRONMENTS);
    }

    public function getApiEndpoint()
    {
        if ($this->getEnvironment() && $this->isValidEnvironmentMode($this->getEnvironment())) {
            return "https://" . $this->getEnvironment() . self::API_ENDPOINT_SUFFIX . "/";
        }
        throw new \WHMCS\Exception\Module\InvalidConfiguration("Plaid environment not set");
    }

    public function getPlaidLinkJsLink()
    {
        return self::PLAID_JS_URL;
    }

    protected function getCountryCode()
    {
        if (\Auth::user()) {
            \Auth::user();
            switch (\Auth::user()->language) {
                case "french":
                    return "fr";
                    break;
                case "spanish":
                    return "es";
                    break;
                case "dutch":
                    return "nl";
                    break;
                default:
                    return "en";
            }
        } else {
            return "en";
        }
    }

    protected function getLinkToken($forceNew)
    {
        if ($this->getStoredLinkToken() && !$forceNew) {
            return $this->getStoredLinkToken();
        }
        $httpClient = $this->getHttpClient();
        $response = $httpClient->post("link/token/create", [\GuzzleHttp\RequestOptions::JSON => ["client_id" => $this->getClientId(), "secret" => $this->getSecretKey(), "client_name" => \WHMCS\Config\Setting::getValue("CompanyName"), "user" => ["client_user_id" => (string) \Auth::client()->id], "products" => ["auth"], "country_codes" => [\Auth::client()->country], "language" => $this->getCountryCode()], \GuzzleHttp\RequestOptions::HEADERS => ["Content-Type" => "application/json"]]);
        if (200 <= $response->getStatusCode() && $response->getStatusCode() < 300) {
            $responseData = json_decode($response->getBody()->getContents(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->setStoredLinkToken($responseData["link_token"]);
            }
        }
        return $this->getStoredLinkToken();
    }

    public function getHttpClient($HttpClient, $exceptions)
    {
        return new \WHMCS\Http\Client\HttpClient([\GuzzleHttp\RequestOptions::HTTP_ERRORS => $exceptions, \GuzzleHttp\RequestOptions::VERIFY => true, "base_uri" => $this->getApiEndpoint()]);
    }

    public function getJavascriptOutput()
    {
        $existingToken = \WHMCS\Input\Sanitize::escapeSingleQuotedString($this->getExistingToken());
        $environment = \WHMCS\Input\Sanitize::escapeSingleQuotedString($this->getEnvironment());
        $companyName = \WHMCS\Input\Sanitize::escapeSingleQuotedString(\WHMCS\Config\Setting::getValue("CompanyName"));
        $achJs = \DI::make("asset")->getWebRoot() . "/modules/gateways/stripe_ach/stripe_ach.min.js?a=" . time();
        return "<script src=\"" . $this->getPlaidLinkJsLink() . "\"></script>\n<script type=\"text/javascript\" src=\"" . $achJs . "\"></script>\n<script type=\"text/javascript\">\n\nvar existingToken = '" . $existingToken . "',\n    plaidEnvironment = '" . $environment . "',\n    plaidLinkToken = '" . $this->getLinkToken(true) . "',\n    companyName = '" . $companyName . "';\n\n\$(document).ready(function() {\n    initStripeACH();\n});    \n</script>";
    }
}

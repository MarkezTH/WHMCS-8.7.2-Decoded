<?php

namespace WHMCS\MarketConnect;

class Api
{
    private static $loggedErrors = [];
    const MARKETPLACE_LIVE_URL = "https://marketplace.whmcs.com/api/";
    const MARKETPLACE_TESTING_URL = "https://testing.marketplace.whmcs.com/api/";
    const MARKETPLACE_LOCAL_TESTING_URL = "http://localhost:8000/api/";
    const MARKETPLACE_API_VERSION = "v1";

    public function link($email, $password, $licenseKey, $agreetos)
    {
        return $this->post("/link", ["email" => $email, "password" => $password, "license_key" => $licenseKey, "agree_tos" => $agreetos], 15);
    }

    public function register($firstname, $lastname, $company, $email, $password, $licenseKey, $agreetos)
    {
        return $this->post("/register", ["first_name" => $firstname, "last_name" => $lastname, "company_name" => $company, "email" => $email, "password" => $password, "license_key" => $licenseKey, "agree_tos" => $agreetos], 20);
    }

    public function balance()
    {
        return $this->get("/balance", [], 10);
    }

    public function services()
    {
        return $this->get("/services");
    }

    public function servicesOnly()
    {
        return $this->normalizeServicesResponse($this->services());
    }

    protected function normalizeServicesResponse($servicesApiResponse)
    {
        if (!is_array($servicesApiResponse)) {
            return [];
        }
        foreach ($servicesApiResponse as $key => $unknown) {
            if (!is_array($unknown)) {
                unset($servicesApiResponse[$key]);
            }
        }
        return $servicesApiResponse;
    }

    public function activate($service)
    {
        return $this->post("/services/activate", ["service" => $service]);
    }

    public function predeactivate($service)
    {
        return $this->post("/services/predeactivate", ["service" => $service]);
    }

    public function deactivate($service)
    {
        return $this->post("/services/deactivate", ["service" => $service]);
    }

    public function purchase($service, $term, $quantity)
    {
        return $this->post("/order", ["service" => $service, "term" => $term, "qty" => $quantity]);
    }

    public function configure($configurationData)
    {
        return $this->post("/order/configure", $configurationData);
    }

    public function renew($orderNumber, $term, $callbackUrl = "", $useInstantIssuance = false)
    {
        $params = array_merge(["order_number" => $orderNumber, "term" => $term, "callback_url" => $callbackUrl, "use_request_token" => $useInstantIssuance ? 1 : 0]);
        return $this->post("/order/renew", $params);
    }

    public function extra($function, $params = [])
    {
        return $this->post("/order/" . $function, $params);
    }

    public function cancel($orderNumber)
    {
        return $this->post("/order/cancel", ["order_number" => $orderNumber]);
    }

    public function status($orderNumber)
    {
        return $this->get("/order/" . $orderNumber);
    }

    public function sso()
    {
        return $this->get("/sso");
    }

    public function ssoForService($service)
    {
        return $this->get("/sso/" . $service);
    }

    public function ssoForOrder($orderNumber)
    {
        return $this->get("/order/sso/" . $orderNumber);
    }

    public function upgrade($orderNumber, $service, $term, $quantity)
    {
        return $this->post("/order/upgrade", ["order_number" => $orderNumber, "service" => $service, "term" => $term, "qty" => $quantity]);
    }

    public function validateCompetitiveUpgrade($url)
    {
        return $this->post("/service/symantec/validatecompetitiveupgrade", ["url" => $url]);
    }

    public function testCodeGuardWebsiteConnection($params)
    {
        return $this->post("/service/codeguard/testwebsiteconnection", $params);
    }

    protected function get($action, $data = [], $timeout = NULL)
    {
        return $this->call($action, "GET", $data, $timeout);
    }

    protected function post($action, $data = [], $timeout = NULL)
    {
        return $this->call($action, "POST", $data, $timeout);
    }

    protected function useMarketplaceTestingEnv()
    {
        return (bool) \App::getApplicationConfig()->use_marketplace_testing_env;
    }

    protected function useMarketplaceLocalTestingEnv()
    {
        return (bool) \App::getApplicationConfig()->use_marketplace_local_testing_env;
    }

    protected function getApiUrl()
    {
        if ($this->useMarketplaceLocalTestingEnv()) {
            return self::MARKETPLACE_LOCAL_TESTING_URL . self::MARKETPLACE_API_VERSION;
        }
        if ($this->useMarketplaceTestingEnv()) {
            return self::MARKETPLACE_TESTING_URL . self::MARKETPLACE_API_VERSION;
        }
        return self::MARKETPLACE_LIVE_URL . self::MARKETPLACE_API_VERSION;
    }

    protected function call($action, $method, $data, $timeout = 300)
    {
        if ($action != "/link" && $action != "/register" && !MarketConnect::isAccountConfigured()) {
            throw new Exception\AuthNotConfigured("Authentication failed. Please navigate to Configuration <i class=“far fa-wrench” aria-hidden=\"true\"></i> > System Settings > MarketConnect to resolve.");
        }
        $data["whmcs_company_name"] = \WHMCS\Config\Setting::getValue("CompanyName");
        $data["whmcs_company_email"] = \WHMCS\Config\Setting::getValue("Email");
        $data["whmcs_company_url"] = \WHMCS\Config\Setting::getValue("Domain");
        $data["whmcs_version"] = \App::getVersion()->getVersion();
        $apiBearerToken = MarketConnect::getApiBearerToken();
        if (0 < strlen($apiBearerToken) && !ctype_graph($apiBearerToken)) {
            $message = "Failed to decrypt MarketConnect API token.Please check your CC encryption hash or disconnect and reconnect your MarketConnect account.";
            if (!in_array($message, self::$loggedErrors)) {
                logActivity($message);
                self::$loggedErrors[] = $message;
            }
            throw new Exception\AuthError($message);
        }
        $curl = curl_init();
        $curlUrl = $this->getApiUrl() . $action;
        switch ($method) {
            case "GET":
                $curlUrl .= "?" . http_build_query($data);
                break;
            default:
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                $headers = ["Authorization: Bearer " . $apiBearerToken];
                if ($this->useMarketplaceLocalTestingEnv()) {
                    $headers[] = "Cookie: XDEBUG_SESSION=XDEBUG_ECLIPSE";
                }
                curl_setopt_array($curl, [CURLOPT_URL => $curlUrl, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 1, CURLOPT_TIMEOUT => $timeout, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => $headers]);
                $response = curl_exec($curl);
                $curlError = curl_error($curl);
                $curlErrorNum = curl_errno($curl);
                $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                $decoded = json_decode($response, true) ?? [];
                $replaceVars = [];
                if (array_key_exists("password", $data)) {
                    $replaceVars[] = $data["password"];
                }
                if (array_key_exists("token", $decoded)) {
                    $replaceVars[] = $decoded["token"];
                }
                $responseToLog = $response;
                if (!$responseToLog && $curlError) {
                    $responseToLog = "CURL Error " . $curlErrorNum . " - " . $curlError;
                }
                logModuleCall("marketconnect", $action, $data, $responseToLog, $decoded, $replaceVars);
                if ($curlError) {
                    throw new Exception\ConnectionError("Error Code: " . $curlErrorNum . " - " . $curlError);
                }
                if ($responseCode == 401) {
                    throw new Exception\AuthError($decoded["error"] ?? "Login Failed", $responseCode);
                }
                if ($responseCode != 200) {
                    $errorMessage = $decoded["error"] ?? "Unknown Error";
                    if (!empty($decoded["previousExceptionMessage"])) {
                        $previousExceptionData = json_decode($decoded["previousExceptionMessage"], true) ?? [];
                        $previousMessage = $previousExceptionData[0]["message"] ?? $decoded["previousExceptionMessage"];
                        $errorMessage .= " " . $previousMessage;
                    }
                    throw new Exception\GeneralError($errorMessage, $responseCode);
                }
                return $decoded;
        }
    }

    public function getThreeSixtyMonitoringSiteCheckProbes()
    {
        return $this->post("/service/threesixtymonitoring/sitecheckprobes");
    }

    public function performThreeSixtyMonitoringSiteCheck($url, $probeId)
    {
        return $this->post("/service/threesixtymonitoring/sitecheck", ["url" => $url, "probe_id" => $probeId]);
    }
}

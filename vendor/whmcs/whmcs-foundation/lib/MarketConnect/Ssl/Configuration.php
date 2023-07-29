<?php

namespace App\Games\Kernel;

class _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F4D61726B6574436F6E6E6563742F53736C2F436F6E66696775726174696F6E2E7068703078376630316332633665346630_
{
    public $firstName = "";
    public $lastName = "";
    public $email = "";
    public $phoneNumber = "";
}
class _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F4D61726B6574436F6E6E6563742F53736C2F436F6E66696775726174696F6E2E7068703078376630316332633665636234_
{
    public $companyName = "";
    public $address1 = "";
    public $address2 = "";
    public $city = "";
    public $state = "";
    public $postcode = "";
    public $country = "";
    public $phoneNumber = "";
}
namespace WHMCS\MarketConnect\Ssl;

class Configuration
{
    protected $ssl = NULL;
    protected $payload = NULL;
    protected $finalized = false;
    const MARKETCONNECT_DCV_EMAIL = "email";
    const MARKETCONNECT_DCV_FILE = "http-token";
    const MARKETCONNECT_DCV_DNS = "dns-txt-token";

    public function __construct(\WHMCS\Service\Ssl $ssl)
    {
        $this->ssl = $ssl;
        $this->payload = [];
    }

    public function populate()
    {
        $this->setDomain($this->getDomain());
        $this->order($this->ssl->getOrderNumber());
        return $this;
    }

    public function getDomain()
    {
        return $this->productDomain($this->ssl->getDomain());
    }

    public function productDomain($domain)
    {
        if ($this->ssl->isWildcard() && strpos($domain, "*") === false) {
            if (strpos($domain, "www.") === 0) {
                $domain = substr($domain, 4);
            }
            $domain = "*." . $domain;
        }
        return $domain;
    }

    public function finalize()
    {
        if ($this->isFinalized()) {
            return $this->payload;
        }
        if (!isset($this->payload["dcv_method"]) && $this->getDomainValidationEmail() != "") {
            $this->payload["dcv_method"] = static::MARKETCONNECT_DCV_EMAIL;
        }
        if (!isset($this->payload["fileauth"])) {
            $this->payload["fileauth"] = false;
        }
        $this->payload["callback_url"] = fqdnRoutePath("store-ssl-callback");
        $this->finalized = true;
        return $this->payload;
    }

    public function isFinalized()
    {
        return $this->finalized;
    }

    protected function setDomain($domain)
    {
        $this->payload["domain"] = $domain;
    }

    public function domain($self, $domain)
    {
        $this->setDomain($this->productDomain($domain));
        return $this;
    }

    public function order($self, $number)
    {
        $this->payload["order_number"] = $number;
        return $this;
    }

    public function certificateSigningRequest($self, $blob, $server)
    {
        $this->payload["csr"] = $blob;
        $this->payload["servertype"] = $this->getMarketplaceServerType($server);
        return $this;
    }

    public function contactsFromParams($self, $params)
    {
        $default = ["title" => $params["configdata"]["jobtitle"], "firstname" => $params["configdata"]["firstname"], "lastname" => $params["configdata"]["lastname"], "email" => $params["configdata"]["email"], "phone" => $params["configdata"]["phonenumber"]];
        foreach (["admin", "tech", "billing"] as $contact) {
            $this->payload[$contact] = $default;
        }
        return $this;
    }

    public function organisationFromParams($self, $params)
    {
        $this->payload["org"] = ["name" => $params["configdata"]["orgname"], "address1" => $params["configdata"]["address1"], "address2" => $params["configdata"]["address2"], "city" => $params["configdata"]["city"], "state" => $params["configdata"]["state"], "postcode" => $params["configdata"]["postcode"], "country" => $params["configdata"]["country"], "phone" => $params["configdata"]["phonenumber"]];
        return $this;
    }

    public function getSecondLevelDomainOnly()
    {
        return preg_replace("/(\\*\\.|www\\.)(.*)/", "\\2", $this->getDomain());
    }

    public function emailUser($user)
    {
        return sprintf("%s@%s", $user, $this->getSecondLevelDomainOnly());
    }

    public function getMarketplaceServerType($serverType)
    {
        if ($serverType == "1031") {
            return "cpanel";
        }
        if ($serverType == "1030") {
            return "plesk";
        }
        if ($serverType == "1013" || $serverType == "1014") {
            return "iis";
        }
        $validWebServerTypes = ["cpanel", "plesk", "apache2", "apacheopenssl", "apacheapachessl", "iis"];
        if (in_array($serverType, $validWebServerTypes)) {
            return $serverType;
        }
        return "other";
    }

    public function includeProvisioningModule($self, $module)
    {
        $this->payload["server_module"] = $module->getLoadedModule();
        return $this;
    }

    public function validateDomainEmailFromParams($self, $params = "", $default)
    {
        $email = $default;
        if (!empty($params["configdata"]["approveremail"])) {
            $email = $params["configdata"]["approveremail"];
        } else {
            if (!empty($params["approveremail"])) {
                $email = $params["approveremail"];
            }
        }
        $this->validateDomainEmail($email);
        return $this;
    }

    public function validateDomainEmail($self, $email)
    {
        $this->payload["approveremail"] = $email;
        return $this;
    }

    public function validateDomainFile()
    {
        $this->payload["fileauth"] = true;
        $this->payload["dcv_method"] = static::MARKETCONNECT_DCV_FILE;
        return $this;
    }

    public function validateDomainDns()
    {
        $this->payload["dcv_method"] = static::MARKETCONNECT_DCV_DNS;
        return $this;
    }

    public function validationMethodFromParams($self, $params)
    {
        $method = \WHMCS\Service\Ssl::normalizeToValidationMethod($params["configdata"]["approvalmethod"] ?? NULL);
        switch ($method) {
            case \WHMCS\Service\Ssl::DOMAIN_VALIDATION_FILE:
                $this->validateDomainFile();
                break;
            case \WHMCS\Service\Ssl::DOMAIN_VALIDATION_DNS:
                $this->validateDomainDns();
                break;
            default:
                return $this;
        }
    }

    public function cleanForPersistence()
    {
        $payload = $this->payload;
        $ignore = ["callback_url"];
        foreach ($ignore as $v) {
            unset($payload[$v]);
        }
        return $payload;
    }

    public function contactsFromProperties($self, $properties, \WHMCS\User\Client $client)
    {
        if ($client == NULL) {
            _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F6D61726B6574636F6E6E6563742F73736C2F636F6E66696775726174696F6E2E7068703078376630316332633665346630_();
            $client = $easytoyou_error_decompile;
        }
        $default = ["title" => $properties->get("Certificate Contact Title") ?: "Mr", "firstname" => $properties->get("Certificate Contact First Name") ?: $client->firstName, "lastname" => $properties->get("Certificate Contact Last Name") ?: $client->lastName, "email" => $properties->get("Certificate Contact Email") ?: $client->email, "phone" => $properties->get("Certificate Contact Phone") ?: $client->phoneNumber];
        foreach (["admin", "tech", "billing"] as $contact) {
            $this->payload[$contact] = $default;
        }
        return $this;
    }

    public function organisationFromProperties($self, $properties, \WHMCS\User\Client $client)
    {
        if ($client == NULL) {
            _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F6D61726B6574636F6E6E6563742F73736C2F636F6E66696775726174696F6E2E7068703078376630316332633665636234_();
            $client = $easytoyou_error_decompile;
        }
        $this->payload["org"] = ["name" => $properties->get("Certificate Organisation Name") ?: $client->companyName, "address1" => $properties->get("Certificate Address 1") ?: $client->address1, "address2" => $properties->get("Certificate Address 2") ?: $client->address2, "city" => $properties->get("Certificate City") ?: $client->city, "state" => $properties->get("Certificate State") ?: $client->state, "postcode" => $properties->get("Certificate Post/Zip Code") ?: $client->postcode, "country" => $properties->get("Certificate Country") ?: $client->country, "phone" => $properties->get("Certificate Phone") ?: $client->phoneNumber];
        return $this;
    }

    public function getDomainValidationEmail()
    {
        return $this->payload["approveremail"] ?? "";
    }

    public function setUseInstantIssuance($self, $value)
    {
        $this->payload["use_request_token"] = $value ? 1 : 0;
        return $this;
    }
}

<?php

namespace WHMCS\Module\Registrar\Transip;

class TransIP
{
    private $username = NULL;
    private $privateKey = NULL;
    private $endpoint = NULL;
    private $testMode = false;
    private $transIpClient = NULL;
    const API_URI_FORMAT = "https://%s/v6";
    const CONFIG_FIELD_ENDPOINT = "Endpoint";
    const CONFIG_FIELD_USERNAME = "Login";
    const CONFIG_FIELD_PRIVATE_KEY = "PrivateKey";
    const CONFIG_FIELD_TEST_MODE = "ReadOnlyMode";
    const TRANSIP_TEST_DOMAIN = "transipdemo.be";
    const AVAILABLE_ENDPOINTS = ["eu" => "api.transip.eu", "nl" => "api.transip.nl", "be" => "api.transip.be"];

    public static function init($TransIP, $params)
    {
        $self = new self();
        $username = trim($params[self::CONFIG_FIELD_USERNAME]);
        $privateKey = trim($params[self::CONFIG_FIELD_PRIVATE_KEY]);
        $endpoint = trim($params[self::CONFIG_FIELD_ENDPOINT]);
        $testMode = (bool) $params[self::CONFIG_FIELD_TEST_MODE];
        if (empty($endpoint)) {
            $self->handleModuleMisconfiguration("Endpoint has not been specified.");
        }
        if (empty($username)) {
            $self->handleModuleMisconfiguration("Username has not been specified.");
        }
        if (empty($privateKey)) {
            $self->handleModuleMisconfiguration("Private Key has not been specified.");
        }
        $self->setTestMode($testMode)->setUsername($username)->setPrivateKey($privateKey)->setEndpoint($endpoint)->autoloadTransipLibrary();
        return $self;
    }

    public static function autoloadTransipLibrary()
    {
        if (!class_exists("Transip\\Api\\Library\\TransipAPI")) {
            spl_autoload_register(["WHMCS\\Module\\Registrar\\Transip\\Autoload", "autoload"]);
        }
    }

    public function client($TransipAPI)
    {
        $this->autoloadTransipLibrary();
        if (!$this->transIpClient instanceof \Transip\Api\Library\TransipAPI) {
            $this->transIpClient = new \Transip\Api\Library\TransipAPI($this->getUsername(), $this->getPrivateKey(), true, "", $this->getEndpoint());
        }
        $this->transIpClient->setTestMode($this->getTestMode());
        if ($this->getTestMode()) {
            $this->transIpClient->useDemoToken();
        }
        return $this->transIpClient;
    }

    public function setUsername($self, $username)
    {
        $this->username = $username;
        return $this;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setPrivateKey($self, $privateKey)
    {
        $this->privateKey = $privateKey;
        return $this;
    }

    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    public function setEndpoint($self, $endpoint)
    {
        if (array_search($endpoint, self::AVAILABLE_ENDPOINTS) === false) {
            $this->handleModuleMisconfiguration("Invalid endpoint specified.");
        }
        $this->endpoint = sprintf(self::API_URI_FORMAT, $endpoint);
        return $this;
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function setTestMode($self, $testMode)
    {
        $this->testMode = $testMode;
        return $this;
    }

    public function getTestMode()
    {
        return $this->testMode;
    }

    public function handleModuleMisconfiguration($message, $activityLogEntry = false)
    {
        if ($activityLogEntry) {
            try {
                logActivity("TransIP Module Error: " . $message);
            } catch (\Illuminate\Contracts\Container\BindingResolutionException $e) {
            }
        }
        throw new \WHMCS\Exception\Module\InvalidConfiguration($message);
    }

    public static function getContactTypeMapping()
    {
        return [\Transip\Api\Library\Entity\Domain\WhoisContact::CONTACT_TYPE_REGISTRANT => "Registrant contact", \Transip\Api\Library\Entity\Domain\WhoisContact::CONTACT_TYPE_ADMINISTRATIVE => "Administrative contact", \Transip\Api\Library\Entity\Domain\WhoisContact::CONTACT_TYPE_TECHNICAL => "Technical contact"];
    }

    public static function getContactFieldMapping()
    {
        return ["firstName" => "First name", "lastName" => "Last name", "companyName" => "Company name", "companyKvk" => "Company KvK number", "companyType" => "Company type", "street" => "Street", "number" => "Number", "postalCode" => "Postal code", "city" => "City", "phoneNumber" => "Phone number", "faxNumber" => "Fax number", "email" => "Email address", "country" => "Country code"];
    }

    public static function getContactsFromParams($data)
    {
        $address = self::splitAddress($data["address1"]);
        $reg = new \Transip\Api\Library\Entity\Domain\WhoisContact();
        self::setCompanyWhoisData($data, $reg);
        $admin = clone $reg;
        $reg->setType(\Transip\Api\Library\Entity\Domain\WhoisContact::CONTACT_TYPE_REGISTRANT);
        $reg->setFirstName($data["firstname"]);
        $reg->setLastName($data["lastname"]);
        $reg->setPostalCode($data["postcode"]);
        $reg->setCity($data["city"]);
        $reg->setStreet($address[0]);
        $reg->setNumber($address[1]);
        $reg->setCountry($data["country"]);
        $reg->setPhoneNumber($data["phonenumber"]);
        $reg->setFaxNumber("");
        $reg->setEmail($data["email"]);
        $adminAddress = self::splitAddress($data["adminaddress1"]);
        $admin->setType(\Transip\Api\Library\Entity\Domain\WhoisContact::CONTACT_TYPE_ADMINISTRATIVE);
        $admin->setFirstName($data["adminfirstname"]);
        $admin->setLastName($data["adminlastname"]);
        $admin->setPostalCode($data["adminpostcode"]);
        $admin->setCity($data["admincity"]);
        $admin->setStreet($adminAddress[0]);
        $admin->setNumber($adminAddress[1]);
        $admin->setCountry($data["admincountry"]);
        $admin->setPhoneNumber($data["adminphonenumber"]);
        $admin->setFaxNumber("");
        $admin->setEmail($data["adminemail"]);
        $tech = clone $admin;
        $tech->setType(\Transip\Api\Library\Entity\Domain\WhoisContact::CONTACT_TYPE_TECHNICAL);
        return [$reg, $admin, $tech];
    }

    private static function setCompanyWhoisData($params, \Transip\Api\Library\Entity\Domain\WhoisContact &$whoisContact)
    {
        $map = self::getCompanyTypes();
        $whoisContact->setCompanyType(\Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_NONE)->setCompanyName("")->setCompanyKvk("");
        if (!empty($params["additionalfields"]) && !empty($params["additionalfields"]["TIPCompanyType"]) && array_key_exists($params["additionalfields"]["TIPCompanyType"], $map)) {
            $whoisContact->setCompanyType($params["additionalfields"]["TIPCompanyType"])->setCompanyName($params["additionalfields"]["TIPCompanyName"])->setCompanyKvk($params["additionalfields"]["TIPCompanyNumber"]);
        }
    }

    public static function splitAddress($address)
    {
        $address = trim($address);
        try {
            return self::convertAddress($address);
        } catch (\Exception $e) {
            if (preg_match("/[0-9]/Usi", $address, $matches)) {
                if ($address == (string) intval($address)) {
                    return ["Postbus", trim($address)];
                }
                $address = preg_replace("/([^0-9]*)([0-9]+)([^\\s]*)(.*)/si", "\$1\$4 \$2\$3", $address);
                $address = trim($address);
                try {
                    return self::convertAddress($address);
                } catch (Exception $e) {
                    return ["FakeAddress", "1"];
                }
            } else {
                return [$address, "1"];
            }
        }
    }

    public static function convertAddress($address)
    {
        $maxLoop = 10;
        $address = preg_replace("/(.*\\s)([0-9]+)(\\s*)-(\\s*)([0-9]+)(.*)/si", "\$1\$2-\$5\$6", $address);
        $address = preg_replace("/(.*\\s)([0-9]+)\\s([0-9]+)\\s*hg(.*)/Usi", "\$1\$2-\$3hg\$4", $address);
        $address = preg_replace("/(.*\\s)([0-9]+)(\\s*)(kamer|k)(\\s*)([0-9]+)(.*)/si", "\$1\$2-k\$6\$7", $address);
        if (preg_match("/^([^0-9\\s]+)([0-9]+)\$/", $address, $matches)) {
            return [$matches[1], $matches[2]];
        }
        while (!preg_match("/(.*)[^0-9][0-9]+?[^\\s]*\$/Usi", $address, $matches) && $maxLoop) {
            $parts = explode(" ", $address);
            if (count($parts) < 3) {
                throw new \Exception("Could not convert address '" . $address . "'");
            }
            $lastEl = array_pop($parts);
            $parts[count($parts) - 1] .= $lastEl;
            $address = implode(" ", $parts);
            $maxLoop--;
        }
        if (!preg_match("/(.*)[^0-9][0-9]+?[^\\s]*\$/Usi", $address, $matches) && $maxLoop == 0) {
            throw new \Exception("Hit limit of 10 rounds while trying to convert address line '" . $address . "'");
        }
        $street = $matches[1];
        $number = trim(substr($address, strlen($street)));
        return [$street, $number];
    }

    public static function getNameserversFromParams($params)
    {
        $nameservers = [];
        if (!empty($params["ns1"])) {
            $nameservers[] = (new \Transip\Api\Library\Entity\Domain\Nameserver())->setHostname($params["ns1"]);
        }
        if (!empty($params["ns2"])) {
            $nameservers[] = (new \Transip\Api\Library\Entity\Domain\Nameserver())->setHostname($params["ns2"]);
        }
        if (!empty($params["ns3"])) {
            $nameservers[] = (new \Transip\Api\Library\Entity\Domain\Nameserver())->setHostname($params["ns3"]);
        }
        if (!empty($params["ns4"])) {
            $nameservers[] = (new \Transip\Api\Library\Entity\Domain\Nameserver())->setHostname($params["ns4"]);
        }
        if (!empty($params["ns5"])) {
            $nameservers[] = (new \Transip\Api\Library\Entity\Domain\Nameserver())->setHostname($params["ns5"]);
        }
        return $nameservers;
    }

    public static function getCompanyTypes()
    {
        self::autoloadTransipLibrary();
        return [\Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_NONE => "None / Individual", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_BV => "Private Company", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_NV => "Limited Company", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_BVIO => "Limited Company In Formation", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_COOP => "Co-Operative", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_CV => "Limited Partnership", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_EENMANSZAAK => "Proprietorship", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_KERK => "Denomination", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_OWM => "Mutual Guarantee Company", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_REDR => "Shipping Company", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_STICHTING => "Founding", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_VERENIGING => "Association", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_VOF => "Partnership", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_BEG => "Foreign EC Company", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_BRO => "Foreign Legal Form/Company", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_EESV => "European Economic Interest Group", \Transip\Api\Library\Entity\Domain\WhoisContact::COMPANY_TYPE_ANDERS => "Other"];
    }
}

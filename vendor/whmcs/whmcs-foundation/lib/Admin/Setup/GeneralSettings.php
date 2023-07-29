<?php

namespace WHMCS\Admin\Setup;

class GeneralSettings
{
    public function autoDetermineSystemUrl()
    {
        $scheme = \App::in_ssl() ? "https" : "http";
        $host = $_SERVER["SERVER_NAME"];
        $port = NULL;
        $path = $this->removeFilenameFromPath($_SERVER["PHP_SELF"]);
        if (\WHMCS\Utility\Environment\WebHelper::isUsingNonStandardWebPort()) {
            $port = ":" . \WHMCS\Utility\Environment\WebHelper::getWebPortInUse();
        }
        $systemUrl = $scheme . "://" . $host . $port . $path;
        if (substr($systemUrl, -1) != "/") {
            $systemUrl .= "/";
        }
        $adminPath = \App::get_admin_folder_name();
        $systemUrl = str_replace("/" . $adminPath . "/", "/", $systemUrl);
        return $systemUrl;
    }

    public function autoDetermineDomain()
    {
        $domain = $_SERVER["SERVER_NAME"];
        return "http://" . $domain;
    }

    public function autoDetermineSystemEmailsFromEmail()
    {
        return "noreply@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]);
    }

    public function autoSetInitialConfiguration($companyName, $email, $address, $country, $language, $logoUrl)
    {
        $domain = $this->processDomainValue(\WHMCS\Config\Setting::getValue("Domain") ?? "");
        if (empty($domain)) {
            $domain = $this->autoDetermineDomain();
        }
        $systemUrl = $this->processSystemUrlValue(\WHMCS\Config\Setting::getValue("SystemURL") ?? "");
        if (empty($systemUrl)) {
            $systemUrl = $this->autoDetermineSystemUrl();
        }
        $signature = "---" . PHP_EOL . $companyName . PHP_EOL . $domain;
        \WHMCS\Config\Setting::setValue("Domain", $domain);
        \WHMCS\Config\Setting::setValue("SystemURL", $systemUrl);
        \WHMCS\Config\Setting::setValue("SystemEmailsFromEmail", $this->autoDetermineSystemEmailsFromEmail());
        \WHMCS\Config\Setting::setValue("Signature", $signature);
        if (\WHMCS\Config\Setting::getValue("DefaultNameserver1") == "ns1.example.com") {
            \WHMCS\Config\Setting::setValue("DefaultNameserver1", "ns1." . str_replace("http://", "", $domain));
        }
        if (\WHMCS\Config\Setting::getValue("DefaultNameserver2") == "ns2.example.com") {
            \WHMCS\Config\Setting::setValue("DefaultNameserver2", "ns2." . str_replace("http://", "", $domain));
        }
        \WHMCS\Config\Setting::setValue("CompanyName", $companyName);
        if ($logoUrl) {
            \WHMCS\Config\Setting::setValue("LogoURL", str_replace(["http://", "https://"], "//", $this->autoDetermineSystemUrl() . $logoUrl));
        }
        \WHMCS\Config\Setting::setValue("Email", $email);
        \WHMCS\Config\Setting::setValue("InvoicePayTo", $address);
        \WHMCS\Config\Setting::setValue("DefaultCountry", $country);
        $defaultCurrency = $this->getCurrencyBasedOnCountry($country);
        if (is_array($defaultCurrency)) {
            $this->setDefaultCurrencyIfNotUsed($defaultCurrency);
        }
        \WHMCS\Config\Setting::setValue("DateFormat", $this->getDateFormatBasedOnCountry($country));
        \WHMCS\Config\Setting::setValue("ClientDateFormat", "fullday");
        if (in_array($language, \WHMCS\Language\ClientLanguage::getLanguages())) {
            \WHMCS\Config\Setting::setValue("Language", $language);
            if ($language != "english") {
                \WHMCS\Config\Setting::setValue("EnableTranslations", 1);
            }
            if (in_array($language, \WHMCS\Language\AdminLanguage::getLanguages())) {
                update_query("tbladmins", ["language" => $language], ["id" => \WHMCS\Session::get("adminid")]);
            }
        }
        $this->setupFirstSupportDepartment($email);
    }

    public function getCurrencyBasedOnCountry($country)
    {
        $currencyMap = ["AUD" => ["AUD", "\$", " AUD", 2], "BRL" => ["BRL", "R\$", " BRL", 2], "CAD" => ["CAD", "\$", " CAD", 2], "CNY" => ["CNY", "¥", " CNY", 2], "EUR" => ["EUR", "€", " EUR", 3], "GBP" => ["GBP", "£", " GBP", 2], "IDR" => ["IDR", "Rp", " IDR", 4], "INR" => ["INR", "₹", " INR", 2], "NZD" => ["NZD", "\$", " NZD", 2], "TRY" => ["TRY", "₺", " TRY", 2], "USD" => ["USD", "\$", " USD", 2], "ZAR" => ["ZAR", "R", " ZAR", 2]];
        $countryMap = ["AT" => "EUR", "AU" => "AUD", "BE" => "EUR", "BG" => "EUR", "BR" => "BRL", "CA" => "CAD", "CY" => "EUR", "CN" => "CNY", "CZ" => "EUR", "DE" => "EUR", "DK" => "EUR", "EE" => "EUR", "ES" => "EUR", "FI" => "EUR", "FR" => "EUR", "GB" => "GBP", "GR" => "EUR", "HR" => "EUR", "HU" => "EUR", "ID" => "IDR", "IE" => "EUR", "IT" => "EUR", "IN" => "INR", "LT" => "EUR", "LU" => "EUR", "LV" => "EUR", "MT" => "EUR", "NL" => "EUR", "NZ" => "NZD", "PL" => "EUR", "PT" => "EUR", "RO" => "EUR", "SE" => "EUR", "SI" => "EUR", "SK" => "EUR", "TR" => "TRY", "US" => "USD", "ZA" => "ZAR"];
        if (array_key_exists($country, $countryMap)) {
            return $currencyMap[$countryMap[$country]];
        }
        return NULL;
    }

    public function setDefaultCurrencyIfNotUsed($currency)
    {
        $currencyCode = $currency[0];
        $alreadyExists = get_query_val("tblcurrencies", "id", ["code" => $currencyCode]);
        if ($alreadyExists) {
            return false;
        }
        $transactionCount = get_query_val("tblaccounts", "COUNT(id)", "");
        $invoicesCount = get_query_val("tblinvoices", "COUNT(id)", "");
        $productsCount = get_query_val("tblproducts", "COUNT(id)", "");
        if ($transactionCount + $invoicesCount + $productsCount == 0) {
            update_query("tblcurrencies", ["code" => $currency[0], "prefix" => $currency[1], "suffix" => $currency[2], "format" => $currency[3]], "`default` = 1");
        } else {
            insert_query("tblcurrencies", ["code" => $currency[0], "prefix" => $currency[1], "suffix" => $currency[2], "format" => $currency[3], "rate" => "1", "default" => "0"]);
        }
        return true;
    }

    public function getDateFormatBasedOnCountry($country)
    {
        switch ($country) {
            case "US":
            case "FM":
                $format = "MM/DD/YYYY";
                break;
            case "CN":
            case "HU":
            case "JP":
            case "LT":
            case "TW":
                $format = "YYYY-MM-DD";
                break;
            case "IR":
            case "KR":
                $format = "YYYY/MM/DD";
                break;
            default:
                $format = "DD/MM/YYYY";
                return $format;
        }
    }

    public function setupFirstSupportDepartment($email)
    {
        if (!\WHMCS\Database\Capsule::table("tblticketdepartments")->count()) {
            $departmentId = \WHMCS\Database\Capsule::table("tblticketdepartments")->insertGetId(["name" => "General Enquiries", "description" => "All Enquiries", "email" => $email, "order" => 1]);
            \WHMCS\Database\Capsule::table("tbladmins")->where("id", "=", \WHMCS\Session::get("adminid"))->update(["supportdepts" => $departmentId]);
            return true;
        }
        return false;
    }

    private function removeFilenameFromPath($path)
    {
        $path = preg_replace("#/([^/]*\\.php)\$#simU", "", $path);
        $path .= substr($path, -1) != "/" ? "/" : "";
        return $path;
    }

    public function doesUriContainHostAndScheme($uri)
    {
        if ($uri == "") {
            return false;
        }
        if (!preg_match("/\\b(?:(?:https?|ftp):\\/\\/|www\\.)[-a-z0-9+&@#\\/%?=~_|!:,.;]*[-a-z0-9+&@#\\/%=~_|]/i", $uri)) {
            return false;
        }
        return true;
    }

    public function processDomainValue($domain)
    {
        if (!$this->doesUriContainHostAndScheme($domain)) {
            return "";
        }
        return trim($domain, "/");
    }

    public function processSystemUrlValue($systemUrl)
    {
        if (!$this->doesUriContainHostAndScheme($systemUrl)) {
            return "";
        }
        return trim($systemUrl, "/") . "/";
    }
}

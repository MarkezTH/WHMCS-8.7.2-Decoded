<?php

namespace WHMCS\Admin\Setup;

class Domains
{
    public function enable()
    {
        \WHMCS\Config\Setting::setValue("AllowRegister", "on");
        \WHMCS\Config\Setting::setValue("AllowTransfer", "on");
        \WHMCS\Config\Setting::setValue("AllowOwnDomain", "on");
    }

    public function disable()
    {
        \WHMCS\Config\Setting::setValue("AllowRegister", "");
        \WHMCS\Config\Setting::setValue("AllowTransfer", "");
        \WHMCS\Config\Setting::setValue("AllowOwnDomain", "");
    }

    public function setupTldsWithDefaultOptions($extensions, $registrar, $price)
    {
        if (!is_array($extensions) || count($extensions) == 0) {
            return NULL;
        }
        if (!is_numeric($price)) {
            throw new \WHMCS\Exception("A selling price is required.");
        }
        if ($price <= 0) {
            throw new \WHMCS\Exception("Selling price must be greater than 0.");
        }
        foreach ($extensions as $extension) {
            try {
                $this->addTld($extension, false, false, false, true, $registrar, $price);
            } catch (\WHMCS\Exception $e) {
            }
        }
    }

    public function addTld($extension, $dnsManagement = false, $emailForwarding = false, $idProtection = false, $requiresEppCode = false, $registrar = "", $price = -1, $tldGroup = "")
    {
        if (substr($extension, 0, 1) != ".") {
            $extension = "." . $extension;
        }
        \WHMCS\Domains\Idna::toPunycode(\WHMCS\Domains\Idna::fromPunycode(ltrim($extension, ".")));
        $tld = \WHMCS\Database\Capsule::table("tbldomainpricing")->where("extension", "=", $extension)->get()->all();
        if (0 < count($tld)) {
            throw new \WHMCS\Exception("Extension already exists.");
        }
        $lastOrder = \WHMCS\Database\Capsule::table("tbldomainpricing")->orderBy("order", "desc")->first();
        if (is_null($lastOrder)) {
            $lastOrder = 0;
        } else {
            $lastOrder = $lastOrder->order;
        }
        if ($tldGroup && !in_array($tldGroup, ["sale", "new", "hot"])) {
            $tldGroup = "";
        }
        $extensionId = \WHMCS\Database\Capsule::table("tbldomainpricing")->insertGetId(["extension" => $extension, "dnsmanagement" => (int) $dnsManagement, "emailforwarding" => (int) $emailForwarding, "idprotection" => (int) $idProtection, "eppcode" => (int) $requiresEppCode, "autoreg" => $registrar, "group" => $tldGroup, "order" => $lastOrder + 1]);
        foreach (["register", "transfer", "renew"] as $type) {
            \WHMCS\Database\Capsule::table("tblpricing")->insert(["type" => "domain" . $type, "currency" => "1", "relid" => $extensionId, "msetupfee" => $price, "qsetupfee" => "-1", "ssetupfee" => "-1", "asetupfee" => "-1", "bsetupfee" => "-1", "monthly" => "-1", "quarterly" => "-1", "semiannually" => "-1", "annually" => "-1", "biennially" => "-1"]);
        }
        logAdminActivity("Domain Pricing TLD Created: '" . $extension . "'");
    }
}

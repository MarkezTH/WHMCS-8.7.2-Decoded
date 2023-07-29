<?php

namespace WHMCS\Module\Registrar\CentralNic;

class AdditionalFields
{
    protected $fields = [];
    protected $isDomainApplication = false;
    protected $tld = "";
    protected $additionalFields = [];
    protected $countryCode = "";
    protected $isTransferring = false;

    public function __construct($tld, $countryCode, $additionalFields, $isTransferring = false)
    {
        $this->tld = $tld;
        $this->countryCode = $countryCode;
        $this->additionalFields = $additionalFields;
        $this->isTransferring = $isTransferring;
        $this->transform();
    }

    public function isTransferring()
    {
        return $this->isTransferring;
    }

    protected function transform()
    {
        $this->reset();
        if (empty($this->additionalFields)) {
            return NULL;
        }
        switch ($this->tld) {
            case "au":
                $this->transformDotAu();
                break;
            case "aero":
                $this->transformDotAero();
                break;
            case "br":
                $this->transformDotBr();
                break;
            case "ca":
                $this->transformDotCa();
                break;
            case "cn":
                $this->transformDotCn();
                break;
            case "de":
                $this->transformDotDe();
                break;
            case "dentist":
            case "attorney":
            case "lawyer":
                $this->transformDotDentist();
                break;
            case "dev":
                $this->transformDotDev();
                break;
            case "es":
                $this->transformDotEs();
                break;
            case "eu":
                $this->transformDotEu();
                break;
            case "fr":
            case "pm":
            case "re":
            case "tf":
            case "wf":
            case "yt":
                $this->transformDotFr();
                break;
            case "hk":
                $this->transformDotHk();
                break;
            case "hu":
                $this->transformDotHu();
                break;
            case "it":
                $this->transformDotIt();
                break;
            case "jobs":
                $this->transformDotJobs();
                break;
            case "nu":
                $this->transformDotNu();
                break;
            case "pt":
                $this->transformDotPt();
                break;
            case "quebec":
                $this->transformDotQuebec();
                break;
            case "ro":
                $this->transformDotRo();
                break;
            case "ru":
                $this->transformDotRu();
                break;
            case "se":
                $this->transformDotSe();
                break;
            case "sg":
                $this->transformDotSg();
                break;
            case "swiss":
                $this->transformDotSwiss();
                break;
            case "tel":
                $this->transformDotTel();
                break;
            case "travel":
                $this->transformDotTravel();
                break;
            case "uk":
                $this->transformDotUk();
                break;
            case "us":
                $this->transformDotUs();
                break;
            case "boo":
                $this->transformDotBoo();
                break;
            case "rsvp":
                $this->transformDotRsvp();
                break;
        }
    }

    public function isDomainApplication()
    {
        return $this->isDomainApplication;
    }

    public function getFields()
    {
        return $this->fields;
    }

    protected function setFields($key, $value)
    {
        $this->fields[$key] = $value;
        return $this;
    }

    protected function reset()
    {
        $this->fields = [];
        $this->isDomainApplication = false;
        return $this;
    }

    protected function formatAuRelationTypeValue($value)
    {
        return str_replace([" ", "-", "/"], "", strtolower($value));
    }

    protected function transformDotCa()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        switch ($this->additionalFields["Legal Type"] ?? "") {
            case "Corporation":
                $legalType = "CCO";
                break;
            case "Canadian Citizen":
                $legalType = "CCT";
                break;
            case "Permanent Resident of Canada":
                $legalType = "RES";
                break;
            case "Government":
                $legalType = "GOV";
                break;
            case "Canadian Educational Institution":
                $legalType = "EDU";
                break;
            case "Canadian Unincorporated Association":
                $legalType = "ASS";
                break;
            case "Canadian Hospital":
                $legalType = "HOP";
                break;
            case "Partnership Registered in Canada":
                $legalType = "PRT";
                break;
            case "Trade-mark registered in Canada":
                $legalType = "TDM";
                break;
            case "Canadian Trade Union":
                $legalType = "TRD";
                break;
            case "Canadian Political Party":
                $legalType = "PLT";
                break;
            case "Canadian Library Archive or Museum":
                $legalType = "LAM";
                break;
            case "Trust established in Canada":
                $legalType = "TRS";
                break;
            case "Aboriginal Peoples":
                $legalType = "ABO";
                break;
            case "Legal Representative of a Canadian Citizen":
                $legalType = "LGR";
                break;
            case "Official mark registered in Canada":
                $legalType = "OMK";
                break;
            default:
                $legalType = NULL;
                if ($legalType) {
                    $this->setFields("X-CA-LEGAL-TYPE", $legalType);
                    $this->setFields("X-CA-TRADEMARK", 0);
                }
        }
    }

    protected function transformDotAu()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-AU-DOMAIN-IDNUMBER", $this->additionalFields["Registrant ID"] ?? "");
        $this->setFields("X-AU-DOMAIN-RELATIONTYPE", $this->formatAuRelationTypeValue($this->additionalFields["Eligibility Type"] ?? ""));
        $this->setFields("X-AU-OWNER-ORGANIZATION", $this->additionalFields["Registrant Name"] ?? "");
        switch ($this->additionalFields["Eligibility ID Type"] ?? "") {
            case "Australian Company Number (ACN)":
                $eligibilityType = "ACN";
                break;
            case "Australian Business Number (ABN)":
                $eligibilityType = "ABN";
                break;
            case "Trademark (TM)":
                $eligibilityType = "TM";
                break;
            case "ACT Business Number":
            case "NSW Business Number":
            case "NT Business Number":
            case "QLD Business Number":
            case "SA Business Number":
            case "TAS Business Number":
            case "VIC Business Number":
            case "WA Business Number":
            case "Other - Used to record an Incorporated Association number":
                $eligibilityType = "OTHER";
                break;
            default:
                $eligibilityType = NULL;
                if ($eligibilityType) {
                    $this->setFields("X-AU-DOMAIN-IDTYPE", $eligibilityType);
                    $this->setFields("X-AU-DOMAIN-RELATION", "D" == ($this->additionalFields["Eligibility Reason"][0] ?? "") ? 1 : 2);
                }
        }
    }

    protected function transformDotCn()
    {
        $this->setFields("X-CN-ACCEPT-TRUSTEE-TAC", 0);
        switch ($this->additionalFields["Owner Type"] ?? "") {
            case "Enterprise":
                $ownerType = "E";
                break;
            case "Individual":
            default:
                $ownerType = "I";
                switch ($this->additionalFields["ID Type"] ?? "") {
                    case "Beijing School for Children of Foreign Embassy Staff in China Permit":
                        $idType = "BJWSXX";
                        break;
                    case "Business License":
                        $idType = "YYZZ";
                        break;
                    case "Certificate for Uniform Social Credit Code":
                        $idType = "TYDM";
                        break;
                    case "Exit-Entry Permit for Travelling to and from Hong Kong and Macao":
                        $idType = "GAJMTX";
                        break;
                    case "Foreign Permanent Resident ID Card":
                        $idType = "WJLSFZ";
                        break;
                    case "Fund Legal Person Registration Certificate":
                        $idType = "JJHFR";
                        break;
                    case "ID":
                        $idType = "SFZ";
                        break;
                    case "Judicial Expertise License":
                        $idType = "SFJD";
                        break;
                    case "Medical Institution Practicing License":
                        $idType = "YLJGZY";
                        break;
                    case "Military Code Designation":
                        $idType = "BDDM";
                        break;
                    case "Military Paid External Service License":
                        $idType = "JDDWFW";
                        break;
                    case "Notary Organization Practicing License":
                        $idType = "GZJGZY";
                        break;
                    case "Officer’s identity card":
                        $idType = "JGZ";
                        break;
                    case "Organization Code Certificate":
                        $idType = "ORG";
                        break;
                    case "Others":
                        $idType = "QT";
                        break;
                    case "Others-Certificate for Uniform Social Credit Code":
                        $idType = "QTTYDM";
                        break;
                    case "Overseas Organization Certificate":
                        $idType = "JWJG";
                        break;
                    case "Practicing License of Law Firm":
                        $idType = "LSZY";
                        break;
                    case "Private Non-Enterprise Entity Registration Certificate":
                        $idType = "MBFQY";
                        break;
                    case "Private School Permit":
                        $idType = "MBXXBX";
                        break;
                    case "Public Institution Legal Person Certificate":
                        $idType = "SYDWFR";
                        break;
                    case "Registration Certificate of Foreign Cultural Center in China":
                        $idType = "WGZHWH";
                        break;
                    case "Religion Activity Site Registration Certificate":
                        $idType = "ZJCS";
                        break;
                    case "Residence permit for Hong Kong and Macao residents":
                        $idType = "GAJZZ";
                        break;
                    case "Residence permit for Taiwan residents":
                        $idType = "TWJZZ";
                        break;
                    case "Resident Representative Office of Tourism Departments of Foreign Government Approval Registration Certificate":
                        $idType = "WLCZJG";
                        break;
                    case "Resident Representative Offices of Foreign Enterprises Registration Form":
                        $idType = "WGCZJG";
                        break;
                    case "Social Organization Legal Person Registration Certificate":
                        $idType = "SHTTFR";
                        break;
                    case "Social Service Agency Registration Certificate":
                        $idType = "SHFWJG";
                        break;
                    case "Travel passes for Taiwan Residents to Enter or Leave the Mainland":
                        $idType = "TWJMTX";
                        break;
                    case "Passport":
                    default:
                        $idType = "HZ";
                        $this->setFields("X-CN-OWNER-TYPE", $ownerType);
                        $this->setFields("X-CN-OWNER-ID-NUMBER", $this->additionalFields["ID Number"] ?? "");
                        $this->setFields("X-CN-OWNER-ID-TYPE", $idType);
                }
        }
    }

    protected function transformDotEs()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        switch ($this->additionalFields["ID Form Type"] ?? "") {
            case "DNI":
            case "NIF":
            case "Tax Identification Number":
            case "Tax Identification Code":
                $idType = 3;
                break;
            case "NIE":
            case "Foreigner Identification Number":
                $idType = 1;
                break;
            default:
                $idType = 0;
                $this->setFields("X-ES-OWNER-LEGALFORM", $this->additionalFields["Legal Form"] ?? 1);
                $this->setFields("X-ES-OWNER-TIPO-IDENTIFICACION", $idType);
                $this->setFields("X-ES-OWNER-IDENTIFICACION", $this->additionalFields["ID Form Number"] ?? "");
        }
    }

    protected function transformDotEu()
    {
        $this->setFields("X-EU-REGISTRANT-CITIZENSHIP", $this->additionalFields["EU Country of Citizenship"] ?? "");
        if (!$this->isTransferring()) {
            $this->setFields("X-EU-ACCEPT-TRUSTEE-TAC", 0);
            $this->setFields("X-EU-REGISTRANT-LANG", "EN");
        }
    }

    protected function transformDotDev()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-ACCEPT-SSL-REQUIREMENT", empty($this->additionalFields[".DEV SSL Agreement"]) ? 0 : 1);
    }

    protected function transformDotDentist()
    {
        $this->setFields("X-UNITEDTLD-REGULATORY-DATA", $this->additionalFields["Regulatory Data"] ?? "");
    }

    protected function transformDotDe()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-DE-ACCEPT-TRUSTEE-TAC", 0);
    }

    protected function transformDotBr()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-BR-ACCEPT-TRUSTEE-TAC", 0);
        $this->setFields("X-BR-REGISTER-NUMBER", $this->additionalFields["Register Number"] ?? "");
    }

    protected function transformDotAero()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-AERO-ENS-AUTH-ID", $this->additionalFields[".AERO ID"] ?? "");
        $this->setFields("X-AERO-ENS-AUTH-KEY", $this->additionalFields[".AERO Key"] ?? "");
    }

    protected function transformDotFr()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-FR-ACCEPT-TRUSTEE-TAC", 0);
        switch ($this->additionalFields["Legal Type"] ?? "") {
            case "Individual":
                $this->setFields("X-FR-BIRTHCITY", $this->additionalFields["Birthplace City"] ?? "");
                $this->setFields("X-FR-BIRTHDATE", $this->additionalFields["Birthdate"] ?? "");
                $this->setFields("X-FR-BIRTHPC", $this->additionalFields["Birthplace Postcode"] ?? "");
                $this->setFields("X-FR-BIRTHPLACE", $this->additionalFields["Birthplace Country"] ?? "");
                $this->setFields("X-FR-RESTRICT-PUB", 1);
                break;
            case "Company":
                $this->setFields("X-FR-DUNS", $this->additionalFields["DUNS Number"] ?? "");
                $this->setFields("X-FR-SIREN-OR-SIRET", $this->additionalFields["SIRET Number"] ?? "");
                $this->setFields("X-FR-TRADEMARK", $this->additionalFields["Trademark Number"] ?? "");
                $this->setFields("X-FR-VATID", $this->additionalFields["VAT Number"] ?? "");
                break;
        }
    }

    protected function transformDotHk()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-ACCEPT-NSCHANGE", 0);
        switch ($this->additionalFields["Registrant Type"] ?? "") {
            case "ind":
                $this->setFields("X-HK-DOMAIN-CATEGORY", "I");
                $this->setFields("X-HK-OWNER-AGE-OVER-18", "No" == ($this->additionalFields["Individuals Under 18"] ?? "") ? "Yes" : "No");
                $this->setFields("X-HK-OWNER-DOCUMENT-NUMBER", $this->additionalFields["Individuals Document Number"] ?? "");
                $this->setFields("X-HK-OWNER-DOCUMENT-ORIGIN-COUNTRY", $this->additionalFields["Individuals Issuing Country"] ?? "");
                $this->setFields("X-HK-OWNER-DOCUMENT-TYPE", $this->additionalFields["Individuals Supporting Documentation"] ?? "");
                break;
            case "org":
                $this->setFields("X-HK-DOMAIN-CATEGORY", "O");
                $this->setFields("X-HK-OWNER-DOCUMENT-NUMBER", $this->additionalFields["Organizations Document Number"] ?? "");
                $this->setFields("X-HK-OWNER-DOCUMENT-ORIGIN-COUNTRY", $this->additionalFields["Organizations Issuing Country"] ?? "");
                $this->setFields("X-HK-OWNER-DOCUMENT-TYPE", $this->additionalFields["Organizations Supporting Documentation"] ?? "");
                break;
        }
    }

    protected function transformDotHu()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        if (!empty($this->additionalFields["Accept Trustee Service"]) || $this->countryCode != "HU") {
            $this->setFields("X-HU-ACCEPT-TRUSTEE-TAC", 1);
        }
        if (!empty($this->additionalFields["ID Card or Passport Number"])) {
            $this->setFields("X-HU-IDCARD-OR-PASSPORT-NUMBER", $this->additionalFields["ID Card or Passport Number"]);
        }
        if (!empty($this->additionalFields["VAT Number"])) {
            $this->setFields("X-HU-VAT-NUMBER", $this->additionalFields["VAT Number"]);
        }
    }

    protected function transformDotIt()
    {
        switch ($this->additionalFields["Legal Type"] ?? "") {
            case "Companies/one man companies":
                $entityType = 2;
                break;
            case "Freelance workers/professionals":
                $entityType = 3;
                break;
            case "non-profit organizations":
                $entityType = 4;
                break;
            case "public organizations":
                $entityType = 5;
                break;
            case "other subjects":
                $entityType = 6;
                break;
            case "non natural foreigners":
                $entityType = 7;
                break;
            case "Italian and foreign natural persons":
            default:
                $entityType = 1;
                $this->setFields("X-IT-ACCEPT-TRUSTEE-TAC", 0);
                $this->setFields("X-IT-CONSENTFORPUBLISHING", !empty($this->additionalFields["Publish Personal Data"]) ? 1 : 0);
                $this->setFields("X-IT-ENTITY-TYPE", $entityType);
                $this->setFields("X-IT-PIN", $this->additionalFields["Tax ID"] ?? "na");
                $this->setFields("X-IT-SECT3-LIABILITY", !empty($this->additionalFields["Accept Section 3 of .IT registrar contract"]) ? 1 : 0);
                $this->setFields("X-IT-SECT5-PERSONAL-DATA-FOR-REGISTRATION", !empty($this->additionalFields["Accept Section 5 of .IT registrar contract"]) ? 1 : 0);
                $this->setFields("X-IT-SECT6-PERSONAL-DATA-FOR-DIFFUSION", !empty($this->additionalFields["Accept Section 6 of .IT registrar contract"]) ? 1 : 0);
                $this->setFields("X-IT-SECT7-EXPLICIT-ACCEPTANCE", !empty($this->additionalFields["Accept Section 7 of .IT registrar contract"]) ? 1 : 0);
        }
    }

    protected function transformDotJobs()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-JOBS-COMPANYURL", $this->additionalFields["Website"] ?? "");
    }

    protected function transformDotNu()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-NU-IIS-IDNO", $this->additionalFields["Identification Number"] ?? "");
        if (!empty($this->additionalFields["VAT Number"])) {
            $this->setFields("X-NU-IIS-VATNO", $this->additionalFields["VAT Number"]);
        }
    }

    protected function transformDotPt()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        if (!empty($this->additionalFields["Owner Identification"])) {
            $this->setFields("X-PT-OWNER-IDENTIFICATION", $this->additionalFields["Owner Identification"]);
        }
        if (!empty($this->additionalFields["Tech Identification"])) {
            $this->setFields("X-PT-TECH-IDENTIFICATION", $this->additionalFields["Tech Identification"]);
        }
    }

    protected function transformDotQuebec()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-INTENDED-USE", $this->additionalFields["Intended Use"] ?? "");
    }

    protected function transformDotRo()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        switch ($this->additionalFields["Registrant Type"] ?? "") {
            case "p":
                $this->setFields("X-RO-IDCARD-OR-PASSPORT-NUMBER", $this->additionalFields["CNPFiscalCode"] ?? "");
                break;
            default:
                $this->setFields("X-RO-VAT-NUMBER", $this->additionalFields["CNPFiscalCode"] ?? "");
                $this->setFields("X-RO-COMPANY-NUMBER", $this->additionalFields["Registration Number"]);
        }
    }

    protected function transformDotRu()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        switch ($this->additionalFields["Registrant Type"] ?? "") {
            case "IND":
                $this->setFields("X-RU-BIRTHDATE", $this->additionalFields["Individuals Birthday"] ?? "");
                $passportNumber = $this->additionalFields["Individuals Passport Number"] ?? "";
                $issuer = $this->additionalFields["Individuals Passport Issuer"] ?? "";
                $this->setFields("X-RU-PASSPORTDATA", $passportNumber . " - " . $issuer);
                break;
            case "ORG":
                $this->setFields("X-RU-CODE", $this->additionalFields["Russian Organizations Taxpayer Number 1"] ?? "");
                $this->setFields("X-RU-KPP", $this->additionalFields["Russian Organizations Territory-Linked Taxpayer Number 2"] ?? "");
                break;
        }
    }

    protected function transformDotSe()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-NICSE-IDNUMBER", $this->additionalFields["Identification Number"] ?? "");
        $this->setFields("X-NICSE-VATID", $this->additionalFields["VAT"] ?? "");
    }

    protected function transformDotSg()
    {
        $this->setFields("X-SG-ACCEPT-TRUSTEE-TAC", 0);
        switch ($this->additionalFields["Registrant Type"] ?? "") {
            case "Individual":
                $this->setFields("X-SG-ADMIN-SINGPASSID", $this->additionalFields["RCB Singapore ID"] ?? "");
                break;
            case "Organisation":
                $this->setFields("X-SG-RCBID", $this->additionalFields["RCB Singapore ID"] ?? "");
                break;
        }
    }

    protected function transformDotSwiss()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("class", "SWISS-GOLIVE");
        $this->setFields("X-INTENDED-USE", $this->additionalFields["Core Intended Use"] ?? "");
        $this->setFields("X-SWISS-UID", $this->additionalFields["Registrant Enterprise ID"] ?? "");
        $this->isDomainApplication = true;
    }

    protected function transformDotTel()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-TEL-PUBLISH-WHOIS", !empty($this->additionalFields["WHOIS Opt-out"]) ? 1 : 0);
        $this->setFields("X-TEL-WHOISTYPE", "Legal Person" == ($this->additionalFields["Legal Type"] ?? "") ? "Legal" : "Natural");
    }

    protected function transformDotTravel()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-TRAVEL-INDUSTRY", !empty($this->additionalFields[".TRAVEL Usage Agreement"]) ? "Y" : "N");
    }

    protected function transformDotUk()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        switch ($this->additionalFields["Legal Type"] ?? "") {
            case "UK Limited Company":
                $legalType = "LTD";
                break;
            case "UK Public Limited Company":
                $legalType = "PLC";
                break;
            case "UK Partnership":
                $legalType = "PTNR";
                break;
            case "UK Limited Liability Partnership":
                $legalType = "LLP";
                break;
            case "Sole Trader":
                $legalType = "STRA";
                break;
            case "UK Registered Charity":
                $legalType = "RCHAR";
                break;
            case "UK Industrial/Provident Registered Company":
                $legalType = "IP";
                break;
            case "UK School":
                $legalType = "SCH";
                break;
            case "UK Government Body":
                $legalType = "GOV";
                break;
            case "UK Corporation by Royal Charter":
                $legalType = "CRC";
                break;
            case "UK Statutory Body":
                $legalType = "STAT";
                break;
            case "Non-UK Individual":
                $legalType = "FIND";
                break;
            case "Foreign Organization":
                $legalType = "FCORP";
                break;
            case "Other foreign organizations":
                $legalType = "FOTHER";
                break;
            default:
                $legalType = "IND";
                $this->setFields("X-UK-OWNER-CORPORATE-TYPE", $legalType);
                $this->setFields("X-UK-OWNER-CORPORATE-NUMBER", $this->additionalFields["Company ID Number"] ?? "");
        }
    }

    protected function transformDotUs()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        switch ($this->additionalFields["Application Purpose"] ?? "") {
            case "Business use for profit":
                $purpose = "P1";
                break;
            case "Non-profit business":
            case "Club":
            case "Association":
            case "Religious Organization":
                $purpose = "P2";
                break;
            case "Personal Use":
                $purpose = "P3";
                break;
            case "Educational purposes":
                $purpose = "P4";
                break;
            case "Government purposes":
                $purpose = "P5";
                break;
            default:
                $purpose = NULL;
                if ($purpose) {
                    $this->setFields("X-US-NEXUS-APPPURPOSE", $purpose);
                }
                switch ($this->additionalFields["Nexus Category"] ?? "") {
                    case "C11":
                    case "C12":
                    case "C21":
                        $this->setFields("X-US-NEXUS-CATEGORY", $this->additionalFields["Nexus Category"]);
                        break;
                    case "C31":
                    case "C32":
                        $this->setFields("X-US-NEXUS-CATEGORY", $this->additionalFields["Nexus Category"]);
                        $this->setFields("X-US-NEXUS-VALIDATOR", $this->additionalFields["Nexus Country"] ?? "");
                        break;
                }
        }
    }

    protected function transformDotBoo()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-ACCEPT-SSL-REQUIREMENT", empty($this->additionalFields[".boo SSL Agreement"]) ? 0 : 1);
    }

    protected function transformDotRsvp()
    {
        if ($this->isTransferring()) {
            return NULL;
        }
        $this->setFields("X-ACCEPT-SSL-REQUIREMENT", empty($this->additionalFields[".Rsvp SSL Agreement"]) ? 0 : 1);
    }

    public static function transformCompanyName($company, $tld, $additionalFields)
    {
        switch ($tld) {
            case "it":
                if ($additionalFields["Legal Type"] == "Italian and foreign natural persons") {
                    $company = "";
                }
                break;
            default:
                return $company;
        }
    }

    public static function transformTradeDomain($tld, Commands\AbstractCommand $command, $additionalFields)
    {
        switch ($tld) {
            case "swiss":
                if (empty($additionalFields["UID"])) {
                    throw new \Exception("Swiss domainTld requires UID");
                }
                $command->setParam("X-SWISS-UID", $additionalFields["UID"]);
                break;
            default:
                return $command;
        }
    }
}

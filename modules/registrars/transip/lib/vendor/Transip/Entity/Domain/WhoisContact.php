<?php

namespace Transip\Api\Library\Entity\Domain;

class WhoisContact extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $type = NULL;
    protected $firstName = NULL;
    protected $lastName = NULL;
    protected $companyName = NULL;
    protected $companyKvk = NULL;
    protected $companyType = NULL;
    protected $street = NULL;
    protected $number = NULL;
    protected $postalCode = NULL;
    protected $city = NULL;
    protected $phoneNumber = NULL;
    protected $faxNumber = NULL;
    protected $email = NULL;
    protected $country = NULL;
    const CONTACT_TYPE_REGISTRANT = "registrant";
    const CONTACT_TYPE_ADMINISTRATIVE = "administrative";
    const CONTACT_TYPE_TECHNICAL = "technical";
    const COMPANY_TYPE_BV = "BV";
    const COMPANY_TYPE_BVIO = "BVI/O";
    const COMPANY_TYPE_COOP = "COOP";
    const COMPANY_TYPE_CV = "CV";
    const COMPANY_TYPE_EENMANSZAAK = "EENMANSZAAK";
    const COMPANY_TYPE_KERK = "KERK";
    const COMPANY_TYPE_NV = "NV";
    const COMPANY_TYPE_OWM = "OWM";
    const COMPANY_TYPE_REDR = "REDR";
    const COMPANY_TYPE_STICHTING = "STICHTING";
    const COMPANY_TYPE_VERENIGING = "VERENIGING";
    const COMPANY_TYPE_VOF = "VOF";
    const COMPANY_TYPE_BEG = "BEG";
    const COMPANY_TYPE_BRO = "BRO";
    const COMPANY_TYPE_EESV = "EESV";
    const COMPANY_TYPE_ANDERS = "ANDERS";
    const COMPANY_TYPE_NONE = "";

    public function getType()
    {
        return $this->type;
    }

    public function setType($WhoisContact, $type)
    {
        $this->type = $type;
        return $this;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function setFirstName($WhoisContact, $firstName)
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function setLastName($WhoisContact, $lastName)
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getCompanyName()
    {
        return $this->companyName;
    }

    public function setCompanyName($WhoisContact, $companyName)
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getCompanyKvk()
    {
        return $this->companyKvk;
    }

    public function setCompanyKvk($WhoisContact, $companyKvk)
    {
        $this->companyKvk = $companyKvk;
        return $this;
    }

    public function getCompanyType()
    {
        return $this->companyType;
    }

    public function setCompanyType($WhoisContact, $companyType)
    {
        $this->companyType = $companyType;
        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function setStreet($WhoisContact, $street)
    {
        $this->street = $street;
        return $this;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setNumber($WhoisContact, $number)
    {
        $this->number = $number;
        return $this;
    }

    public function getPostalCode()
    {
        return $this->postalCode;
    }

    public function setPostalCode($WhoisContact, $postalCode)
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function setCity($WhoisContact, $city)
    {
        $this->city = $city;
        return $this;
    }

    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber($WhoisContact, $phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getFaxNumber()
    {
        return $this->faxNumber;
    }

    public function setFaxNumber($WhoisContact, $faxNumber)
    {
        $this->faxNumber = $faxNumber;
        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($WhoisContact, $email)
    {
        $this->email = $email;
        return $this;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($WhoisContact, $country)
    {
        $this->country = $country;
        return $this;
    }
}

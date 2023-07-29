<?php

namespace WHMCS\Module\Registrar\CentralNic;

class Contact
{
    protected $handle = NULL;
    protected $firstName = "";
    protected $lastName = "";
    protected $company = "";
    protected $address1 = "";
    protected $address2 = "";
    protected $city = "";
    protected $state = "";
    protected $postalCode = "";
    protected $country = "";
    protected $email = "";
    protected $phone = "";
    protected $fax = "";
    protected $updateAllow = true;
    protected $contactType = self::ADMIN_CONTACT;
    const REGISTRANT_CONTACT = "Registrant";
    const ADMIN_CONTACT = "Admin";
    const TECH_CONTACT = "Tech";
    const BILLING_CONTACT = "Billing";

    public function __construct($firstName, $lastName, $company, $address1, $address2, $city, $state, $postalCode, $country, $email, $phone, $fax)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->company = $company;
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->city = $city;
        $this->state = $state;
        $this->postalCode = $postalCode;
        $this->country = $country;
        $this->email = $email;
        $this->phone = $phone;
        $this->fax = $fax;
    }

    public static function factoryFromContactDetail($contacts)
    {
        return new self($contacts["First Name"] ?? "", $contacts["Last Name"] ?? "", $contacts["Company Name"] ?? "", $contacts["Address"] ?? "", $contacts["Address 2"] ?? "", $contacts["City"] ?? "", $contacts["State"] ?? "", $contacts["Postcode"] ?? "", $contacts["Country"] ?? "", $contacts["Email"] ?? "", $contacts["Phone"] ?? "", $contacts["Fax"] ?? "");
    }

    public function asRegistrant()
    {
        $this->contactType = self::REGISTRANT_CONTACT;
        return $this;
    }

    public function asAdmin()
    {
        $this->contactType = self::ADMIN_CONTACT;
        return $this;
    }

    public function asTech()
    {
        $this->contactType = self::TECH_CONTACT;
        return $this;
    }

    public function asBilling()
    {
        $this->contactType = self::BILLING_CONTACT;
        return $this;
    }

    public function getContactType()
    {
        return $this->contactType;
    }

    public function assertValid()
    {
        $errors = [];
        (new Validation())->addValidationItem(new ValidationItem("First Name", $this->firstName, Validation::ASSERT_NOT_EMPTY), new ValidationItem("Last Name", $this->lastName, Validation::ASSERT_NOT_EMPTY), new ValidationItem("Address", $this->address1, Validation::ASSERT_NOT_EMPTY), new ValidationItem("City", $this->city, Validation::ASSERT_NOT_EMPTY), new ValidationItem("State", $this->state, Validation::ASSERT_NOT_EMPTY), new ValidationItem("Postal Code", $this->postalCode, Validation::ASSERT_NOT_EMPTY), new ValidationItem("Country", $this->country, Validation::ASSERT_NOT_EMPTY), new ValidationItem("Email", $this->email, Validation::ASSERT_IS_EMAIL), new ValidationItem("Phone", $this->phone, Validation::ASSERT_NOT_EMPTY))->validate()->getValidatedItems()->each(function ($item) use($errors) {
            if ($item->getAssertionMessage()) {
                $errors[] = $item->getAssertionMessage();
            }
        });
        if (!empty($errors)) {
            $err = implode(", ", $errors);
            throw new \Exception("Invalid " . $this->getContactType() . " contact information: " . $err);
        }
        return $this;
    }

    public function setHandle($handle)
    {
        $this->handle = $handle;
        return $this;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function exists()
    {
        return $this->getHandle() != NULL;
    }

    public function setUpdateAllow($allow)
    {
        $this->updateAllow = $allow;
        return $this;
    }

    public function isUpdatable($remoteContact)
    {
        if ($this->updateAllow && $this->getHandle() == $remoteContact->getHandle() && strcasecmp($this->firstName, $remoteContact->firstName) === 0 && strcasecmp($this->lastName, $remoteContact->lastName) === 0 && strcasecmp($this->company, $remoteContact->company) === 0 && !$this->registrantTriggered($remoteContact)) {
            return true;
        }
        return false;
    }

    public static function populate($api, $handle)
    {
        $response = (new Commands\StatusContact($api, $handle))->execute();
        $contact = new Contact($response->getDataValue("firstname") ?? "", $response->getDataValue("lastname") ?? "", $response->getDataValue("organization") ?? "", $response->getData()["street"][0] ?? "", $response->getData()["street"][1] ?? "", $response->getDataValue("city") ?? "", $response->getDataValue("state") ?? "", $response->getDataValue("zip") ?? "", $response->getDataValue("country") ?? "", $response->getDataValue("email") ?? "", $response->getDataValue("phone") ?? "", $response->getDataValue("fax") ?? "");
        $contact->setHandle($handle);
        return $contact;
    }

    public function updateOrCreate($api = false, $forceCreateNew)
    {
        try {
            if ($this->exists()) {
                $remoteContact = self::populate($api, $this->getHandle());
                if (!$this->isUpdatable($remoteContact)) {
                    throw new \Exception("Contact can not be updated.");
                }
                return $this->update($api);
            }
        } catch (\Exception $e) {
        }
        return $this->create($api, $forceCreateNew);
    }

    public function create($api = false, $forceCreateNew)
    {
        if ($forceCreateNew) {
            return $this->doCreateNew($api);
        }
        return $this->doCreate($api);
    }

    protected function doCreate($api)
    {
        $newContact = (new Commands\AddContact($api, $this->firstName, $this->lastName, $this->company, $this->address1, $this->address2, $this->city, $this->state, $this->postalCode, $this->country, $this->email, $this->phone, $this->fax))->execute();
        $this->setHandle($newContact->getDataValue("contact"));
        return $this;
    }

    protected function doCreateNew($api)
    {
        $newContact = (new Commands\AddContact($api, $this->firstName, $this->lastName, $this->company, $this->address1, $this->address2, $this->city, $this->state, $this->postalCode, $this->country, $this->email, $this->phone, $this->fax))->asNew()->execute();
        $this->setHandle($newContact->getDataValue("contact"));
        return $this;
    }

    public function update($api)
    {
        if (!$this->getHandle()) {
            throw new \Exception("Invalid Contact Handle");
        }
        (new Commands\ModifyContact($api, $this->getHandle(), $this->firstName, $this->lastName, $this->company, $this->address1, $this->address2, $this->city, $this->state, $this->postalCode, $this->country, $this->email, $this->phone, $this->fax))->execute();
        return $this;
    }

    public function toArray()
    {
        return ["First Name" => $this->firstName, "Last Name" => $this->lastName, "Company Name" => $this->company, "Address" => $this->address1, "Address 2" => $this->address2, "City" => $this->city, "State" => $this->state, "Postcode" => $this->postalCode, "Country" => $this->country, "Phone" => $this->phone, "Fax" => $this->fax, "Email" => $this->email];
    }

    protected function registrantTriggered($remoteContact)
    {
        return $this->getContactType() == self::REGISTRANT_CONTACT && strcasecmp($this->email, $remoteContact->email) !== 0;
    }
}

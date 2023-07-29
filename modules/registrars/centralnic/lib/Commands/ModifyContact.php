<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class ModifyContact extends AbstractCommand
{
    protected $command = "ModifyContact";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $contactHandle, $firstname, $lastname, $company, $address1, $address2, $city, $state, $postCode, $country, $email, $phone, $fax)
    {
        $this->setParam("contact", $contactHandle)->setParam("firstname", $firstname)->setParam("lastname", $lastname)->setParam("organization", $company)->setParam("street0", $address1)->setParam("street1", $address2)->setParam("city", $city)->setParam("state", $state)->setParam("zip", $postCode)->setParam("country", $country)->setParam("email", $email)->setParam("phone", $phone)->setParam("fax", $fax);
        parent::__construct($api);
    }
}

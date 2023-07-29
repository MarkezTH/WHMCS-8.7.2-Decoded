<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class AddContact extends AbstractCommand
{
    protected $command = "AddContact";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $firstname, $lastname, $company, $address1, $address2, $city, $state, $postalCode, $country, $email, $phone, $fax)
    {
        $this->setParam("NEW", 0)->setParam("PREVERIFY", 1)->setParam("AUTODELETE", 1)->setParam("firstname", $firstname)->setParam("lastname", $lastname)->setParam("organization", $company)->setParam("street0", $address1)->setParam("street1", $address2)->setParam("city", $city)->setParam("state", $state)->setParam("zip", $postalCode)->setParam("country", $country)->setParam("email", $email)->setParam("phone", $phone)->setParam("fax", $fax);
        parent::__construct($api);
    }

    public function asNew()
    {
        $this->setParam("NEW", 1);
        return $this;
    }
}

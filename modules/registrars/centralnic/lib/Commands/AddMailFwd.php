<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class AddMailFwd extends AbstractCommand
{
    protected $command = "AddMailFwd";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $from, $to)
    {
        $this->setParam("from", $from)->setParam("to", $to);
        parent::__construct($api);
    }

    public static function addList($api, $list)
    {
        $errors = [];
        foreach ($list as $emailAddress => $destination) {
            try {
                (new AddMailFwd($api, $emailAddress, $destination))->execute();
            } catch (\Exception $e) {
                $errors[$emailAddress] = $e->getMessage();
            }
        }
        return $errors;
    }
}

<?php

namespace WHMCS\Authorization\Contracts;

interface RoleInterface
{
    public function getId();

    public function allow($itemsToAllow);

    public function deny($itemsToDeny);

    public function getData();

    public function setData($data);
}

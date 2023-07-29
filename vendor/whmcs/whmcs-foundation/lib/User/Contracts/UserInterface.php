<?php

namespace WHMCS\User\Contracts;

interface UserInterface
{
    public function getUsernameAttribute();

    public function hasPermission($permission);
}

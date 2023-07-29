<?php

namespace WHMCS\Authentication\Contracts;

interface Token
{
    public static function factoryFromUser(\WHMCS\User\User $user);

    public function validFormat();

    public function id();

    public function generate();

    public function generateHash();

    public function validateUser($user, $validateIp);
}

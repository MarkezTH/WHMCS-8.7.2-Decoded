<?php

namespace WHMCS\Module\Contracts;

interface SenderModuleInterface
{
    public function settings();

    public function getName();

    public function getDisplayName();

    public function testConnection($params);

    public function send($params, \WHMCS\Mail\Message $message);
}

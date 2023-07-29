<?php

namespace WHMCS\Support\Ticket;

interface RequestorTypes
{
    const ADMIN = "Operator";
    const OWNER = "Owner";
    const USER = "Authorized User";
    const REGISTERED_USER = "Registered User";
    const LEGACY_SUBACCOUNT = "Sub-account";
    const GUEST = "Guest";
}

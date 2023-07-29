<?php

namespace WHMCS\Utility;

class Status
{
    const PENDING = "Pending";
    const PENDING_REGISTRATION = "Pending Registration";
    const PENDING_TRANSFER = "Pending Transfer";
    const ACTIVE = "Active";
    const INACTIVE = "Inactive";
    const CLOSED = "Closed";
    const COMPLETED = "Completed";
    const SUSPENDED = "Suspended";
    const TERMINATED = "Terminated";
    const GRACE = "Grace";
    const REDEMPTION = "Redemption";
    const EXPIRED = "Expired";
    const CANCELLED = "Cancelled";
    const FRAUD = "Fraud";
    const TRANSFERRED_AWAY = "Transferred Away";
    const DRAFT = "Draft";
    const DELIVERED = "Delivered";
    const ON_HOLD = "On Hold";
    const ACCEPTED = "Accepted";
    const LOST = "Lost";
    const DEAD = "Dead";
    const CLIENT_STATUSES = NULL;
    const SERVICE_STATUSES = NULL;
    const DOMAIN_STATUSES = NULL;
    const QUOTE_STATUSES = NULL;

    public static function normalise($status)
    {
        return preg_replace("/[^a-z0-9]/", "", strtolower($status));
    }
}

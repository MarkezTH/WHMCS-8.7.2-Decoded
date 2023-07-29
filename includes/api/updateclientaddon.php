<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$id = (int) App::getFromRequest("id");
try {
    $addon = WHMCS\Service\Addon::with("service")->findOrFail($id);
} catch (Exception $e) {
    $apiresults = ["result" => "error", "message" => "Addon ID Not Found"];
    return NULL;
}
$serviceId = $addon->serviceId;
$currentStatus = $addon->status;
$userId = $addon->service->clientId;
$addonId = $addon->addonId;
$autoRecalculate = stringLiteralToBool(App::getFromRequest("autorecalc"));
$status = $whmcs->get_req_var("status");
$terminationDate = $whmcs->get_req_var("terminationdate");
if (App::isInRequest("addonid")) {
    $newAddonId = (int) App::getFromRequest("addonid");
    if ($newAddonId != $addon->addonId && 0 <= $newAddonId) {
        $addonId = $addon->addonId = $newAddonId;
    }
}
if (App::isInRequest("name")) {
    $addonName = App::getFromRequest("name");
    if ($addonName != $addon->name) {
        $addon->name = $addonName;
    }
}
if (App::isInRequest("billingcycle")) {
    $billingCycle = App::getFromRequest("billingcycle");
    if ($addon->billingCycle != $billingCycle) {
        $addon->billingCycle = $billingCycle;
    }
}
if (App::isInRequest("setupfee")) {
    $setupFee = App::getFromRequest("setupfee");
    if ($setupFee != $addon->setupFee) {
        $addon->setupFee = $setupFee;
    }
}
if ($autoRecalculate) {
    $addon->recurringFee = $addon->recalculateRecurringPrice();
} else {
    if (App::isInRequest("recurring")) {
        $recurring = (double) App::getFromRequest("recurring");
        if ($recurring != $addon->recurringFee) {
            $addon->recurringFee = $recurring;
        }
    }
}
if (App::isInRequest("nextduedate")) {
    $nextDueDate = App::getFromRequest("nextduedate");
    if ($nextDueDate != $addon->nextDueDate) {
        $addon->nextDueDate = App::getFromRequest("nextduedate");
        $addon->nextInvoiceDate = App::getFromRequest("nextduedate");
    }
}
if (App::isInRequest("notes")) {
    $notes = App::getFromRequest("notes");
    if ($notes != $addon->notes) {
        $addon->notes = $notes;
    }
}
if ($status && $status != $currentStatus) {
    switch ($status) {
        case WHMCS\Utility\Status::TERMINATED:
        case WHMCS\Utility\Status::CANCELLED:
            if ((!$terminationDate || $terminationDate == "0000-00-00") && !in_array($currentStatus, [WHMCS\Utility\Status::TERMINATED, WHMCS\Utility\Status::CANCELLED])) {
                $terminationDate = date("Y-m-d");
            }
            break;
        default:
            $terminationDate = "0000-00-00";
            $addon->status = $status;
    }
}
if ($terminationDate) {
    if (!$status) {
        switch ($currentStatus) {
            case WHMCS\Utility\Status::TERMINATED:
            case WHMCS\Utility\Status::CANCELLED:
                if ($terminationDate == "0000-00-00") {
                    $terminationDate = date("Y-m-d");
                }
                break;
            default:
                $terminationDate = "0000-00-00";
        }
    }
    if ($terminationDate != $addon->getRawAttribute("termination_date")) {
        $addon->setAttribute("termination_date", $terminationDate);
    }
}
if ($addon->isDirty()) {
    $addon->save();
    logActivity("Modified Addon - Addon ID: " . $id . " - Service ID: " . $serviceId, $userId);
    $hookParams = ["id" => $id, "userid" => $userId, "serviceid" => $serviceId, "addonid" => $addonId];
    if ($currentStatus != WHMCS\Utility\Status::ACTIVE && $status == WHMCS\Utility\Status::ACTIVE) {
        run_hook("AddonActivated", $hookParams);
    } else {
        if ($currentStatus != WHMCS\Utility\Status::SUSPENDED && $status == WHMCS\Utility\Status::SUSPENDED) {
            run_hook("AddonSuspended", $hookParams);
        } else {
            if ($currentStatus != WHMCS\Utility\Status::TERMINATED && $status == WHMCS\Utility\Status::TERMINATED) {
                run_hook("AddonTerminated", $hookParams);
            } else {
                if ($currentStatus != WHMCS\Utility\Status::CANCELLED && $status == WHMCS\Utility\Status::CANCELLED) {
                    run_hook("AddonCancelled", $hookParams);
                } else {
                    if ($currentStatus != WHMCS\Utility\Status::FRAUD && $status == WHMCS\Utility\Status::FRAUD) {
                        run_hook("AddonFraud", $hookParams);
                    } else {
                        run_hook("AddonEdit", $hookParams);
                    }
                }
            }
        }
    }
    $apiresults = ["result" => "success", "id" => $id];
} else {
    $apiresults = ["result" => "error", "id" => $id, "message" => "Nothing to Update"];
}

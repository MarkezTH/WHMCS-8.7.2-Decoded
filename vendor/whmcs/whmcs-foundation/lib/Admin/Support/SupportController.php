<?php

namespace WHMCS\Admin\Support;

class SupportController
{
    public function getClientServices(\WHMCS\Http\Message\ServerRequest $request, $skipTen = true)
    {
        if ($request->has("skipTen")) {
            $skipTen = $request->get("skipTen") !== "false";
        }
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.client.support.service.full-list", ["output" => $this->getClientServicesOutput($request, $skipTen)])]);
    }

    protected function getClientServicesOutput(\WHMCS\Http\Message\ServerRequest $request, $skipTen = true)
    {
        $ticketId = $request->get("ticketId");
        $clientId = $request->get("clientId");
        $showTen = (bool) $request->get("showTen");
        $ticket = \WHMCS\Support\Ticket::where("userid", $clientId)->where("id", $ticketId)->first();
        $client = \WHMCS\User\Client::find($clientId);
        $clientCurrency = $client && $client->currencyId ? $client->currencyId : 0;
        $selectedRelatedId = 0;
        $selectedRelatedType = "";
        if ($ticket && $ticket->service) {
            $selectedRelatedType = substr($ticket->service, 0, 1);
            $selectedRelatedId = substr($ticket->service, 1);
        }
        $output = [];
        $services = \WHMCS\Service\Service::with("product")->where("userid", $clientId)->orderBy("domainstatus")->orderBy("id", "desc")->get();
        foreach ($services as $service) {
            if ($service->product && !($selectedRelatedType == "S" && $selectedRelatedId == $service->id)) {
                $serviceRegDate = fromMySQLDate($service->registrationDate);
                $serviceNextDueDate = "-";
                if ((new \WHMCS\Billing\Cycles())->isRecurring($service->billingCycle)) {
                    $serviceNextDueDate = fromMySQLDate($service->nextDueDate);
                }
                $serviceAmount = $service->recurringAmount;
                if ($serviceAmount <= 0) {
                    $serviceAmount = $service->firstPaymentAmount;
                }
                $serviceAmount = formatCurrency($serviceAmount, $clientCurrency);
                $name = $service->product->getRawAttribute("name");
                $domain = $service->domain ? " - <a href=\"http://" . $service->domain . "/\" target=\"_blank\">" . $service->domain . "</a>" : "";
                $serviceName = "<a href=\"clientshosting.php?userid=" . $clientId . "&id=" . $service->id . "\"" . " target=\"_blank\">" . $name . "</a>" . $domain;
                $output[] = ["type" => "product", "id" => $service->id, "name" => $serviceName, "amount" => $serviceAmount, "billingCycle" => $service->billingCycle, "registrationDate" => $serviceRegDate, "nextDueDate" => $serviceNextDueDate, "status" => $service->domainStatus];
            }
        }
        $predefinedAddons = \WHMCS\Product\Addon::all()->pluck("name", "id");
        $hostingAddons = \WHMCS\Database\Capsule::table("tblhostingaddons")->join("tblhosting", "tblhosting.id", "=", "tblhostingaddons.hostingid")->join("tblproducts", "tblproducts.id", "=", "tblhosting.packageid")->where("tblhosting.userid", $clientId)->orderBy("status")->orderBy("tblhosting.id", "desc")->get(["tblhostingaddons.*", "tblhosting.id as hosting_id", "tblhosting.domain", "tblproducts.name as product_name"])->all();
        foreach ($hostingAddons as $hostingAddon) {
            if (!($selectedRelatedType == "A" && $selectedRelatedId == $hostingAddon->id)) {
                $hostingId = $hostingAddon->hosting_id;
                $serviceRegDate = fromMySQLDate($hostingAddon->regdate);
                $serviceNextDueDate = "-";
                if ((new \WHMCS\Billing\Cycles())->isRecurring($hostingAddon->billingcycle)) {
                    $serviceNextDueDate = fromMySQLDate($hostingAddon->nextduedate);
                }
                $name = $hostingAddon->name;
                if (!$name) {
                    $name = $predefinedAddons[$hostingAddon->addonid];
                }
                $domain = $hostingAddon->domain ? " - <a href=\"http://" . $hostingAddon->domain . "/\" target=\"_blank\">" . $hostingAddon->domain . "</a>" : "";
                $serviceName = \AdminLang::trans("orders.addon") . " - " . $name . "<br /><a href=\"clientshosting.php?userid=" . $clientId . "&id=" . $hostingId . "\" target=\"_blank\">" . $name . "</a>" . $domain;
                $serviceAmount = formatCurrency($hostingAddon->recurring, $clientCurrency);
                $output[] = ["type" => "addon", "id" => $hostingAddon->id, "name" => $serviceName, "amount" => $serviceAmount, "billingCycle" => $hostingAddon->billingcycle, "registrationDate" => $serviceRegDate, "nextDueDate" => $serviceNextDueDate, "status" => $hostingAddon->status];
            }
        }
        $domainText = \AdminLang::trans("fields.domain");
        $year = \AdminLang::trans("domains.year");
        $years = \AdminLang::trans("domains.years");
        $domains = \WHMCS\Domain\Domain::where("userid", $clientId)->orderBy("status")->orderBy("id", "desc")->get();
        foreach ($domains as $domain) {
            if (!($selectedRelatedType == "D" && $selectedRelatedId == $domain->id)) {
                $serviceName = "<a href=\"clientsdomains.php?userid=" . $clientId . "&id=" . $domain->id . "\"" . " target=\"_blank\">" . $domainText . "</a> - <a href=\"http://" . $domain->domain . "/\"" . " target=\"_blank\">" . $domain->domain . "</a>";
                $billingCycle = $domain->registrationPeriod . " " . $year;
                if (1 < $domain->registrationPeriod) {
                    $billingCycle = $domain->registrationPeriod . " " . $years;
                }
                $serviceAmount = $domain->recurringAmount;
                if ($serviceAmount <= 0) {
                    $serviceAmount = $domain->firstPaymentAmount;
                }
                $output[] = ["type" => "domain", "id" => $domain->id, "name" => $serviceName, "amount" => formatCurrency($serviceAmount, $clientCurrency), "billingCycle" => $billingCycle, "registrationDate" => fromMySQLDate($domain->registrationDate), "nextDueDate" => fromMySQLDate($domain->nextDueDate), "status" => $domain->status];
            }
        }
        $offset = $selectedRelatedType === "" ? 10 : 9;
        if ($skipTen) {
            $output = array_slice($output, $offset);
        }
        if ($showTen) {
            $output = array_slice($output, 0, $offset);
        }
        return $output;
    }

    public function setRelatedService(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ticketId = $request->get("ticketId");
        $clientId = $request->get("clientId");
        $serviceType = $request->get("type");
        $relatedId = $request->get("id");
        try {
            $ticket = \WHMCS\Support\Ticket::where("userid", $clientId)->where("id", $ticketId)->first();
            if (!$ticket) {
                throw new \WHMCS\Exception("Invalid Access Attempt (1)");
            }
            switch ($serviceType) {
                case "addon":
                    $type = "A";
                    break;
                case "domain":
                    $type = "D";
                    break;
                case "product":
                    $type = "S";
                    $newRelatedId = $type . $relatedId;
                    $successTitle = "global.nochange";
                    $successMessage = "global.noChanges";
                    if ($newRelatedId !== $ticket->service) {
                        $originalService = $ticket->service;
                        if (!$originalService) {
                            $originalService = "None";
                        }
                        $original = $this->getRelatedDescriptionFromTypeAndId(substr($originalService, 0, 1), substr($originalService, 1));
                        $new = $this->getRelatedDescriptionFromTypeAndId($type, $relatedId);
                        $ticket->service = $newRelatedId;
                        $ticket->save();
                        if (!function_exists("addTicketLog")) {
                            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "ticketfunctions.php";
                        }
                        addTicketLog($ticket->id, "Related service changed from '" . $original . "' to '" . $new . "'");
                        $successTitle = "global.success";
                        $successMessage = "global.changesuccess";
                    }
                    return new \WHMCS\Http\Message\JsonResponse(["success" => true, "type" => $serviceType, "id" => $relatedId, "successMessage" => \AdminLang::trans($successMessage)]);
                    break;
                default:
                    throw new \WHMCS\Exception("Invalid Access Attempt (2)");
            }
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "errorMessage" => $e->getMessage()]);
        }
    }

    protected function getRelatedDescriptionFromTypeAndId($type, $id)
    {
        try {
            switch ($type) {
                case "A":
                    $addon = \WHMCS\Service\Addon::with("productAddon")->findOrFail($id);
                    $addonName = $addon->name;
                    if (!$addonName) {
                        $addonName = $addon->productAddon->name;
                    }
                    $description = \AdminLang::trans("orders.addon") . " - " . $addonName;
                    break;
                case "D":
                    $domain = \WHMCS\Domain\Domain::findOrFail($id);
                    $description = \AdminLang::trans("fields.domain") . " - " . $domain->domain;
                    break;
                case "S":
                    $service = \WHMCS\Service\Service::with("product")->findOrFail($id);
                    $description = $service->product->getRawAttribute("name") . " - " . $service->domain;
                    break;
                default:
                    $description = "None";
            }
        } catch (\Exception $e) {
            throw new \WHMCS\Exception("No match found for: " . $type . "-" . $id);
        }
        return $description;
    }

    public function getAdditionalData(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = $request->get("clientId", 0);
        $client = \WHMCS\User\Client::with(["contacts", "users"])->where("id", $clientId)->first();
        if (!$client) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => true, "services" => "", "ccs" => []]);
        }
        return new \WHMCS\Http\Message\JsonResponse(["success" => true, "services" => view("admin.client.support.service.full-list", ["output" => $this->getClientServicesOutput($request, false)]), "ccs" => $this->getClientAssociates($client)]);
    }

    private function getClientAssociates($client)
    {
        $ccs = [];
        $cEmail = $client->email;
        $associates = ["fal fa-address-book" => $client->contacts, "fal fa-user" => $client->users];
        foreach ($associates as $class => $list) {
            foreach ($list as $model) {
                $mEmail = $model->email;
                if ($cEmail != $mEmail && !isset($ccs[$mEmail])) {
                    $ccs[$mEmail] = ["name" => $model->fullname, "text" => $mEmail, "iconclass" => $class];
                }
            }
        }
        $ccs = array_values($ccs);
        return $ccs;
    }
}

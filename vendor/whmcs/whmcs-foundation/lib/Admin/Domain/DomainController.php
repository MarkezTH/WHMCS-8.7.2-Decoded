<?php

namespace WHMCS\Admin\Domain;

class DomainController
{
    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $aInt = new \WHMCS\Admin("List Domains");
        $aInt->setResponseType(\WHMCS\Admin::RESPONSE_HTML_MESSAGE);
        $aInt->title = \AdminLang::Trans("services.listdomains");
        $aInt->sidebar = "clients";
        $aInt->icon = "domains";
        $aInt->requiredFiles(["registrarfunctions"]);
        $name = "domains";
        $orderby = "domain";
        $sort = "ASC";
        $pageObj = new \WHMCS\Pagination($name, $orderby, $sort);
        $pageObj->digestCookieData();
        $tbl = new \WHMCS\ListTable($pageObj, 0, $aInt);
        $tbl->setColumns(["checkall", ["id", \AdminLang::trans("fields.id")], ["domain", \AdminLang::trans("fields.domain")], ["clientname", \AdminLang::trans("fields.clientname")], ["registrationperiod", \AdminLang::trans("fields.regperiod")], ["registrar", \AdminLang::trans("fields.registrar")], ["recurringamount", \AdminLang::trans("fields.price")], ["nextduedate", \AdminLang::trans("fields.nextduedate")], ["expirydate", \AdminLang::trans("fields.expirydate")], ["status", \AdminLang::trans("fields.status")], ""]);
        $domainData = new Table\Domain($pageObj);
        $filter = (new \WHMCS\Filter("admin-domains-index"))->setAllowedVars(["clientname", "domain", "status", "registrar", "id", "notes", "subscriptionid"]);
        $searchCriteria = $filter->store()->getFilterCriteria();
        $domainData->execute($searchCriteria);
        $domainList = $pageObj->getData();
        foreach ($domainList as $data) {
            $id = $data["id"];
            $userId = $data["userid"];
            $domain = $data["domain"];
            $amount = $data["recurringamount"];
            $registrar = $data["registrar"];
            $nextDueDate = $data["nextduedate"];
            $expiryDate = $data["expirydate"];
            $subscriptionId = $data["subscriptionid"];
            $registrationDate = $data["registrationdate"];
            $registrationPeriod = $data["registrationperiod"];
            $status = $data["status"];
            $firstName = $data["firstname"];
            $lastName = $data["lastname"];
            $companyName = $data["companyname"];
            $groupId = $data["groupid"];
            $currency = $data["currency"];
            if (!$domain) {
                $domain = "(" . \AdminLang::trans("addons.nodomain") . ")";
            }
            $amount = formatCurrency($amount, $currency);
            $registrationDate = fromMySQLDate($registrationDate);
            $nextDueDate = fromMySQLDate($nextDueDate);
            $expiryDate = fromMySQLDate($expiryDate);
            $yearOrYears = "domains.year";
            if (1 < $registrationPeriod) {
                $yearOrYears .= "s";
            }
            $registrationPeriod .= " " . \AdminLang::trans($yearOrYears);
            $styleStatus = \WHMCS\Utility\Status::normalise($status);
            $checkbox = "<input type=\"checkbox\" name=\"selectedclients[]\"" . " value=\"" . $id . "\" class=\"checkall\" />";
            $domainUri = "clientsdomains.php?userid=" . $userId . "&id=" . $id;
            $domainIdLink = "<a href=\"" . $domainUri . "\">" . $id . "</a>";
            $domainNameLink = "<a href=\"" . $domainUri . "\">" . $domain . "</a>";
            $registrarInterface = new \WHMCS\Module\Registrar();
            $registrarLabel = ucfirst($registrar);
            if ($registrarInterface->load($registrar)) {
                $registrarLabel = $registrarInterface->getDisplayName();
            }
            $statusBadge = "<span class=\"label " . $styleStatus . "\">" . $status . "</span>";
            $expandIcon = "<a href=\"" . routePath("admin-domains-detail", $id) . "\" class=\"view-detail\"><i class=\"fa fa-plus\"></i></a>";
            $tbl->addRow([$checkbox, $domainIdLink, $domainNameLink, $aInt->outputClientLink($userId, $firstName, $lastName, $companyName, $groupId), $registrationPeriod, $registrarLabel, $amount, $nextDueDate, $expiryDate, $statusBadge, $expandIcon]);
        }
        $tbl->setMassActionURL("sendmessage.php?type=domain&multiple=true");
        $tbl->setMassActionBtns("<button type=\"submit\" class=\"btn btn-default\">" . \AdminLang::trans("global.sendmessage") . "</button>");
        $pageObj->setBasePath(routePath("admin-domains-index"));
        $tbl->setShowHidden(\App::getFromRequest("show_hidden"));
        $tableOutput = $tbl->output();
        unset($domainData);
        unset($domainList);
        $aInt->content = view("admin.client.domains.index", ["criteria" => $searchCriteria, "tableOutput" => $tableOutput, "cycles" => $aInt->cyclesDropDown($searchCriteria["billingcycle"], true), "statuses" => (new \WHMCS\Domain\Status())->translatedDropdownOptions([$searchCriteria["status"]]), "registrars" => getRegistrarsDropdownMenu($searchCriteria["registrar"], "registrar", "select-inline", true), "tabStart" => $aInt->beginAdminTabs([\AdminLang::trans("global.searchfilter")]), "tabEnd" => $aInt->endAdminTabs()]);
        return $aInt->display();
    }

    public function sslCheck(\WHMCS\Http\Message\ServerRequest $request)
    {
        $domain = trim($request->get("domain"));
        $userId = $request->get("userid");
        $sslStatus = \WHMCS\Domain\Ssl\Status::factory($userId, $domain)->syncAndSave();
        $response = ["image" => $sslStatus->getImagePath(), "tooltip" => $sslStatus->getTooltipContent(), "class" => $sslStatus->getClass()];
        if ($request->get("details")) {
            $issuerName = "";
            if ($sslStatus->issuerName) {
                $issuerName = $sslStatus->issuerOrg;
                if (!$issuerName) {
                    $issuerName = $sslStatus->issuerName;
                }
            }
            $response["issuerName"] = $issuerName;
            $expiryDate = $sslStatus->expiryDate;
            if ($expiryDate) {
                $expiryDate = $expiryDate->endOfDay()->toAdminDateTimeFormat();
            } else {
                $expiryDate = "-";
            }
            $response["expiryDate"] = $expiryDate;
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function domainDetail(\WHMCS\Http\Message\ServerRequest $request)
    {
        $domain = \WHMCS\Domain\Domain::findOrFail($request->attributes()->get("domainid"));
        $data = [[\AdminLang::trans("fields.ordernum") => $domain->orderId ? $domain->order->orderNumber : "", \AdminLang::trans("fields.regdate") => $domain->registrationDate->toAdminDateFormat(), \AdminLang::trans("orders.ordertype") => $domain->type], [\AdminLang::trans("domains.dnsmanagement") => $domain->dnsmanagement ? "Yes" : "No", \AdminLang::trans("domains.emailforwarding") => $domain->emailforwarding ? "Yes" : "No", \AdminLang::trans("domains.idprotection") => $domain->idprotection ? "Yes" : "No", \AdminLang::trans("domains.premiumDomain") => $domain->is_premium ? "Yes" : "No"], [\AdminLang::trans("fields.paymentmethod") => $domain->paymentGateway()->name()->first()->value]];
        return new \WHMCS\Http\Message\JsonResponse(["output" => (new \WHMCS\View\Bootstrap())->renderKeyValuePairsInColumns(4, $data)]);
    }

    public function subscriptionInfo(\WHMCS\Http\Message\ServerRequest $request)
    {
        $relatedId = $request->get("id");
        try {
            $relatedItem = \WHMCS\Domain\Domain::findOrFail($relatedId);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid Access Attempt");
        }
        return \WHMCS\Payment\Subscription::getInfo($relatedItem);
    }

    public function subscriptionCancel(\WHMCS\Http\Message\ServerRequest $request)
    {
        $relatedId = $request->get("id");
        try {
            $relatedItem = \WHMCS\Domain\Domain::findOrFail($relatedId);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid Access Attempt");
        }
        return \WHMCS\Payment\Subscription::cancel($relatedItem);
    }
}

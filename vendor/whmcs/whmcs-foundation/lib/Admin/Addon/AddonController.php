<?php

namespace WHMCS\Admin\Addon;

class AddonController
{
    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $aInt = new \WHMCS\Admin("List Addons");
        $aInt->setResponseType(\WHMCS\Admin::RESPONSE_HTML_MESSAGE);
        $aInt->title = \AdminLang::Trans("services.listaddons");
        $aInt->sidebar = "clients";
        $aInt->icon = "productaddons";
        $aInt->requiredFiles(["gatewayfunctions"]);
        $name = "addons";
        $orderby = "id";
        $sort = "DESC";
        $pageObj = new \WHMCS\Pagination($name, $orderby, $sort);
        $pageObj->digestCookieData();
        $tbl = new \WHMCS\ListTable($pageObj, 0, $aInt);
        $tbl->setColumns(["checkall", ["id", \AdminLang::trans("fields.id")], ["addon", \AdminLang::trans("fields.addon")], ["product", \AdminLang::trans("fields.product")], ["clientname", \AdminLang::trans("fields.clientname")], ["billingcycle", \AdminLang::trans("fields.billingcycle")], ["recurring", \AdminLang::trans("fields.price")], ["nextduedate", \AdminLang::trans("fields.nextduedate")], ["status", \AdminLang::trans("fields.status")], ""]);
        $predefinedAddonsList = \WHMCS\Database\Capsule::table("tbladdons")->pluck("name", "id")->all();
        $addonData = new Table\Addon($pageObj);
        $filter = (new \WHMCS\Filter("admin-addons-index"))->setAllowedVars(["clientname", "addon", "type", "package", "billingcycle", "server", "paymentmethod", "status", "domain", "customfieldvalue", "customfield"]);
        $searchCriteria = $filter->store()->getFilterCriteria();
        $addonData->execute($searchCriteria);
        $addonList = $pageObj->getData();
        foreach ($addonList as $data) {
            $aId = $data["id"];
            $id = $data["hostingid"];
            $addonId = $data["addonid"];
            $userId = $data["userid"];
            $addonName = $data["addonname"];
            $domain = $data["domain"];
            $dType = $data["type"];
            $dPackage = $data["name"];
            $upgrades = $data["upgrades"];
            $dPaymentMethod = $data["paymentmethod"];
            $amount = $data["recurring"];
            $billingCycle = $data["billingcycle"];
            $nextDueDate = $data["nextduedate"];
            $status = $data["status"];
            if (!$addonName) {
                $addonName = $predefinedAddonsList[$addonId];
            }
            $nextDueDate = fromMySQLDate($nextDueDate);
            $firstName = $data["firstname"];
            $lastName = $data["lastname"];
            $companyName = $data["companyname"];
            $groupId = $data["groupid"];
            $currency = $data["currency"];
            if (!$domain) {
                $domain = "(" . \AdminLang::trans("addons.nodomain") . ")";
            }
            $amount = formatCurrency($amount, $currency);
            if (in_array($billingCycle, ["One Time", "Free Account", "Free"])) {
                $nextDueDate = "-";
            }
            $billingCycle = \AdminLang::trans("billingcycles." . str_replace(["-", "account", " "], "", strtolower($billingCycle)));
            $checkbox = "<input type=\"checkbox\" name=\"selectedclients[]\"" . " value=\"" . $id . "\" class=\"checkall\" />";
            $addonUri = "clientsservices.php?userid=" . $userId . "&id=" . $id . "&aid=" . $aId;
            $addonIdLink = "<a href=\"" . $addonUri . "\">" . $aId . "</a>";
            $hostingLink = "<a href=\"clientsservices.php?userid=" . $userId . "&id=" . $id . "\">" . $dPackage . " - " . $domain . "</a>";
            $statusBadge = "<span class=\"label " . strtolower($status) . "\">" . $status . "</span>";
            $expandIcon = "<a href=\"" . routePath("admin-addons-detail", $aId) . "\" class=\"view-detail\"><i class=\"fa fa-plus\"></i></a>";
            $tbl->addRow([$checkbox, $addonIdLink, $addonName, $hostingLink, $aInt->outputClientLink($userId, $firstName, $lastName, $companyName, $groupId), $billingCycle, $amount, $nextDueDate, $statusBadge, $expandIcon]);
        }
        $predefinedAddonsList += \WHMCS\Database\Capsule::table("tblhostingaddons")->where("name", "!=", "")->pluck("name", "name")->all();
        asort($predefinedAddonsList);
        $tbl->setMassActionURL("sendmessage.php?type=product&multiple=true");
        $tbl->setMassActionBtns("<button type=\"submit\" class=\"btn btn-default\">" . \AdminLang::trans("global.sendmessage") . "</button>");
        $pageObj->setBasePath(routePath("admin-addons-index"));
        $tbl->setShowHidden(\App::getFromRequest("show_hidden"));
        $tableOutput = $tbl->output();
        unset($addonData);
        unset($addonList);
        $serverData = \WHMCS\View\Helper::getServerDropdownOptions($searchCriteria["server"]);
        $servers = $serverData["servers"];
        $disabledServers = $serverData["disabledServers"];
        $customFields = \WHMCS\CustomField::where("type", "addon")->pluck("fieldname", "id")->all();
        $customFieldsForm = new \WHMCS\Form();
        $customFieldsDropdown = $customFieldsForm->dropdown("customfield", $customFields, $searchCriteria["customfield"], "", true);
        $aInt->content = view("admin.client.addons.index", ["criteria" => $searchCriteria, "tableOutput" => $tableOutput, "products" => $aInt->productDropDown((int) $searchCriteria["package"], false, true), "addonsList" => $predefinedAddonsList, "paymentMethods" => paymentMethodsSelection(\AdminLang::trans("global.any")), "cycles" => $aInt->cyclesDropDown($searchCriteria["billingcycle"], true), "servers" => $servers . $disabledServers, "statuses" => $aInt->productStatusDropDown($searchCriteria["status"], true), "customFields" => $customFieldsDropdown, "tabStart" => $aInt->beginAdminTabs([\AdminLang::trans("global.searchfilter")]), "tabEnd" => $aInt->endAdminTabs()]);
        return $aInt->display();
    }

    public function addonDetail(\WHMCS\Http\Message\ServerRequest $request)
    {
        $addon = \WHMCS\Service\Addon::with("order", "service", "productAddon")->findOrFail($request->attributes()->get("addonid"));
        $data = [[\AdminLang::trans("fields.ordernum") => $addon->orderId ? $addon->order->orderNumber : "", \AdminLang::trans("fields.regdate") => $addon->registrationDate->toAdminDateFormat()], [\AdminLang::trans("fields.server") => $addon->serverId ? $addon->serverModel->name : "", \AdminLang::trans("fields.parentdomain") => $addon->service->domain], [\AdminLang::trans("fields.paymentmethod") => $addon->paymentGateway()->name()->first()->value]];
        return new \WHMCS\Http\Message\JsonResponse(["output" => (new \WHMCS\View\Bootstrap())->renderKeyValuePairsInColumns(4, $data)]);
    }

    public function subscriptionInfo(\WHMCS\Http\Message\ServerRequest $request)
    {
        $relatedId = $request->get("id");
        try {
            $relatedItem = \WHMCS\Service\Addon::findOrFail($relatedId);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid Access Attempt");
        }
        return \WHMCS\Payment\Subscription::getInfo($relatedItem);
    }

    public function subscriptionCancel(\WHMCS\Http\Message\ServerRequest $request)
    {
        $relatedId = $request->get("id");
        try {
            $relatedItem = \WHMCS\Service\Addon::findOrFail($relatedId);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid Access Attempt");
        }
        return \WHMCS\Payment\Subscription::cancel($relatedItem);
    }
}

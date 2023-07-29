<?php

namespace WHMCS\Admin\Addon\Table;

class Addon extends \WHMCS\TableModel
{
    public function _execute($implementationData = [])
    {
        return $this->getAddons($implementationData);
    }

    protected function getAddons($criteria = NULL)
    {
        $query = $this->startQuery($criteria);
        $inactiveClients = $this->startQuery($criteria);
        $inactiveClients->whereIn("tblclients.status", ["Inactive", "Closed"])->distinct();
        $this->getPageObj()->setHiddenCount($inactiveClients->count(["tblclients.id"]));
        if (\App::isInRequest("show_hidden") && !\App::getFromRequest("show_hidden") || !\App::isInRequest("show_hidden")) {
            $query->where("tblclients.status", "Active");
        }
        $this->getPageObj()->setNumResults($query->count());
        $orderBy = $this->getPageObj()->getOrderBy();
        if ($orderBy == "product") {
            $orderBy = "tblproducts.name";
        } else {
            if ($orderBy == "clientname") {
                $query->orderBy("tblclients.firstname", $this->getPageObj()->getSortDirection());
                $orderBy = "tblclients.lastname";
            } else {
                if ($orderBy == "addon") {
                    $orderBy = "tblhostingaddons.name";
                }
            }
        }
        $query->orderBy($orderBy, $this->getPageObj()->getSortDirection())->limit($this->getRecordLimit())->offset($this->getRecordOffset());
        $result = $query->get(["tblhostingaddons.*", "tblhostingaddons.name AS addonname", "tblhosting.domain", "tblhosting.userid", "tblclients.firstname", "tblclients.lastname", "tblclients.companyname", "tblclients.groupid", "tblclients.currency", "tblproducts.name", "tblproducts.type"])->all();
        return json_decode(json_encode($result), true);
    }

    private function startQuery($criteria = NULL)
    {
        $query = \WHMCS\Database\Capsule::table("tblhostingaddons")->join("tblclients", "tblclients.id", "=", "tblhostingaddons.userid")->join("tblhosting", "tblhosting.id", "=", "tblhostingaddons.hostingid")->join("tblproducts", "tblproducts.id", "=", "tblhosting.packageid");
        if (is_array($criteria)) {
            if ($criteria["clientname"]) {
                $query->where(\WHMCS\Database\Capsule::raw("concat(firstname, ' ', lastname)"), "like", "%" . $criteria["clientname"] . "%");
            }
            if ($criteria["addon"]) {
                if (is_numeric($criteria["addon"])) {
                    $query->where("tblhostingaddons.addonid", $criteria["addon"]);
                } else {
                    $query->where("tblhostingaddons.name", $criteria["addon"]);
                }
            }
            if ($criteria["type"]) {
                $query->where("tblproducts.type", $criteria["type"]);
            }
            if ($criteria["package"]) {
                $query->where("tblproducts.id", $criteria["package"]);
            }
            if ($criteria["billingcycle"]) {
                $query->where("tblhostingaddons.billingcycle", $criteria["billingcycle"]);
            }
            if ($criteria["server"]) {
                $query->where(function (\Illuminate\Database\Query\Builder $queryFunction) use($criteria) {
                    $queryFunction->where("tblhostingaddons.server", $criteria["server"])->orWhere("tblhosting.server", $criteria["server"]);
                });
            }
            if ($criteria["paymentmethod"]) {
                $query->where("tblhostingaddons.paymentmethod", $criteria["paymentmethod"]);
            }
            if ($criteria["status"]) {
                $query->where("tblhostingaddons.status", $criteria["status"]);
            }
            if ($criteria["domain"]) {
                $query->where("tblhosting.domain", "like", "%" . $criteria["domain"] . "%");
            }
            if ($criteria["customfield"] && empty($criteria["customfieldvalue"])) {
                $ids = \WHMCS\Database\Capsule::table("tblcustomfields")->join("tblhostingaddons", "tblcustomfields.relid", "=", "tblhostingaddons.hostingid")->where("tblcustomfields.type", "addon")->where("tblcustomfields.id", $criteria["customfield"])->pluck("tblhostingaddons.hostingid")->all();
                $query->whereIn("tblhosting.id", $ids);
            } else {
                if ($criteria["customfieldvalue"]) {
                    if ($criteria["customfield"]) {
                        $ids = \WHMCS\Database\Capsule::table("tblcustomfieldsvalues")->where("fieldid", (int) $criteria["customfield"])->where("value", "like", "%" . $criteria["customfieldvalue"] . "%")->pluck("relid")->all();
                    } else {
                        $ids = \WHMCS\Database\Capsule::table("tblcustomfieldsvalues")->join("tblcustomfields", "tblcustomfields.id", "=", "tblcustomfieldsvalues.fieldid")->where("tblcustomfields.type", "addon")->where("tblcustomfieldsvalues.value", "like", "%" . $criteria["customfieldvalue"] . "%")->pluck("tblcustomfieldsvalues.relid")->all();
                    }
                    $query->whereIn("tblhostingaddons.id", $ids);
                }
            }
        }
        return $query;
    }
}

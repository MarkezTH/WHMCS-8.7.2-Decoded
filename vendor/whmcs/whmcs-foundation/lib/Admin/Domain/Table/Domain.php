<?php

namespace WHMCS\Admin\Domain\Table;

class Domain extends \WHMCS\TableModel
{
    public function _execute($implementationData = [])
    {
        return $this->getDomains($implementationData);
    }

    public function getDomains($criteria = NULL)
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
        if ($orderBy == "clientname") {
            $query->orderBy("tblclients.firstname", $this->getPageObj()->getSortDirection());
            $orderBy = "tblclients.lastname";
        }
        $query->orderBy($orderBy, $this->getPageObj()->getSortDirection())->limit($this->getRecordLimit())->offset($this->getRecordOffset());
        $result = $query->get(["tbldomains.*", "tblclients.firstname", "tblclients.lastname", "tblclients.companyname", "tblclients.groupid", "tblclients.currency"])->all();
        return json_decode(json_encode($result), true);
    }

    private function startQuery($criteria = NULL)
    {
        $query = \WHMCS\Database\Capsule::table("tbldomains")->join("tblclients", "tblclients.id", "=", "tbldomains.userid");
        if (is_array($criteria)) {
            if ($criteria["clientname"]) {
                $query->where(\WHMCS\Database\Capsule::raw("concat(firstname, ' ', lastname)"), "like", "%" . $criteria["clientname"] . "%");
            }
            if ($criteria["domain"]) {
                $query->where("tbldomains.domain", "like", "%" . $criteria["domain"] . "%");
            }
            if ($criteria["status"]) {
                $query->where("tbldomains.status", $criteria["status"]);
            }
            if ($criteria["registrar"]) {
                switch ($criteria["registrar"]) {
                    case "none":
                        $query->where("tbldomains.registrar", "=", "");
                        break;
                    default:
                        $query->where("tbldomains.registrar", $criteria["registrar"]);
                }
            }
            if ($criteria["id"]) {
                $query->where("tbldomains.id", $criteria["id"]);
            }
            if ($criteria["notes"]) {
                $query->where("tbldomains.additionalnotes", "like", "%" . $criteria["notes"] . "%");
            }
            if ($criteria["subscriptionid"]) {
                $query->where("tbldomains.subscriptionid", "like", "%" . $criteria["subscriptionid"] . "%");
            }
        }
        return $query;
    }
}

<?php

class Plesk_Manager_V1680 extends Plesk_Manager_V1670
{
    protected function _getServicePlanAddons()
    {
        $data = Plesk_Registry::getInstance()->api->service_plan_addon_get();
        $data = $data->xpath("//service-plan-addon/get/result");
        return $data;
    }

    protected function _createServicePlanAddon($data)
    {
        $data = Plesk_Registry::getInstance()->api->service_plan_addon_create($data);
        $data = $data->xpath("//service-plan-addon/add/result");
        return $data[0];
    }

    protected function _getWebspacesUsage($params)
    {
        $usage = [];
        $webspaces = Plesk_Registry::getInstance()->api->webspace_usage_get_by_name(["domains" => $params["domains"]]);
        foreach ($webspaces->xpath("//webspace/get/result") as $result) {
            try {
                $this->_checkErrors($result);
                if (!empty($result->data)) {
                    $domainName = (string) $result->data->gen_info->name;
                    $usage[$domainName]["diskusage"] = (double) $result->data->gen_info->real_size;
                    $usage[$domainName]["disk_usage"] = $usage[$domainName]["diskusage"];
                    $usage[$domainName]["bwusage"] = (double) $result->data->stat->traffic;
                    $usage[$domainName]["bandwidth_usage"] = $usage[$domainName]["bwusage"];
                    $usage[$domainName]["domain_usage"] = (int) $result->data->stat->domains;
                    $usage[$domainName]["subdomain_usage"] = (int) $result->data->stat->subdom;
                    $usage[$domainName]["mailbox_usage"] = (int) $result->data->stat->box;
                    $usage[$domainName]["db_usage"] = (int) $result->data->stat->db;
                    $diskUsageStats = $result->data->disk_usage;
                    $usage[$domainName]["mysql_usage"] = (double) ($diskUsageStats->dbases ?? $diskUsageStats->mysql_dbases);
                    $resourceUsage = $result->data->_obfuscated_7265736F757263652D7573616765_;
                    foreach ($resourceUsage->resource as $resource) {
                        switch ((string) $resource->name) {
                            case "max_dom_aliases":
                                $usage[$domainName]["aliases_usage"] = (int) $resource->value;
                                break;
                        }
                    }
                    $usage[$domainName] = array_merge($usage[$domainName], $this->_getLimits($result->data->limits));
                }
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        foreach ($webspaces->xpath("//site/get/result") as $result) {
            try {
                $this->_checkErrors($result);
                if (!empty($result->data)) {
                    $filter = $result->xpath("filter-id");
                    $parentDomainName = (string) reset($filter);
                    $usage[$parentDomainName]["bwusage"] = isset($usage[$parentDomainName]["bwusage"]) ? $usage[$parentDomainName]["bwusage"] + (double) $result->data->stat->traffic : 0;
                    $usage[$parentDomainName]["bandwidth_usage"] = $usage[$parentDomainName]["bwusage"];
                }
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        foreach ($usage as $domainName => $domainUsage) {
            foreach ($domainUsage as $param => $value) {
                switch ($param) {
                    case "aliases_limit":
                    case "aliases_usage":
                    case "db_usage":
                    case "domain_limit":
                    case "domain_usage":
                    case "mailbox_usage":
                    case "subdomain_limit":
                    case "subdomain_usage":
                    default:
                        $usage[$domainName][$param] = $usage[$domainName][$param] / 1048576;
                }
            }
        }
        return $usage;
    }

    protected function _getResellersUsage($params)
    {
        $usage = [];
        $data = Plesk_Registry::getInstance()->api->reseller_get_usage_by_login(["logins" => $params["usernames"]]);
        foreach ($data->xpath("//reseller/get/result") as $result) {
            try {
                $this->_checkErrors($result);
                $parentId = $result->id;
                $login = (string) $result->data->_obfuscated_67656E2D696E666F_->login;
                $usage[$login]["diskusage"] = (double) $result->data->stat->_obfuscated_6469736B2D7370616365_;
                $usage[$login]["bwusage"] = (double) $result->data->stat->traffic;
                $usage[$login]["subaccounts"] = (int) $result->data->stat->_obfuscated_6163746976652D636C69656E7473_;
                $usage[$login]["domain_usage"] = (int) $result->data->stat->_obfuscated_6163746976652D646F6D61696E73_;
                $usage[$login]["subdomain_usage"] = (int) $result->data->stat->subdomains;
                $usage[$login]["mailbox_usage"] = (int) $result->data->stat->postboxs;
                $usage[$login]["db_usage"] = (int) $result->data->stat->_obfuscated_646174612D6261736573_;
                $usage[$login]["aliases_usage"] = (int) $result->data->stat->_obfuscated_646F6D61696E2D616C6961736573_;
                $customers = Plesk_Registry::getInstance()->api->reseller_get_customers_by_id(["ownerId" => $parentId]);
                $parentIds = [$parentId];
                foreach ($customers->customer->get->result as $customer) {
                    if (isset($customer->id)) {
                        $parentIds[] = (int) $customer->id;
                    }
                }
                $databaseUsage = Plesk_Registry::getInstance()->api->reseller_get_customer_db_usage_by_id(["ownerIds" => $parentIds]);
                $usage[$login]["mysql_usage"] = 0;
                foreach ($databaseUsage->webspace->get->result as $webspace) {
                    if (isset($webspace->data->disk_usage->dbases)) {
                        $dbUsage = $webspace->data->disk_usage->dbases;
                    } else {
                        if (isset($webspace->data->disk_usage->mysql_dbases)) {
                            $dbUsage = $webspace->data->disk_usage->mysql_dbases;
                        } else {
                            $dbUsage = 0;
                        }
                    }
                    $usage[$login]["mysql_usage"] += (double) $dbUsage;
                }
                unset($dbUsage);
                $usage[$login]["disk_usage"] = $usage[$login]["diskusage"];
                $usage[$login]["bandwidth_usage"] = $usage[$login]["bwusage"];
                $usage[$login] = array_merge($usage[$login], $this->_getLimits($result->data->limits));
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        foreach ($usage as $login => $loginUsage) {
            foreach ($loginUsage as $param => $value) {
                switch ($param) {
                    case "aliases_limit":
                    case "aliases_usage":
                    case "db_usage":
                    case "domain_limit":
                    case "domain_usage":
                    case "mailbox_usage":
                    case "subaccounts":
                    case "subdomain_limit":
                    case "subdomain_usage":
                    default:
                        $usage[$login][$param] = $usage[$login][$param] / 1048576;
                }
            }
        }
        return $usage;
    }
}

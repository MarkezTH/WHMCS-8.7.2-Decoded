<?php

class Plesk_Manager_V1632 extends Plesk_Manager_V1630
{
    protected function _processAddons($params)
    {
        parent::_processAddons($params);
    }

    protected function _addWebspace($params)
    {
        parent::_addWebspace($params);
    }

    protected function _getSharedIpv4($params)
    {
        return $this->_getIp($params);
    }

    protected function _getSharedIpv6($params)
    {
        return $this->_getIp($params, Plesk_Object_Ip::IPV6);
    }

    protected function _getFreeDedicatedIpv4()
    {
        return $this->_getFreeDedicatedIp();
    }

    protected function _getFreeDedicatedIpv6()
    {
        return $this->_getFreeDedicatedIp(Plesk_Object_Ip::IPV6);
    }

    protected function _getIpList($type = NULL, $version)
    {
        $ipList = [];
        if (is_null($result)) {
            $result = Plesk_Registry::getInstance()->api->ip_get();
        }
        foreach ($result->ip->get->result->addresses->ip_info as $item) {
            if ($type === (string) $item->type) {
                $ip = (string) $item->ip_address;
                if (Plesk_Object_Ip::IPV6 !== $version || $this->_isIpv6($ip)) {
                    if (!(Plesk_Object_Ip::IPV4 === $version && $this->_isIpv6($ip))) {
                        $ipList[] = $ip;
                    }
                }
            }
        }
        return $ipList;
    }

    protected function _getFreeDedicatedIp($version = Plesk_Object_Ip::IPV4)
    {
        $ipListUse = [];
        $ipListFree = [];
        $ipList = $this->_getIpList(Plesk_Object_Ip::DEDICATED, $version);
        if (is_null($domains)) {
            $domains = Plesk_Registry::getInstance()->api->webspaces_get();
        }
        foreach ($domains->xpath("//webspace/get/result") as $item) {
            try {
                $this->_checkErrors($item);
                foreach ($item->data->hosting->vrt_hst->ip_address as $ip) {
                    $ipListUse[(string) $ip] = (string) $ip;
                }
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        foreach ($ipList as $ip) {
            if (!in_array($ip, $ipListUse)) {
                $ipListFree[] = $ip;
            }
        }
        $freeIp = reset($ipListFree);
        if (empty($freeIp)) {
            throw new Exception(Plesk_Registry::getInstance()->translator->translate("ERROR_NO_FREE_DEDICATED_IPTYPE", ["TYPE" => Plesk_Object_Ip::IPV6 == $version ? "IPv6" : "IPv4"]));
        }
        return $freeIp;
    }

    protected function _getWebspacesUsage($params)
    {
        return parent::_getWebspacesUsage($params);
    }

    protected function _changeSubscriptionIp($params)
    {
        $webspace = Plesk_Registry::getInstance()->api->webspace_get_by_name(["domain" => $params["domain"]]);
        $ipDedicatedList = $this->_getIpList(Plesk_Object_Ip::DEDICATED);
        foreach ($webspace->webspace->get->result->data->hosting->vrt_hst->ip_address as $ip) {
            $ip = (string) $ip;
            $oldIp[$this->_isIpv6($ip) ? Plesk_Object_Ip::IPV6 : Plesk_Object_Ip::IPV4] = $ip;
        }
        $ipv4Address = $oldIp[Plesk_Object_Ip::IPV4] ?? "";
        $ipv6Address = $oldIp[Plesk_Object_Ip::IPV6] ?? "";
        if ($params["configoption3"] === "IPv4 none; IPv6 shared" || $params["configoption3"] == "IPv4 none; IPv6 dedicated") {
            $ipv4Address = "";
        }
        if ($params["configoption3"] === "IPv4 shared; IPv6 none" || $params["configoption3"] == "IPv4 dedicated; IPv6 none") {
            $ipv6Address = "";
        }
        if (!empty($params["ipv4Address"])) {
            if (isset($oldIp[Plesk_Object_Ip::IPV4]) && $oldIp[Plesk_Object_Ip::IPV4] != $params["ipv4Address"] && (!in_array($oldIp[Plesk_Object_Ip::IPV4], $ipDedicatedList, true) || !in_array($params["ipv4Address"], $ipDedicatedList))) {
                $ipv4Address = $params["ipv4Address"];
            } else {
                if (!isset($oldIp[Plesk_Object_Ip::IPV4])) {
                    $ipv4Address = $params["ipv4Address"];
                }
            }
        }
        if (!empty($params["ipv6Address"])) {
            if (isset($oldIp[Plesk_Object_Ip::IPV6]) && $oldIp[Plesk_Object_Ip::IPV6] != $params["ipv6Address"] && (!in_array($oldIp[Plesk_Object_Ip::IPV6], $ipDedicatedList, true) || !in_array($params["ipv6Address"], $ipDedicatedList))) {
                $ipv6Address = $params["ipv6Address"];
            } else {
                if (!isset($oldIp[Plesk_Object_Ip::IPV6])) {
                    $ipv6Address = $params["ipv6Address"];
                }
            }
        }
        if (!empty($ipv4Address) || !empty($ipv6Address)) {
            Plesk_Registry::getInstance()->api->webspace_set_ip(["domain" => $params["domain"], "ipv4Address" => $ipv4Address, "ipv6Address" => $ipv6Address]);
        }
    }

    protected function _listAccounts($params)
    {
        $data = Plesk_Registry::getInstance()->api->webspace_get_all([]);
        $response = [];
        foreach ($data->xpath("//webspace/get/result") as $webSpace) {
            $webSpaceData = $webSpace->data->gen_info;
            if ($webSpaceData) {
                $planData = $webSpace->data->subscriptions;
                $planData = (array) $planData->subscription->plan;
                $planGuid = $planData["plan-guid"];
                $webSpaceDataArray = (array) $webSpaceData;
                $ownerId = $webSpaceDataArray["owner-id"] ?? NULL;
                if ($ownerId) {
                    try {
                        $ownerData = Plesk_Registry::getInstance()->api->customer_get_by_id(["id" => $ownerId]);
                        list($ownerData) = $ownerData->xpath("//customer/get/result");
                    } catch (Exception $e) {
                        if ($e->getMessage() === "Client does not exist") {
                            $ownerData = Plesk_Registry::getInstance()->api->reseller_get_by_id(["id" => $ownerId]);
                            list($ownerData) = $ownerData->xpath("//reseller/get/result");
                        } else {
                            throw $e;
                        }
                    }
                    $username = $this->_coalesce($this->_firstElementString($webSpace->data->hosting->vrt_hst->xpath("property[name='ftp_login']/value")), $this->_firstElementString($ownerData->xpath("//login")));
                    $planName = "None";
                    if ($planGuid !== NULL) {
                        try {
                            $servicePlan = Plesk_Registry::getInstance()->api->service_plan_get_by_guid(["planGuids" => [$planGuid]]);
                            $planName = $servicePlan->xpath("//service-plan/get/result/name")[0]->__toString();
                        } catch (Exception $e) {
                        }
                    }
                    $status = WHMCS\Service\Status::ACTIVE;
                    if ((int) $webSpaceDataArray["status"]) {
                        $status = WHMCS\Service\Status::SUSPENDED;
                    }
                    $ipAddress = $webSpaceDataArray["dns_ip_address"];
                    if (is_array($ipAddress)) {
                        $ipAddress = $ipAddress[0];
                    }
                    $response[] = ["name" => $username, "email" => $ownerData->xpath("//email")[0]->__toString(), "username" => $username, "domain" => $webSpaceDataArray["name"], "uniqueIdentifier" => $webSpaceDataArray["name"], "product" => $planName, "primaryip" => $ipAddress, "created" => $webSpaceDataArray["cr_date"] . " 00:00:00", "status" => $status];
                }
            }
        }
        return $response;
    }

    protected function _coalesce(...$inputs)
    {
        foreach ($inputs as $input) {
            if (0 < strlen($input)) {
                return $input;
            }
        }
        return "";
    }

    protected function _firstElementString($input)
    {
        if (!$input || $input === false) {
            return "";
        }
        if (is_array($input)) {
            $input = array_shift($input);
        }
        return $input->__toString();
    }

    protected function _getWebspaceByDomain($domain)
    {
        return Plesk_Registry::getInstance()->api->webspace_get_by_name(["domain" => $domain]);
    }
}

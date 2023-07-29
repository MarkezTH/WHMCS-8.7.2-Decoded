<?php

class Plesk_Manager_V1640 extends Plesk_Manager_V1635
{
    protected function _getWebspacesUsage($params)
    {
        $usage = [];
        $webspaces = Plesk_Registry::getInstance()->api->webspace_usage_get_by_name(["domains" => $params["domains"]]);
        foreach ($webspaces->xpath("//webspace/get/result") as $result) {
            try {
                $this->_checkErrors($result);
                $domainName = (string) $result->data->gen_info->name;
                if (!empty($domainName)) {
                    $usage[$domainName]["diskusage"] = (double) $result->data->gen_info->real_size;
                    $resourceUsage = (array) $result->data->xpath("resource-usage");
                    $resourceUsage = reset($resourceUsage);
                    foreach ($resourceUsage->resource as $resource) {
                        $name = (string) $resource->name;
                        if ("max_traffic" == $name) {
                            $usage[$domainName]["bwusage"] = (double) $resource->value;
                            $limits = is_null($result->data->limits) ? [] : $this->_getLimits($result->data->limits);
                            $usage[$domainName] = array_merge($usage[$domainName], $limits);
                            foreach ($usage[$domainName] as $param => $value) {
                                $usage[$domainName][$param] = $usage[$domainName][$param] / 1048576;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        return $usage;
    }
}

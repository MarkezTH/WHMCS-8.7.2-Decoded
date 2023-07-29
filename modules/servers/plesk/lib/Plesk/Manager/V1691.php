<?php

class Plesk_Manager_V1691 extends Plesk_Manager_V1680
{
    protected function _getExtensions($params)
    {
        $data = Plesk_Registry::getInstance()->api->get_extensions();
        $data = $data->xpath("//extension/get/result");
        return $data[0];
    }

    protected function _callExtension($params)
    {
        $data = Plesk_Registry::getInstance()->api->call_extension($params);
        $data = $data->xpath("//extension/call/result");
        return $data[0];
    }
}

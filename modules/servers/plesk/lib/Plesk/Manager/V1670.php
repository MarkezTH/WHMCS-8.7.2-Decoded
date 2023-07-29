<?php

class Plesk_Manager_V1670 extends Plesk_Manager_V1660
{
    protected function _generateCSR($params)
    {
        return parent::_generateCSR($params);
    }

    protected function _installSsl($params)
    {
        return parent::_installSsl($params);
    }

    protected function _getLicenseKey($params)
    {
        $data = Plesk_Registry::getInstance()->api->get_license_key();
        $data = $data->xpath("//server/get/result");
        return $data[0];
    }
}

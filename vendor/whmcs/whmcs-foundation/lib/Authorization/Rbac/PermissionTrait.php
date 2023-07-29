<?php

namespace WHMCS\Authorization\Rbac;

trait PermissionTrait
{
    protected $permissionData = [];

    public function setData($data = [])
    {
        $this->permissionData = $data;
    }

    public function getData()
    {
        if (!is_array($this->permissionData)) {
            $this->permissionData = [];
        }
        return $this->permissionData;
    }

    public function isAllowed($item)
    {
        if (!empty($this->getData()[$item])) {
            return true;
        }
        return false;
    }

    public function listAll()
    {
        return $this->getData();
    }
}

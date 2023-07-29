<?php

namespace WHMCS\User;

class Permissions
{
    protected $permissions = [];
    public static $definitions = ["profile", "contacts", "products", "manageproducts", "productsso", "domains", "managedomains", "invoices", "quotes", "tickets", "affiliates", "emails", "orders"];
    const ALL_PERMISSIONS = "ALL";

    public function __construct($permissions = NULL)
    {
        if (!is_null($permissions)) {
            $this->add($permissions);
        }
    }

    public static function getAllPermissions()
    {
        return self::$definitions;
    }

    public static function all()
    {
        return new self(self::getAllPermissions());
    }

    public static function none()
    {
        return new self();
    }

    public static function set($permissions)
    {
        return new self($permissions);
    }

    public function add($permissions)
    {
        if (!is_array($permissions)) {
            $permissions = explode(",", $permissions);
        }
        $this->permissions = array_merge($this->permissions, $permissions);
        return $this;
    }

    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions);
    }

    public function get()
    {
        $permsToReturn = [];
        foreach (self::$definitions as $key) {
            if ($this->hasPermission($key)) {
                $permsToReturn[] = $key;
            }
        }
        return $permsToReturn;
    }
}

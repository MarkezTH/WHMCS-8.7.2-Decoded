<?php

namespace WHMCS\User;

class Admin extends AbstractUser implements UserInterface
{
    use Traits\Authenticatable;
    use Traits\User;
    protected $table = "tbladmins";
    protected $columnMap = ["roleId" => "roleid", "passwordHash" => "password", "twoFactorAuthModule" => "authmodule", "twoFactorAuthData" => "authdata", "supportDepartmentIds" => "supportdepts", "isDisabled" => "disabled", "receivesTicketNotifications" => "ticketnotifications", "loginAttempts" => "loginattempts", "homeWidgets" => "homewidgets", "hiddenWidgets" => "hidden_widgets", "widgetOrder" => "widget_order", "userPreferences" => "user_preferences"];
    public $unique = ["email"];
    protected $appends = ["fullName", "gravatarHash"];
    protected $commaSeparated = ["supportDepartmentIds", "receivesTicketNotifications", "hiddenWidgets", "widgetOrder"];
    protected $casts = ["user_preferences" => "array"];
    protected $rules = ["firstname" => "required", "lastname" => "required", "email" => "required|email", "username" => "required|min:2", "template" => "required", "language" => "required"];
    protected $hidden = ["password", "passwordhash", "authdata", "password_reset_key", "password_reset_data", "password_reset_expiry"];
    const TEMPLATE_THEME_DEFAULT = "blend";

    public function getSecondFactorModuleName()
    {
        return "authmodule";
    }

    public function getSecondFactorConfigName()
    {
        return "authdata";
    }

    public function getFullNameAttribute()
    {
        return $this->firstName . " " . $this->lastName;
    }

    public function getGravatarHashAttribute()
    {
        return md5(strtolower(trim($this->email)));
    }

    public function getUsernameAttribute()
    {
        return isset($this->attributes["username"]) ? $this->attributes["username"] : "";
    }

    public function isAllowedToAuthenticate()
    {
        return !$this->isDisabled;
    }

    public function isAllowedToMasquerade()
    {
        return $this->hasPermission(120);
    }

    public function hasPermission($permission)
    {
        if (!is_numeric($permission)) {
            $id = Admin\Permission::findId($permission);
        } else {
            $id = $permission;
        }
        if ($id) {
            if (!$rolesPerms || empty($rolesPerms[$this->roleId])) {
                $rolesPerms[$this->roleId] = \WHMCS\Database\Capsule::table("tbladminperms")->where("roleid", $this->roleId)->pluck("permid")->all();
            }
            return in_array($id, $rolesPerms[$this->roleId]);
        }
        return false;
    }

    public function getRolePermissions()
    {
        $adminPermissions = [];
        $adminPermissionsArray = Admin\Permission::all();
        $rolePermissions = \WHMCS\Database\Capsule::table("tbladminperms")->where("roleid", "=", $this->roleId)->get()->all();
        foreach ($rolePermissions as $rolePermission) {
            if (isset($adminPermissionsArray[$rolePermission->permid])) {
                $adminPermissions[] = $adminPermissionsArray[$rolePermission->permid];
            }
        }
        return $adminPermissions;
    }

    public function getModulePermissions()
    {
        $addonModulesPermissions = [];
        $setting = \WHMCS\Config\Setting::getValue("AddonModulesPerms");
        if ($setting) {
            $allModulesPermissions = safe_unserialize($setting);
            if (is_array($allModulesPermissions) && array_key_exists($this->roleId, $allModulesPermissions)) {
                $addonModulesPermissions = $allModulesPermissions[$this->roleId];
            }
        }
        return $addonModulesPermissions;
    }

    public function authenticationDevices()
    {
        return $this->hasMany("\\WHMCS\\Authentication\\Device", "user_id");
    }

    public function getTemplateThemeNameAttribute()
    {
        $templateThemeName = $this->template;
        if (!$templateThemeName) {
            $templateThemeName = static::TEMPLATE_THEME_DEFAULT;
        }
        return $templateThemeName;
    }

    public function validateUsername($username, $existingUserId = NULL)
    {
        if (strlen($username) < 2) {
            throw new \WHMCS\Exception\Validation\InvalidLength("Admin usernames must be at least 2 characters in length");
        }
        if (!ctype_alpha(substr($username, 0, 1))) {
            throw new \WHMCS\Exception\Validation\InvalidFirstCharacter("Admin usernames must begin with a letter");
        }
        if (preg_replace("/[A-Za-z0-9\\.\\_\\-\\@]/", "", $username)) {
            throw new \WHMCS\Exception\Validation\InvalidCharacters("Admin usernames may only contain letters, numbers and the special characters . _ - @");
        }
        if (!is_null($existingUserId)) {
            $existingUser = self::where("username", "=", $username)->first();
            if (!is_null($existingUser) && $existingUserId != $existingUser->id) {
                throw new \WHMCS\Exception\Validation\DuplicateValue("Admin username is already in use");
            }
        }
    }

    public function flaggedTickets()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket", "flag");
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("disabled", 0);
    }

    public static function getAuthenticatedUser()
    {
        $adminId = (int) \WHMCS\Session::get("adminid");
        return 0 < $adminId ? self::find($adminId) : NULL;
    }

    public function getSupportDepartmentIds()
    {
        $ids = [];
        foreach ($this->supportDepartmentIds as $id) {
            $id = trim($id);
            if ($id) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function setSecondFactorConfig($self, $value)
    {
        if ($this->getSecondFactorModule() === "duosecurity" && !isset($value["duo_auth_identifier"])) {
            $value["duo_auth_identifier"] = $this->baseGetAuthIdentifierName();
        }
        return $this->baseSetSecondFactorConfig($value);
    }

    public function getAuthIdentifierName()
    {
        if ($this->getSecondFactorModule() === "duosecurity") {
            $config = $this->getSecondFactorConfig();
            if (isset($config["duo_auth_identifier"])) {
                return $config["duo_auth_identifier"];
            }
            return "username";
        }
        return $this->baseGetAuthIdentifierName();
    }
}

<?php

namespace WHMCS\Support;

class Department extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblticketdepartments";
    private $settingsErrorLogged = false;
    public $timestamps = false;
    protected $columnMap = ["clientsOnly" => "clientsonly", "pipeRepliesOnly" => "piperepliesonly", "noAutoResponder" => "noautoresponder", "feedbackRequest" => "feedback_request"];

    public function scopeEnforceUserVisibilityPermissions(\Illuminate\Database\Eloquent\Builder $query)
    {
        if (!\Auth::client()) {
            return $query->where("hidden", "")->where("clientsonly", "");
        }
        return $query->where("hidden", "");
    }

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblticketdepartments.order");
        });
        self::saved(function (self $department) {
            if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
                $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "ticket_department.{id}.description", "related_id" => $department->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "text"]);
                $translation->translation = $department->getRawAttribute("description") ?: "";
                $translation->save();
                $translation = \WHMCS\Language\DynamicTranslation::firstOrNew(["related_type" => "ticket_department.{id}.name", "related_id" => $department->id, "language" => \WHMCS\Config\Setting::getValue("Language"), "input_type" => "text"]);
                $translation->translation = $department->getRawAttribute("name") ?: "";
                $translation->save();
            }
        });
        self::deleted(function (self $department) {
            if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
                \WHMCS\Language\DynamicTranslation::whereIn("related_type", ["ticket_department.{id}.description", "ticket_department.{id}.name"])->where("related_id", "=", $department->id)->delete();
            }
        });
    }

    public function getNameAttribute($name)
    {
        $translatedName = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            if (!defined("CLIENTAREA")) {
                $lang = \AdminLang::self();
            } else {
                $lang = \Lang::self();
            }
            $translatedName = $lang->trans("ticket_department." . $this->id . ".name", [], "dynamicMessages");
        }
        return strlen($translatedName) && $translatedName != "ticket_department." . $this->id . ".name" ? $translatedName : $name;
    }

    public function getDescriptionAttribute($description)
    {
        $translatedDescription = "";
        if (\WHMCS\Config\Setting::getValue("EnableTranslations")) {
            if (!defined("CLIENTAREA")) {
                $lang = \AdminLang::self();
            } else {
                $lang = \Lang::self();
            }
            $translatedDescription = $lang->trans("ticket_department." . $this->id . ".description", [], "dynamicMessages");
        }
        return strlen($translatedDescription) && $translatedDescription != "ticket_department." . $this->id . ".description" ? $translatedDescription : $description;
    }

    public function translatedNames()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "ticket_department.{id}.name")->select(["language", "translation"]);
    }

    public function translatedDescriptions()
    {
        return $this->hasMany("WHMCS\\Language\\DynamicTranslation", "related_id")->where("related_type", "=", "ticket_department.{id}.description")->select(["language", "translation"]);
    }

    public static function getDepartmentName($departmentId, $fallback = "", $language = NULL)
    {
        $name = \Lang::trans("ticket_department." . $departmentId . ".name", [], "dynamicMessages", $language);
        if ($name == "ticket_department." . $departmentId . ".name") {
            if ($fallback) {
                return $fallback;
            }
            return self::find($departmentId, ["name"])->name;
        }
        return $name;
    }

    public static function getDepartmentDescription($departmentId, $fallback = "", $language = NULL)
    {
        $description = \Lang::trans("ticket_department." . $departmentId . ".description", [], "dynamicMessages", $language);
        if ($description == "ticket_department." . $departmentId . ".description") {
            if ($fallback) {
                return $fallback;
            }
            return self::find($departmentId, ["description"])->description;
        }
        return $description;
    }

    public function tickets()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket", "did");
    }

    public function getMailAuthConfigAttribute()
    {
        $value = ["service_provider" => "", "auth_type" => "", "oauth2_client_id" => "", "oauth2_client_secret" => "", "oauth2_refresh_token" => ""];
        $rawValue = $this->getRawAttribute("mail_auth_config");
        if (!empty($rawValue)) {
            $value = json_decode($this->decrypt($this->attributes["mail_auth_config"] ?? ""), true);
        }
        if (!is_array($value) && $this->exists) {
            if (!$this->settingsErrorLogged) {
                $this->settingsErrorLogged = true;
                logActivity("Encryption hash is missing or damaged. Department POP auth settings could not be decrypted.");
            }
            $value = [];
        }
        return $value;
    }

    public function setMailAuthConfigAttribute($value)
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Mail auth configuration must be an array");
        }
        $this->attributes["mail_auth_config"] = $this->encrypt(json_encode($value));
    }

    public function getPasswordAttribute()
    {
        return $this->decrypt($this->attributes["password"] ?? "");
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes["password"] = $this->encrypt($value);
    }

    public static function setDepartmentOrder(int $deptOrder, int $direction)
    {
        $deptToMove = self::where("order", $deptOrder)->first();
        if ($direction == 1) {
            $newOrderUp = $deptOrder - 1;
            $deptToMoveDown = self::where("order", $newOrderUp)->first();
            $deptToMove->order = $newOrderUp;
            $deptToMove->save();
            logAdminActivity("Support Department Modified: '" . $deptToMove->name . "'" . " - Sort Order Increased - Support Department ID: " . $deptToMove->id);
            $deptToMoveDown->order = $deptOrder;
            $deptToMoveDown->save();
            logAdminActivity("Support Department Modified: '" . $deptToMoveDown->name . "'" . " - Sort Order Lowered - Support Department ID: " . $deptToMoveDown->id);
        }
        if ($direction == -1) {
            $newOrderDown = $deptOrder + 1;
            $deptToMoveUp = self::where("order", $newOrderDown)->first();
            $deptToMove->order = $newOrderDown;
            $deptToMove->save();
            logAdminActivity("Support Department Modified: '" . $deptToMove->name . "'" . " - Sort Order Lowered - Support Department ID: " . $deptToMove->id);
            $deptToMoveUp->order = $deptOrder;
            $deptToMoveUp->save();
            logAdminActivity("Support Department Modified: '" . $deptToMoveUp->name . "'" . " - Sort Order Increased - Support Department ID: " . $deptToMoveUp->id);
        }
    }

    public function configureAdmins(\WHMCS\User\Admin $admin, $deptAdmins)
    {
        $supportDepts = $admin->supportDepartmentIds;
        if (in_array($admin->id, $deptAdmins)) {
            if (!in_array($this->id, $supportDepts)) {
                $supportDepts[] = $this->id;
            }
        } else {
            if (in_array($this->id, $supportDepts)) {
                $supportDepts = array_diff($supportDepts, [$this->id]);
            }
        }
        return $admin->supportDepartmentIds = $supportDepts;
    }

    public function isSetUpForMailImport()
    {
        if (!empty($this->host) && !empty($this->port) && !empty($this->login)) {
            return true;
        }
        if ($this->mailAuthConfig["auth_type"] === \WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2 && !empty($this->mailAuthConfig["oauth2_client_id"]) && !empty($this->mailAuthConfig["oauth2_client_secret"]) && !empty($this->mailAuthConfig["oauth2_refresh_token"])) {
            return true;
        }
        return false;
    }
}

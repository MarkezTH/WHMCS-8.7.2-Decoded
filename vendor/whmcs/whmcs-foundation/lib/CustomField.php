<?php

namespace WHMCS;

use WHMCS\Application\Support\Facades\Lang;

class CustomField extends Model\AbstractModel
{
    protected $table = "tblcustomfields";
    protected $columnMap = ["relatedId" => "relid", "regularExpression" => "regexpr", "showOnOrderForm" => "showorder", "showOnInvoice" => "showinvoice"];
    protected $commaSeparated = ["fieldOptions"];
    protected $fillable = ["type", "relid", "fieldName", "fieldType"];
    const TYPE_ADDON = "addon";
    const TYPE_CLIENT = "client";
    const TYPE_PRODUCT = "product";
    const TYPE_SUPPORT = "support";

    public static function boot()
    {
        parent::boot();
        CustomField::created(function (CustomField $customField) {
            if (Config\Setting::getValue("EnableTranslations")) {
                Language\DynamicTranslation::whereIn("related_type", ["custom_field.{id}.name", "custom_field.{id}.description"])->where("related_id", "=", 0)->update(["related_id" => $customField->id]);
            }
        });
        CustomField::saved(function (CustomField $customField) {
            if (Config\Setting::getValue("EnableTranslations")) {
                $translation = Language\DynamicTranslation::firstOrNew(["related_type" => "custom_field.{id}.name", "related_id" => $customField->id, "language" => Config\Setting::getValue("Language"), "input_type" => "text"]);
                $translation->translation = $customField->getRawAttribute("fieldName") ?: $customField->getRawAttribute("fieldname") ?: "";
                $translation->save();
                $translation = Language\DynamicTranslation::firstOrNew(["related_type" => "custom_field.{id}.description", "related_id" => $customField->id, "language" => Config\Setting::getValue("Language"), "input_type" => "text"]);
                $translation->translation = $customField->getRawAttribute("description") ?: "";
                $translation->save();
            }
        });
        CustomField::deleted(function (CustomField $customField) {
            if (Config\Setting::getValue("EnableTranslations")) {
                Language\DynamicTranslation::whereIn("related_type", ["custom_field.{id}.name", "custom_field.{id}.description"])->where("related_id", "=", $customField->id)->delete();
            }
            CustomField\CustomFieldValue::where("fieldid", "=", $customField->id)->delete();
        });
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblcustomfields.sortorder")->orderBy("tblcustomfields.id");
        });
    }

    public function scopeClientFields(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("type", "=", "client");
    }

    public function scopeProductFields(\Illuminate\Database\Eloquent\Builder $query, $productId)
    {
        return $query->where("type", "=", "product")->where("relid", "=", $productId);
    }

    public function scopeSupportFields(\Illuminate\Database\Eloquent\Builder $query, $departmentId)
    {
        return $query->where("type", "=", "support")->where("relid", "=", $departmentId);
    }

    public function scopeAddonFields(\Illuminate\Database\Eloquent\Builder $query, $addonId)
    {
        return $query->where("type", "=", "addon")->where("relid", "=", $addonId);
    }

    public function product()
    {
        return $this->hasOne("WHMCS\\Product\\Product", "id", "relid");
    }

    public function addon()
    {
        return $this->hasOne("WHMCS\\Product\\Addon", "id", "relid");
    }

    public function getFieldNameAttribute($fieldName)
    {
        $translatedFieldName = $this->getFieldNameTranslation(NULL);
        return coalesce($translatedFieldName, $fieldName);
    }

    public function getDescriptionAttribute($description)
    {
        $translatedDescription = "";
        if (Config\Setting::getValue("EnableTranslations")) {
            $translatedDescription = Lang::trans("custom_field." . $this->id . ".description", [], "dynamicMessages");
        }
        return strlen($translatedDescription) && $translatedDescription != "custom_field." . $this->id . ".description" ? $translatedDescription : $description;
    }

    public function customFieldValues()
    {
        return $this->hasMany("WHMCS\\CustomField\\CustomFieldValue", "fieldid");
    }

    public static function getFieldName($fieldId, $fallback = "", $language = NULL)
    {
        $customField = self::find($fieldId);
        if (is_null($customField)) {
            return $fallback;
        }
        return ecoalesce($customField->getFieldNameTranslation($language), $customField->getFriendlyDisplayFieldName(), $fallback);
    }

    public function getFriendlyDisplayFieldName()
    {
        if ($this->hasFriendlyDisplayFieldName()) {
            $fieldName = explode("|", $this->fieldName);
            return $fieldName[1];
        }
        return NULL;
    }

    public function hasFriendlyDisplayFieldName()
    {
        return strpos($this->fieldName, "|") !== false;
    }

    public function getFieldNameTranslation($language, $assumeEnabled = false)
    {
        $translation = NULL;
        if ($assumeEnabled || Config\Setting::getValue("EnableTranslations")) {
            $translation = Lang::trans("custom_field." . $this->id . ".name", [], "dynamicMessages", $language);
        }
        if (!is_null($translation) && $translation != "custom_field." . $this->id . ".name" && $translation != $this->getRawAttribute("fieldname")) {
            return $translation;
        }
        return NULL;
    }

    public static function getDescription($fieldId, $fallback = "", $language = NULL)
    {
        $description = Lang::trans("custom_field." . $fieldId . ".description", [], "dynamicMessages", $language);
        if ($description == "custom_field." . $fieldId . ".description") {
            if ($fallback) {
                return $fallback;
            }
            return CustomField::find($fieldId, ["description"])->description;
        }
        return $description;
    }

    public function scopeRequired($Builder, $query)
    {
        return $query->where("required", "on");
    }

    public function scopeOnOrderShow($Builder, $query)
    {
        return $query->where("showorder", "on");
    }

    public function scopeOnOrderHide($Builder, $query)
    {
        return $query->where("showorder", "");
    }

    public static function commonQueryBuilder($type, $relid = 0, $onOrderOnly = false)
    {
        if (empty($relid)) {
            $relid = 0;
        }
        $query = CustomField::query()->where("type", $type);
        $query->where("relid", $relid);
        if ($onOrderOnly) {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $query) {
                return $query->onOrderShow()->orWhere->required();
            });
        }
        return $query;
    }
}

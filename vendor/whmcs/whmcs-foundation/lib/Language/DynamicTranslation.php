<?php

namespace WHMCS\Language;

class DynamicTranslation extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbldynamic_translations";
    protected $primaryKey = "id";
    protected $fillable = ["related_type", "related_id", "language", "input_type"];

    public static function boot()
    {
        parent::boot();
        DynamicTranslation::saved(function (DynamicTranslation $dynamicTranslation) {
            $dynamicTranslation->reloadDynamicTranslation();
        });
        DynamicTranslation::deleted(function (DynamicTranslation $dynamicTranslation) {
            $dynamicTranslation->reloadDynamicTranslation();
        });
    }

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->enum("related_type", ["configurable_option.{id}.name", "configurable_option_option.{id}.name", "custom_field.{id}.description", "custom_field.{id}.name", "download.{id}.description", "download.{id}.title", "product.{id}.description", "product.{id}.name", "product.{id}.tagline", "product.{id}.short_description", "product_addon.{id}.description", "product_addon.{id}.name", "product_bundle.{id}.description", "product_bundle.{id}.name", "product_group.{id}.headline", "product_group.{id}.name", "product_group.{id}.tagline", "product_group_feature.{id}.feature", "ticket_department.{id}.description", "ticket_department.{id}.name"]);
                $table->integer("related_id", false, true);
                $table->string("language", "16");
                $table->text("translation");
                $table->enum("input_type", ["text", "textarea"]);
                $table->timestamps();
            });
        }
    }

    public function relatedItems()
    {
        switch ($this->relatedType) {
            case "configurable_option.{id}.name":
                return \WHMCS\Database\Capsule::table("tblproductconfigoptions")->find($this->relatedId);
                break;
            case "configurable_option_option.{id}.name":
                return \WHMCS\Database\Capsule::table("tblproductconfigoptionssub")->find($this->relatedId);
                break;
            case "custom_field.{id}.description":
            case "custom_field.{id}.name":
                return \WHMCS\CustomField::find($this->relatedId);
                break;
            case "download.{id}.description":
            case "download.{id}.title":
                return \WHMCS\Download\Download::find($this->relatedId);
                break;
            case "product.{id}.description":
            case "product.{id}.name":
            case "product.{id}.tagline":
            case "product.{id}.short_description":
                return \WHMCS\Product\Product::find($this->relatedId);
                break;
            case "product_addon.{id}.description":
            case "product_addon.{id}.name":
                return \WHMCS\Product\Addon::find($this->relatedId);
                break;
            case "product_bundle.{id}.description":
            case "product_bundle.{id}.name":
                return \WHMCS\Database\Capsule::table("tblbundles")->find($this->relatedId);
                break;
            case "product_group.{id}.headline":
            case "product_group.{id}.name":
            case "product_group.{id}.tagline":
                return \WHMCS\Product\Group::find($this->relatedId);
                break;
            case "product_group_feature.{id}.feature":
                return \WHMCS\Product\Group\Feature::find($this->relatedId);
                break;
            case "ticket_department.{id}.description":
            case "ticket_department.{id}.name":
                return \WHMCS\Support\Department::find($this->relatedId);
                break;
        }
    }

    public function reloadDynamicTranslation()
    {
        if (class_exists("\\Lang")) {
            \Lang::self()->addResource("dynamic", NULL, (string) $this->language, "dynamicMessages");
        }
        if (class_exists("\\AdminLang")) {
            \AdminLang::self()->addResource("dynamic", NULL, (string) $this->language, "dynamicMessages");
        }
        return $this;
    }

    public function getInputField()
    {
        switch ($this->inputType) {
            case "text":
            case "textarea":
                $translation = \WHMCS\Input\Sanitize::encode($this->translation);
                $return = "<textarea name=\"" . $this->language . "\" class=\"form-control\">" . $translation . "</textarea>";
                break;
            default:
                $return = "<input type=\"text\" name=\"" . $this->language . "\" class=\"form-control input-sm\" value=\"" . $this->translation . "\" />";
                return $return;
        }
    }

    public static function getInputType($relatedType)
    {
        switch ($relatedType) {
            case "download.{id}.description":
            case "product.{id}.description":
            case "product.{id}.short_description":
            case "product_addon.{id}.description":
            case "product_bundle.{id}.description":
                return "textarea";
                break;
            default:
                return "text";
        }
    }

    public static function saveNewTranslations($relatedId, $relatedTypes = [])
    {
        if ($relatedTypes) {
            DynamicTranslation::whereIn("related_type", $relatedTypes)->where("related_id", "=", 0)->update(["related_id" => $relatedId]);
        }
    }
}

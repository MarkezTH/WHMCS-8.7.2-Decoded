<?php

namespace WHMCS\Module\Gateway\Protx;

class Protx extends \WHMCS\Model\AbstractModel
{
    protected $moduleName = "protx";
    protected $table = "mod_protx_data";

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->integer("pay_method_id")->default(0);
                $table->binary("gateway_data")->default("");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
                $table->index("pay_method_id");
            });
        }
    }

    public function getGatewayDataAttribute()
    {
        $value = NULL;
        $rawValue = $this->attributes["gateway_data"];
        if ($this->isAesDecryptable($rawValue)) {
            try {
                $value = json_decode(trim($this->aesDecryptValue($rawValue, $this->getEncryptionKey()), chr(0)), true);
            } catch (\Throwable $e) {
            }
        }
        return $value;
    }

    public function setGatewayDataAttribute($value)
    {
        $value = $this->aesEncryptValue(json_encode($value), $this->getEncryptionKey());
        $this->attributes["gateway_data"] = $value;
    }

    private function getEncryptionKey()
    {
        if (!(isset($this->moduleName) && isset($this->payMethodId))) {
            throw new \WHMCS\Exception("The module name or pay method ID is not set. The system cannot process encryption or decryption.");
        }
        return hash("sha256", $this->moduleName . ":" . $this->payMethodId . ":" . \DI::make("config")->cc_encryption_hash);
    }
}

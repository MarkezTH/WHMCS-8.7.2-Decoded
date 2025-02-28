<?php

namespace WHMCS\Domains\Pricing;

class Premium extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbldomainpricing_premium";

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("ordered", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tbldomainpricing_premium.to_amount");
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
                $table->decimal("to_amount", 10, 2)->default(0);
                $table->decimal("markup", 8, 5)->default(0);
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
                $table->unique("to_amount");
            });
            $defaultInserts = [["toAmount" => 200, "markup" => 20], ["toAmount" => 500, "markup" => 25], ["toAmount" => 1000, "markup" => 30], ["toAmount" => -1, "markup" => 20]];
            foreach ($defaultInserts as $defaultInsert) {
                $me = new self();
                $me->toAmount = $defaultInsert["toAmount"];
                $me->markup = $defaultInsert["markup"];
                $me->save();
            }
        }
    }

    public static function markupForCost($amount)
    {
        $cost = self::where("to_amount", ">", $amount)->first();
        if (!$cost) {
            return self::where("to_amount", "=", -1)->value("markup");
        }
        return $cost->markup;
    }
}

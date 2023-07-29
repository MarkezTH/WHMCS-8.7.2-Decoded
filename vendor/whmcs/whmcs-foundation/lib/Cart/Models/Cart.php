<?php

namespace WHMCS\Cart\Models;

class Cart extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblcarts";
    protected $columnMap = [];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->unsignedInteger("user_id")->nullable();
                $table->char("tag", 64);
                $table->text("data");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
                $table->unique("tag");
            });
        }
    }

    public static function boot()
    {
        parent::boot();
        static::saving(function (Cart $cart) {
            if (is_null($cart->tag)) {
                do {
                    $maxTries = 100;
                    $cart->tag = \Illuminate\Support\Str::random(16);
                } while (!(static::byTag($cart->tag)->exists() && 0 < $maxTries--));
            }
        });
    }

    public function getDataAttribute()
    {
        if (isset($this->attributes["data"])) {
            $decoded = json_decode($this->attributes["data"], true);
            if ($decoded) {
                return $decoded;
            }
        }
        return [];
    }

    public function setDataAttribute($value)
    {
        $this->attributes["data"] = json_encode($value);
    }

    public function user()
    {
        return $this->belongsTo("WHMCS\\User\\User", "user_id");
    }

    public function features()
    {
        return $this->hasMany("WHMCS\\Product\\Group\\Feature", "product_group_id")->orderBy("order");
    }

    public function scopeByTag($query, $tag)
    {
        return $query->where("tag", $tag);
    }

    public function scopeByUser($Builder, $query, \WHMCS\User\User $user)
    {
        return $query->where("user_id", $user->id);
    }

    public function exportToSession()
    {
        \WHMCS\Session::set("cart", $this->data);
    }

    public function importFromSession()
    {
        $this->data = (new \WHMCS\OrderForm())->getCartData();
    }
}

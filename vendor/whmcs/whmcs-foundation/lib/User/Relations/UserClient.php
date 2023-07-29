<?php

namespace WHMCS\User\Relations;

class UserClient extends \WHMCS\Model\Relations\AbstractPivot
{
    protected $table = "tblusers_clients";
    protected $dates = ["last_login"];

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\User\\Observers\\UserClientObserver");
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblusers_clients.owner")->orderBy("tblusers_clients.id");
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
                $table->unsignedInteger("auth_user_id")->default(0);
                $table->unsignedInteger("client_id")->default(0);
                $table->unsignedInteger("invite_id")->default(0);
                $table->unsignedTinyInteger("owner")->default(0);
                $table->text("permissions")->nullable();
                $table->timestamp("last_login")->nullable();
                $table->timestamps();
                $table->unique(["auth_user_id", "client_id"], "user_id_client_id");
            });
        }
    }

    public function user()
    {
        return $this->hasOne("WHMCS\\User\\User", "id", "auth_user_id");
    }

    public function client()
    {
        return $this->hasOne("WHMCS\\User\\Client", "id");
    }

    public function updateLastLogin()
    {
        $this->last_login = \WHMCS\Carbon::now();
        return $this;
    }

    public function hasLastLogin()
    {
        return !is_null($this->getRawOriginal("last_login"));
    }

    public function getLastLogin()
    {
        return \WHMCS\Carbon::parse($this->last_login);
    }

    public function getPermissions()
    {
        if ($this->owner) {
            return \WHMCS\User\Permissions::all();
        }
        if ($this->permissions) {
            return \WHMCS\User\Permissions::set($this->permissions);
        }
        return \WHMCS\User\Permissions::none();
    }

    public function setPermissions(\WHMCS\User\Permissions $permissions)
    {
        $this->permissions = implode(",", $permissions->get());
        return $this;
    }
}

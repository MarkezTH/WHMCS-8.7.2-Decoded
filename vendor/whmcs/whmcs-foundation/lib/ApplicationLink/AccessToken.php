<?php

namespace WHMCS\ApplicationLink;

use WHMCS\User\Client\Contact;

class AccessToken extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbloauthserver_access_tokens";
    protected $primaryKey = "id";
    protected $scopePivotTable = "tbloauthserver_access_token_scopes";
    protected $scopePivotId = "access_token_id";
    protected $commaSeparated = ["grantTypes"];
    protected $dates = ["expires"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \Illuminate\Database\Capsule\Manager::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
            $schemaBuilder->dropIfExists($this->scopePivotTable);
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("access_token", 80)->unique();
                $table->string("client_id", 80)->default("");
                $table->string("user_id", 255)->default("");
                $table->string("redirect_uri", 2000)->default("");
                $table->timestamp("expires")->default("0000-00-00 00:00:00");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
            });
        }
        if (!$schemaBuilder->hasTable($this->scopePivotTable)) {
            $self = $this;
            $schemaBuilder->create($this->scopePivotTable, function ($table) use($self) {
                $table->integer($self->scopePivotId, false, true)->default(0);
                $table->integer("scope_id", false, true)->default(0);
                $table->index([$self->scopePivotId, "scope_id"], $self->scopePivotTable . "_scope_id_index");
            });
        }
        $scope = new Scope();
        $scope->createTable();
    }

    public function scopes()
    {
        return $this->belongsToMany("\\WHMCS\\ApplicationLink\\Scope", $this->scopePivotTable, $this->scopePivotId, "scope_id");
    }

    protected function getFormattedScopes()
    {
        $scopes = $this->scopes()->get();
        $spaceDelimitedScopes = "";
        foreach ($scopes as $scope) {
            $spaceDelimitedScopes .= " " . $scope->scope;
        }
        return trim($spaceDelimitedScopes);
    }

    public function getScopeAttribute()
    {
        return $this->getFormattedScopes();
    }

    public function getUserAttribute()
    {
        $uuid = $this->getRawAttribute("user_id");
        $delimiter = strpos($uuid, ":");
        $model = NULL;
        if ($delimiter !== false) {
            $id = substr($uuid, $delimiter + 1);
            $model = Contact::find($id);
        } else {
            $model = \WHMCS\User\Client::findUuid($uuid);
        }
        return $model;
    }

    public function isApiSsoToken()
    {
        return (bool) preg_match("/^api\\.[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\$/", $this->getRawAttribute("client_id"));
    }

    public function getUser()
    {
        $user = NULL;
        $clientValue = $this->getRawAttribute("user_id");
        if ($this->isApiSsoToken()) {
            $ids = explode(":", $clientValue, 2);
            if (is_numeric($ids[0])) {
                $user = \WHMCS\User\User::find($ids[0]);
            }
        } else {
            if (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\$/", $clientValue)) {
                $userClient = $this->getClient();
                if ($userClient) {
                    $user = $userClient->owner();
                }
            }
        }
        return $user;
    }

    public function getClient()
    {
        $client = NULL;
        $clientValue = $this->getRawAttribute("user_id");
        if ($this->isApiSsoToken()) {
            $ids = explode(":", $clientValue, 2);
            if (isset($ids[1]) && is_numeric($ids[1])) {
                $client = \WHMCS\User\Client::find($ids[1]);
            }
        } else {
            if (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\$/", $clientValue)) {
                $client = \WHMCS\User\Client::findUuid($clientValue);
            }
        }
        return $client;
    }

    public function client()
    {
        return $this->belongsTo("\\WHMCS\\ApplicationLink\\Client", "client_id", "identifier");
    }

    public function toArray()
    {
        $data = parent::toArray();
        $data["expires"] = $this->expires->timestamp;
        $data["scope"] = $this->scope;
        return $data;
    }

    public static function deleteExpired(\WHMCS\Carbon $datetime = NULL)
    {
        if (!$datetime) {
            $datetime = \WHMCS\Carbon::now();
        }
        $tokens = self::where("expires", "<", $datetime->toDateTimeString())->get();
        foreach ($tokens as $token) {
            $token->delete();
        }
    }
}

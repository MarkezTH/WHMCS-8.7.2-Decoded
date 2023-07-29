<?php

namespace WHMCS\User\User;

class UserInvite extends \WHMCS\Model\AbstractModel
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $table = "tbluser_invites";
    protected $columnMap = ["clientId" => "client_id", "invitedBy" => "invited_by", "invitedByAdmin" => "invited_by_admin", "acceptedAt" => "accepted_at"];
    protected $casts = ["invited_by_admin" => "boolean"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("token", 100)->default("");
                $table->string("email", 255)->default("");
                $table->unsignedInteger("client_id")->default(0);
                $table->unsignedInteger("invited_by")->default(0);
                $table->tinyInteger("invited_by_admin")->default(0);
                $table->text("permissions")->nullable();
                $table->timestamp("accepted_at")->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "client_id");
    }

    public function sender()
    {
        if ($this->invited_by_admin) {
            return $this->belongsTo("WHMCS\\User\\Admin", "invited_by");
        }
        return $this->belongsTo("WHMCS\\User\\User", "invited_by");
    }

    public function scopePending($query)
    {
        return $query->whereNull("accepted_at")->whereDate("created_at", ">", \WHMCS\Carbon::now()->subDays(7));
    }

    public function scopeOfAccount($query, int $accountId)
    {
        return $query->where("client_id", $accountId);
    }

    public function scopeToken($query, $token)
    {
        return $query->where("token", $token);
    }

    public static function new($email, \WHMCS\User\Permissions $permissions, $clientId)
    {
        $email = trim($email);
        if (empty($email)) {
            throw new \WHMCS\Exception\Validation\Required(\Lang::trans("validation.required", [":attribute" => "email"]));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \WHMCS\Exception\Validation\Required(\Lang::trans("validation.email", [":attribute" => "email"]));
        }
        $client = \WHMCS\User\Client::find($clientId);
        if (!$client) {
            throw new \WHMCS\Exception\Validation\Required(\Lang::trans("validation.required", [":attribute" => "client"]));
        }
        $adminUser = \WHMCS\User\Admin::getAuthenticatedUser();
        $invite = new self();
        $invite->email = $email;
        $invite->clientId = $client->id;
        $invite->invitedBy = $adminUser ? $adminUser->id : \Auth::user()->id;
        $invite->invitedByAdmin = !empty($adminUser);
        $invite->permissions = implode(",", $permissions->get());
        $invite->save();
        $invite->refreshToken()->save();
        try {
            $invite->notify();
        } catch (\Throwable $e) {
            logActivity("User invite email could not be sent: " . $e->getMessage());
        }
        return $invite;
    }

    public function isPending()
    {
        return is_null($this->getRawAttribute("deleted_at")) && is_null($this->getRawAttribute("accepted_at"));
    }

    public function isExpired()
    {
        return $this->createdAt->lt(\WHMCS\Carbon::now()->subDays(7));
    }

    public function refreshToken()
    {
        $token = hash("sha256", $this->getKey() . time() . mt_rand(100000, 999999) . $this->email . $this->clientId . $this->invitedBy);
        $this->token = $token;
        return $this;
    }

    public function getClientName()
    {
        $client = $this->client;
        if ($client->companyName) {
            return $client->companyName;
        }
        return $client->fullName;
    }

    public function getSenderName()
    {
        return $this->sender->fullName;
    }

    public function getUrl()
    {
        return fqdnRoutePath("invite-redeem", $this->token);
    }

    public function notify()
    {
        $emailer = \WHMCS\Mail\Emailer::factoryByTemplate("Account Access Invitation", $this->getKey());
        return $emailer->send();
    }

    public function accept(\WHMCS\User\User $user)
    {
        $this->client->users()->attach($user->id, ["invite_id" => $this->id, "permissions" => $this->permissions]);
        $this->acceptedAt = \WHMCS\Carbon::now();
        $this->save();
        if ($this->email === $user->email && $user->needsToCompleteEmailVerification()) {
            $user->setEmailVerificationCompleted();
        }
        return $this;
    }

    public function cancel()
    {
        $this->delete();
        return $this;
    }
}

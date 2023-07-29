<?php

namespace WHMCS\User;

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;

class User extends \WHMCS\Model\AbstractModel
{
    use Traits\Authenticatable;
    use Traits\EmailVerification;
    use Traits\PasswordResets;
    use Traits\SecurityQuestions;
    use Traits\User;
    public $unique = ["email"];
    protected $table = "tblusers";
    protected $primaryKey = "id";
    protected $columnMap = ["firstName" => "first_name", "lastName" => "last_name", "secondFactor" => "second_factor", "secondFactorConfig" => "second_factor_config", "rememberToken" => "remember_token", "resetToken" => "reset_token", "securityQuestionId" => "security_question_id", "securityQuestionAnswer" => "security_question_answer", "lastIp" => "last_ip", "lastHostname" => "last_hostname", "lastLogin" => "last_login", "resetTokenExpiry" => "reset_token_expiry", "emailVerificationToken" => "email_verification_token", "emailVerificationTokenExpiry" => "email_verification_token_expiry", "emailVerifiedAt" => "email_verified_at"];
    protected $dates = ["last_login", "reset_token_expiry", "email_verification_token_expiry", "email_verified_at"];
    protected $hidden = ["password", "remember_token", "reset_token"];
    protected $appends = ["fullName"];
    const TWOFA_ATTEMPTS = 5;
    const TWOFA_BACKUP_ATTEMPTS = 3;

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\User\\Observers\\UserObserver");
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblusers.id");
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
                $table->string("first_name", 255)->default("");
                $table->string("last_name", 255)->default("");
                $table->string("email", 255)->unique()->default("");
                $table->string("password", 255)->default("");
                $table->string("language", 32)->default("");
                $table->string("second_factor", 255)->default("");
                $table->text("second_factor_config")->nullable();
                $table->string("remember_token", 100)->default("");
                $table->string("reset_token", 100)->default("");
                $table->unsignedInteger("security_question_id")->default(0);
                $table->string("security_question_answer", 255)->default("");
                $table->string("last_ip", 64)->default("");
                $table->string("last_hostname", 255)->default("");
                $table->timestamp("last_login")->nullable();
                $table->string("email_verification_token", 100)->default("");
                $table->timestamp("email_verification_token_expiry")->nullable();
                $table->timestamp("email_verified_at")->nullable();
                $table->timestamp("reset_token_expiry")->nullable();
                $table->timestamps();
            });
        }
    }

    public static function createUser($firstName, $lastName, $email, $password = NULL, $language = false, $skipEmailVerification = false, $skipNameValidation = true)
    {
        $optionalFields = [];
        $setting = \WHMCS\Config\Setting::getValue("ClientsProfileOptionalFields");
        if (is_string($setting) && 0 < strlen($setting)) {
            $optionalFields = explode(",", $setting);
        }
        unset($setting);
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);
        $password = trim($password);
        if ($skipNameValidation !== true) {
            if (!in_array("firstname", $optionalFields) && empty($firstName)) {
                throw new \WHMCS\Exception\Validation\Required(Lang::trans("validation.required", [":attribute" => Lang::trans("clientareafirstname")]));
            }
            if (!in_array("lastname", $optionalFields) && empty($lastName)) {
                throw new \WHMCS\Exception\Validation\Required(Lang::trans("validation.required", [":attribute" => Lang::trans("clientarealastname")]));
            }
        }
        if (empty($email)) {
            throw new \WHMCS\Exception\Validation\Required(Lang::trans("validation.required", [":attribute" => Lang::trans("loginemail")]));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \WHMCS\Exception\Validation\Required(Lang::trans("validation.email", [":attribute" => Lang::trans("loginemail")]));
        }
        if (self::where("email", $email)->exists()) {
            throw new \WHMCS\Exception\User\EmailAlreadyExists();
        }
        if (empty($password)) {
            throw new \WHMCS\Exception\Validation\Required(Lang::trans("validation.required", [":attribute" => Lang::trans("loginpassword")]));
        }
        $user = new self();
        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->email = $email;
        $user->password = (new \WHMCS\Security\Hash\Password())->hash($password);
        $user->language = (string) $language;
        $user->save();
        run_hook("UserAdd", ["user_id" => $user->id, "firstname" => $firstName, "lastname" => $lastName, "email" => $email, "password" => $password, "language" => (string) $language]);
        if ($user->isEmailVerificationEnabled() && !$skipEmailVerification) {
            $user->sendEmailVerification();
        }
        return $user;
    }

    public function clients()
    {
        return $this->belongsToMany("WHMCS\\User\\Client", "tblusers_clients", "auth_user_id", "client_id")->using("WHMCS\\User\\Relations\\UserClient")->withTimestamps()->withPivot(["owner", "permissions"]);
    }

    public function clientsRelationPivot()
    {
        return $this->hasMany("WHMCS\\User\\Relations\\UserClient", "auth_user_id");
    }

    public function remoteAccountLinks()
    {
        return $this->hasMany("WHMCS\\Authentication\\Remote\\AccountLink", "user_id");
    }

    public function scopeUsername($query, $username)
    {
        return $query->where("email", $username);
    }

    public function runPostLoginEvents()
    {
        if ($this->language) {
            \WHMCS\Language\ClientLanguage::saveToSession($this->language);
        }
        $remoteAuth = \DI::make("remoteAuth");
        $remoteAuth->linkRemoteAccounts();
        if (function_exists("run_hook")) {
            run_hook("UserLogin", ["user" => $this]);
        }
        if ($this->resetToken) {
            $this->resetToken = "";
            $this->resetTokenExpiry = NULL;
            $this->save();
        }
    }

    public function runPostLogoutEvents()
    {
        run_hook("UserLogout", ["user" => $this]);
    }

    public function verifyPassword($inputPassword)
    {
        try {
            return (new \WHMCS\Security\Hash\Password())->verify($inputPassword, $this->password);
        } catch (\Exception $e) {
        }
        return false;
    }

    public function updatePassword($inputPassword)
    {
        $this->password = (new \WHMCS\Security\Hash\Password())->hash($inputPassword);
        $this->save();
        run_hook("UserChangePassword", ["userid" => $this->id, "password" => $inputPassword]);
    }

    public function getNumberOfClients()
    {
        return $this->clients()->count();
    }

    public function getClientIds()
    {
        return $this->clients()->pluck("tblclients.id")->toArray();
    }

    public function getClientsByPermission($permission)
    {
        $clientsWithPermission = [];
        foreach ($this->clients as $client) {
            if ($client->pivot->getPermissions()->hasPermission($permission)) {
                $clientsWithPermission[] = $client;
            }
        }
        return $clientsWithPermission;
    }

    public function hasAccessToClient(Client $client)
    {
        return in_array($client->id, $this->getClientIds());
    }

    public function getClient($clientId)
    {
        $client = $this->clients()->find($clientId);
        if (is_null($client)) {
            throw new \WHMCS\Exception\Authentication\InvalidClientRequested();
        }
        return $client;
    }

    public function ownedClients()
    {
        return $this->clients()->where("owner", 1)->get();
    }

    public function isOwner(Client $client)
    {
        return $this->id === $client->owner()->id;
    }

    public function hasTwoFactorAuthEnabled()
    {
        return !empty($this->secondFactor);
    }

    public function sessionToken($SessionToken)
    {
        return \WHMCS\Authentication\SessionToken::factoryFromUser($this);
    }

    public function cookieToken($CookieToken)
    {
        return \WHMCS\Authentication\CookieToken::factoryFromUser($this);
    }

    public function updateLastLogin()
    {
        $this->lastLogin = \WHMCS\Carbon::now();
        $this->lastIp = $this->currentIp();
        $this->lastHostname = $this->currentHostname();
        return $this;
    }

    public function hasLastLogin()
    {
        return !is_null($this->getRawAttribute("last_login"));
    }

    public function newRememberToken()
    {
        $this->rememberToken = (new \WHMCS\Utility\Random())->string(10, 10, 5, 5);
        $this->save();
        return $this->rememberToken;
    }

    public function changeEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \WHMCS\Exception\Validation\InvalidValue();
        }
        if (self::where("email", $email)->where("id", "!=", $this->id)->exists()) {
            throw new \WHMCS\Exception\User\EmailAlreadyExists();
        }
        $oldUserDetails = $this->getDetails();
        $this->email = $email;
        $this->invalidateEmailVerification();
        $this->save();
        run_hook("UserEdit", array_merge($this->getDetails(), ["olddata" => $oldUserDetails]));
        if (Auth::user() && Auth::user()->id === $this->id) {
            Auth::setSessionToken();
        }
        if ($this->isEmailVerificationEnabled()) {
            $this->sendEmailVerification();
        }
        return $this;
    }

    public function createClient($user, $firstname, $lastname, $companyName, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $sendEmail = true, $additionalData = [], $uuid = "", $isAdmin = false, $marketingOptIn = NULL, $clientIp = "", $language = "")
    {
        if (!function_exists("addClient")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $client = addClient($user, $firstname, $lastname, $companyName, $email, $address1, $address2, $city, $state, $postcode, $country, $phonenumber, $sendEmail, $additionalData, $uuid, $isAdmin, $marketingOptIn, $clientIp, $language);
        $authdUser = Auth::user();
        if (!\App::isApiRequest() && !defined("ADMINAREA") && $authdUser && $authdUser->id === $this->id) {
            Auth::setClientId($client->id);
            $client = Auth::client();
        } else {
            $client = Client::find($client->id);
        }
        return $client;
    }

    public function getDetails()
    {
        $details = ["user_id" => $this->id, "firstname" => $this->first_name, "lastname" => $this->last_name, "email" => $this->email, "language" => $this->language];
        return $details;
    }

    public function tickets()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket", "requestor_id");
    }

    public function replies()
    {
        return $this->hasMany("WHMCS\\Support\\Ticket\\Reply", "requestor_id");
    }

    public function orders()
    {
        return $this->hasMany("WHMCS\\Order\\Order", "requestor_id");
    }

    public function validation()
    {
        return $this->hasOne("WHMCS\\User\\User\\UserValidation", "requestor_id");
    }

    public function isValidationPending()
    {
        $userValidation = \DI::make("userValidation");
        if (!$userValidation->isEnabled()) {
            return false;
        }
        return $this->validation && !$this->validation->submittedAt;
    }
}

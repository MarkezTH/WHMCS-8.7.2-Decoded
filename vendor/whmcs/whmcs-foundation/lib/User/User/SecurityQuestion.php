<?php

namespace WHMCS\User\User;

class SecurityQuestion extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbladminsecurityquestions";

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\User\\Observers\\SecurityQuestionObserver");
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tbladminsecurityquestions.id");
        });
    }

    public function setQuestionAttribute($question)
    {
        $this->attributes["question"] = encrypt($question);
    }

    public function getQuestionAttribute($question)
    {
        return decrypt($question);
    }

    public function users()
    {
        return $this->hasMany("WHMCS\\User\\User");
    }
}

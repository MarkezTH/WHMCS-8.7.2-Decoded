<?php

namespace WHMCS\Announcement;

class Announcement extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblannouncements";
    protected $columnMap = ["publishDate" => "date", "isPublished" => "published"];
    protected $dates = ["publishDate"];
    protected $booleans = ["isPublished"];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope("order", function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->orderBy("tblannouncements.date")->orderBy("tblannouncements.id");
        });
    }

    public function parent()
    {
        return $this->hasOne("WHMCS\\Announcement\\Announcement", "id", "parentid");
    }

    public function translations()
    {
        return $this->hasMany("WHMCS\\Announcement\\Announcement", "parentid", "id");
    }

    public static function getUniqueMonthsWithAnnouncements($count = 10)
    {
        $months = collect();
        $announcement = new self();
        $rawDates = \WHMCS\Database\Capsule::table($announcement->getTable())->where("published", "=", 1)->groupBy(\WHMCS\Database\Capsule::connection()->raw("date_format(date, \"%b %Y\")"))->orderBy("date", "desc")->limit($count)->get(["date"]);
        foreach ($rawDates as $date) {
            $dateTime = \WHMCS\Carbon::createFromFormat("Y-m-d H:i:s", $date->date);
            $months[] = $dateTime->startOfMonth();
        }
        return $months;
    }

    public function scopeTranslationsOf($query, $id = "", $language = "")
    {
        if ($id) {
            $query = $query->where("parentid", "=", $id);
        }
        if ($language) {
            $query = $query->where("language", "=", $language);
        }
        return $query;
    }

    public function bestTranslation($language = "")
    {
        if (!$language) {
            $language = \WHMCS\Session::get("Language");
        }
        if (!$language) {
            $language = \WHMCS\Config\Setting::getValue("Language");
        }
        if (!isset($cache[$this->id][$language])) {
            $translation = $this->scopeTranslationsOf($this->newQuery(), $this->id, $language)->first();
            if ($translation) {
                $translation->publishDate = $this->publishDate;
                $cache[$this->id][$language] = $translation;
            } else {
                $cache[$this->id][$language] = $this;
            }
        }
        return $cache[$this->id][$language];
    }

    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query = $query->where("published", "=", "1");
        return $query;
    }
}

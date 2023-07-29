<?php

namespace WHMCS\Product;

trait MarketConnectTrait
{
    protected $field = "configoption1";

    public function scopeMarketConnect($Builder, $query)
    {
        return $query->where($this->moduleField, "marketconnect");
    }

    public function scopeSsl($query)
    {
        $query = $this->scopeMarketConnect($query);
        if ($this instanceof Product) {
            $query->where(function ($query) {
                $query->where($this->field, "like", "rapidssl\\_%")->orWhere($this->field, "like", "geotrust\\_%")->orWhere($this->field, "like", "symantec\\_%")->orWhere($this->field, "like", "digicert\\_%");
            });
        } else {
            if ($this instanceof Addon) {
                $this->addonCheck($query, ["rapidssl\\_%", "geotrust\\_%", "symantec\\_%", "digicert\\_%"]);
            }
        }
    }

    private function addWhere($query, $value = false, $orQuery)
    {
        if ($this instanceof Product) {
            $whereMethod = $orQuery ? "orWhere" : "where";
            $query->{$whereMethod}($this->field, "like", $value);
        } else {
            if ($this instanceof Addon) {
                $this->addonCheck($query, [$value], $orQuery);
            }
        }
    }

    public function scopeRapidssl($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_RAPIDSSL . "\\_%");
    }

    public function scopeOrRapidssl($query)
    {
        $this->addWhere($query, \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_RAPIDSSL . "\\_%", true);
    }

    public function scopeGeotrust($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_GEOTRUST . "\\_%");
    }

    public function scopeOrGeotrust($query)
    {
        $this->addWhere($query, \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_GEOTRUST . "\\_%", true);
    }

    public function scopeDigicert($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_DIGICERT . "\\_%");
    }

    public function scopeOrDigicert($query)
    {
        $this->addWhere($query, \WHMCS\MarketConnect\Services\Symantec::SSL_TYPE_DIGICERT . "\\_%", true);
    }

    public function scopeSymantec($query)
    {
        $this->scopeSsl($query);
    }

    public function scopeWeebly($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_WEEBLY . "\\_%");
    }

    public function scopeSpamexperts($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_SPAMEXPERTS . "\\_%");
    }

    public function scopeSitelock($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_SITELOCK . "\\_%");
    }

    public function scopeSitelockVPN($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_SITELOCKVPN . "\\_%");
    }

    public function scopeNordVPN($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_NORDVPN . "\\_%");
    }

    public function scopeCodeguard($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_CODEGUARD . "\\_%");
    }

    public function scopeMarketgoo($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_MARKETGOO . "\\_%");
    }

    public function scopeOx($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_OX . "\\_%");
    }

    public function scopeSitebuilder($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_SITEBUILDER . "\\_%");
    }

    public function scopeSiteplus($query)
    {
        $this->scopeSitebuilder($query);
    }

    public function scopeXoviNow(\Illuminate\Database\Eloquent\Builder $query)
    {
        $query = $this->scopeMarketConnect($query);
        if ($this instanceof Product) {
            $query->where($this->field, "like", \WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW . "\\_%");
        } else {
            if ($this instanceof Addon) {
                $this->addonCheck($query, [\WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW . "\\_%"]);
            }
        }
    }

    public function scopeThreeSixtyMonitoring($query)
    {
        $query = $this->scopeMarketConnect($query);
        $this->addWhere($query, \WHMCS\MarketConnect\MarketConnect::SERVICE_THREESIXTYMONITORING . "\\_%");
    }

    public function scopeProductKey($query, $productKey)
    {
        $query = $this->scopeMarketConnect($query);
        if ($this instanceof Product) {
            $query->where($this->field, $productKey);
        } else {
            if ($this instanceof Addon) {
                $query->whereHas("moduleConfiguration", function (\Illuminate\Database\Eloquent\Builder $query) use($productKey) {
                    $query->where("setting_name", $this->field)->where("value", $productKey);
                });
            }
        }
    }

    public function scopeMarketConnectProducts($query, $products)
    {
        $this->scopeMarketConnect($query);
        if ($this instanceof Product) {
            $query->whereIn($this->field, $products);
        } else {
            if ($this instanceof Addon) {
                $query->whereHas("moduleConfiguration", function (\Illuminate\Database\Eloquent\Builder $query) use($products) {
                    $query->where("setting_name", $this->field)->whereIn("value", $products);
                });
            }
        }
    }

    public function getMarketConnectType()
    {
        if ($this instanceof Addon) {
            $value = $this->moduleConfiguration()->where("setting_name", $this->field)->value("value");
        } else {
            $value = $this->{$this->field};
        }
        $value = explode("_", $value);
        switch ($value[0]) {
            case "weebly":
            case "spamexperts":
            case "sitelock":
            case "codeguard":
            case "marketgoo":
            case "ox":
            case "sitebuilder":
            case "siteplus":
            case "threesixtymonitoring":
                return $value[0];
                break;
            case "sitelockvpn":
                return "sitelockVPN";
                break;
            case "ssl":
            case "rapidssl":
            case "geotrust":
            case "digicert":
            case "symantec":
            default:
                return "";
        }
    }

    protected function addonCheck($query, $values = false, $orCheck)
    {
        $method = "whereHas";
        if ($orCheck) {
            $method = "orWhereHas";
        }
        $query->{$method}("moduleConfiguration", function (\Illuminate\Database\Eloquent\Builder $query) use($values) {
            $query->where("setting_name", $this->field)->where(function (\Illuminate\Database\Eloquent\Builder $query2) {
                foreach ($values as $i => $value) {
                    if ($i === 0) {
                        $query2->where("value", "like", $value);
                    } else {
                        $query2->orWhere("value", "like", $value);
                    }
                }
            });
        });
    }
}

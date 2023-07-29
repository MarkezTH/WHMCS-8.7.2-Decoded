<?php

namespace WHMCS\Module\Registrar\CentralNic;

class Zones
{
    protected $filename = "zonesinfo.json";
    protected $path = NULL;
    protected $zonesData = NULL;

    public function __construct($path = NULL, $filename = NULL)
    {
        if (!empty($path)) {
            $this->setPath($path);
        }
        if (!empty($filename)) {
            $this->setFilename($filename);
        }
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    public function getPath()
    {
        if (!$this->path) {
            $this->path = implode(DIRECTORY_SEPARATOR, [ROOTDIR, "modules", "registrars", "centralnic", "resources"]);
        }
        return $this->path;
    }

    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    public function load()
    {
        if (empty($this->zonesData)) {
            $this->zonesData = collect();
            $data = (new \WHMCS\File($this->getPathFile()))->contents();
            if ($data) {
                $zones = json_decode($data);
                foreach ($zones as $zone) {
                    if (!empty($zone->zone)) {
                        $this->setZone(new Zone($zone->zone, $zone->periods, (int) $zone->grace_days, (int) $zone->redemption_days, (bool) $zone->epp_required, (bool) $zone->id_protection, (bool) $zone->supports_renewals, (bool) $zone->renews_on_transfer, (bool) $zone->handle_updatable, (bool) $zone->needs_trade, \Carbon\Carbon::createFromFormat("Y-m-d H:i:s", $zone->updated_at)));
                    }
                }
            }
        }
        return $this;
    }

    public function getZones()
    {
        return $this->zonesData ?? collect();
    }

    public function toArray()
    {
        $result = [];
        $this->getZones()->each(function ($zone) use($result) {
            $result[$zone->zone()] = $zone->toArray();
        });
        return $result;
    }

    public function findZone($zoneName)
    {
        return $this->getZones()->first(function ($item) use($zoneName) {
            return $item->zone() == $zoneName;
        });
    }

    public function setZone($zone)
    {
        $this->zonesData->put($zone->zone(), $zone);
        return $this;
    }

    public function save()
    {
        (new \WHMCS\File($this->getPathFile()))->create(json_encode($this->toArray(), JSON_PRETTY_PRINT) . PHP_EOL);
        return $this;
    }

    public function findOrCreate($api, $zoneName)
    {
        try {
            $zone = $this->findZone($zoneName);
            if (is_null($zone)) {
                $zone = self::getRemoteZoneInfo($api, $zoneName);
                $this->setZone($zone);
                $this->save();
            }
            return $zone;
        } catch (\Exception $e) {
            return NULL;
        }
    }

    public static function getRemoteZoneInfo($api, $zone)
    {
        $response = (new Commands\GetZoneInfo($api, $zone))->execute();
        if (!$response->getData()) {
            throw new \Exception("Invalid Zone Info");
        }
        $zone = $response->getDataValue("zone");
        $period = $response->getDataValue("periods") ?: $response->getDataValue("registrationperiods");
        $graceDays = (int) $response->getDataValue("autorenewgraceperioddays") ?: 0;
        $redemptionDays = (int) $response->getDataValue("redemptionperioddays") ?: 0;
        $eppRequired = $response->getDataValue("authcode") == "required";
        $idProtection = $response->getDataValue("rrpsupportswhoisprivacy") || $response->getDataValue("supportstrustee");
        $supportsRenewals = $response->getDataValue("rrpsupportsrenewal") == 1;
        $renewsOnTransfer = $response->getDataValue("renewalattransfer") == 1 || $response->getDataValue("renewalaftertransfer") == 1;
        $handleUpdatable = $response->getDataValue("handlesupdateable") == 1;
        $needsTrade = strtoupper($response->getDataValue("ownerchangeprocess")) == "TRADE";
        return new Zone($zone, $period, $graceDays, $redemptionDays, $eppRequired, $idProtection, $supportsRenewals, $renewsOnTransfer, $handleUpdatable, $needsTrade, \Carbon\Carbon::now());
    }

    protected function getPathFile()
    {
        return $this->getPath() . DIRECTORY_SEPARATOR . $this->getFilename();
    }
}

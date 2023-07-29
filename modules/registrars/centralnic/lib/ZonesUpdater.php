<?php

namespace WHMCS\Module\Registrar\CentralNic;

class ZonesUpdater
{
    protected $api = NULL;
    protected $zones = NULL;
    protected $path = NULL;
    protected $filename = NULL;
    protected $logPrefix = "CentralNic";
    const MAX_ZONES_UPDATE = 100;

    public function __construct(Api\ApiInterface $api, $path = NULL, $filename = NULL)
    {
        $this->api = $api;
        $this->path = $path;
        $this->filename = $filename;
    }

    public function loadZones()
    {
        $this->zones = (new Zones($this->path, $this->filename))->load();
        return $this;
    }

    public function update()
    {
        if (!$this->zones) {
            throw new \Exception("Zones file does not exist.");
        }
        $this->log("Zones data sync started.");
        $this->updatingZones()->addingMissingZones();
        $this->log("Zones data sync finished.");
        return $this;
    }

    protected function updatingZones()
    {
        $updatedCount = 0;
        $updatedZones = [];
        $errorUpdates = [];
        $this->zones->getZones()->sortBy(function ($zone) {
            return $zone->updatedAt();
        })->take(self::MAX_ZONES_UPDATE)->filter(function ($zone) {
            return $zone->isStale();
        })->each(function ($zone) use($updatedCount, $updatedZones, $errorUpdates) {
            try {
                $remoteZone = Zones::getRemoteZoneInfo($this->api, $zone->zone());
                $this->zones->setZone($remoteZone);
                if (0 < count($zone->changed($remoteZone))) {
                    $updatedZones[] = $zone->zone();
                }
                $updatedCount++;
                unset($remoteZone);
            } catch (\Exception $e) {
                $errorUpdates[] = $zone->zone();
            }
        });
        if (!empty($errorUpdates)) {
            $this->log("Unable to save zones: %s.", implode(", ", $errorUpdates));
        }
        if (0 < $updatedCount) {
            try {
                $this->zones->save();
                if (0 < count($updatedZones)) {
                    $this->log("Data updated for %d zones: %s", count($updatedZones), implode(", ", $updatedZones));
                }
            } catch (\Exception $e) {
                throw new \Exception($this->transformMessage("Unable to save zones files: %s.", $e->getMessage()), $e->getCode(), $e);
            }
        }
        return $this;
    }

    protected function addingMissingZones()
    {
        $response = (new Commands\QueryZoneList($this->api))->execute();
        $zonesAdded = [];
        $errorAdd = [];
        foreach ($response->getData()["zone"] ?? [] as $id => $zoneName) {
            if (!$this->zones->findZone($zoneName)) {
                try {
                    $zone = Zones::getRemoteZoneInfo($this->api, $zoneName);
                    if ($zone->periodYears()) {
                        $this->zones->setZone(Zones::getRemoteZoneInfo($this->api, $zoneName));
                        $zonesAdded[] = $zoneName;
                    }
                } catch (\Exception $e) {
                    $errorAdd[] = $zoneName;
                }
            }
        }
        if (!empty($errorAdd)) {
            $this->log("Unable to add zones: %s.", implode(", ", $errorAdd));
        }
        if (0 < count($zonesAdded)) {
            try {
                $this->zones->save();
                $this->log("Data added for %d zones: %s.", count($zonesAdded), implode(", ", $zonesAdded));
            } catch (\Exception $e) {
                throw new \Exception($this->transformMessage("Unable to save zones files: %s.", $e->getMessage()), $e->getCode(), $e);
            }
        }
        return $this;
    }

    public function getLogPrefix()
    {
        return $this->logPrefix;
    }

    protected function log($self, $message, $values)
    {
        logActivity($this->transformMessage($message, ...$values));
        return $this;
    }

    protected function transformMessage($message, $values)
    {
        return vsprintf("[" . $this->getLogPrefix() . "] " . $message, $values);
    }
}

<?php

namespace WHMCS;

class TransientData
{
    protected $chunkSize = 62000;
    const DB_TABLE = "tbltransientdata";

    public static function getInstance()
    {
        return new self();
    }

    public function store($name, $data = 300, int $life)
    {
        if (!is_string($data)) {
            return false;
        }
        $expires = time() + $life;
        if ($this->ifNameExists($name)) {
            $this->sqlUpdate($name, $data, $expires);
        } else {
            $this->sqlInsert($name, $data, $expires);
        }
        return true;
    }

    public function chunkedStore($name, $data = 300, int $life)
    {
        if (!is_string($data)) {
            return false;
        }
        $expires = time() + $life;
        $this->clearChunkedStorage($name);
        for ($i = 0; 0 < strlen($data); $i++) {
            $this->sqlInsert($name . ".chunk-" . $i, substr($data, 0, $this->chunkSize), $expires);
            $data = substr($data, $this->chunkSize);
        }
        return true;
    }

    protected function clearChunkedStorage($name)
    {
        Database\Capsule::table(self::DB_TABLE)->where("name", "LIKE", $name . ".chunk-%")->delete();
    }

    public function retrieve($name)
    {
        return $this->sqlSelect($name, true);
    }

    public function retrieveIncludeExpired($name)
    {
        return $this->sqlSelect($name);
    }

    public function retrieveChunkedItem($name)
    {
        $data = Database\Capsule::table(self::DB_TABLE)->where("name", "LIKE", $name . ".chunk-%")->where("expires", ">=", time())->pluck("data")->all();
        if (0 < count($data)) {
            return implode($data);
        }
        return NULL;
    }

    public function retrieveByData($data)
    {
        return $this->sqlSelectByData($data, true);
    }

    public function ifNameExists($name)
    {
        $data = $this->sqlSelect($name);
        return $data !== NULL;
    }

    public function ifUnexpiredNameExists($name)
    {
        $data = $this->sqlSelect($name, true);
        return $data !== NULL;
    }

    public function delete($name)
    {
        $this->sqlDelete($name);
        return true;
    }

    public function purgeExpired($delaySeconds)
    {
        $now = time() - $delaySeconds;
        return (bool) Database\Capsule::table(self::DB_TABLE)->where("expires", "<", $now)->delete();
    }

    protected function sqlSelect($name = false, $exclude_expired)
    {
        $lookup = Database\Capsule::table(self::DB_TABLE)->where("name", $name);
        if ($exclude_expired) {
            $lookup->where("expires", ">", time());
        }
        return $lookup->value("data");
    }

    protected function sqlSelectByData($data = false, $exclude_expired)
    {
        $lookup = Database\Capsule::table(self::DB_TABLE)->where("data", "=", $data);
        if ($exclude_expired) {
            $lookup->where("expires", ">", Carbon::now()->timestamp);
        }
        return $lookup->value("name");
    }

    protected function sqlInsert($name, $data, int $expires)
    {
        return Database\Capsule::table(self::DB_TABLE)->insertGetId(["name" => $name, "data" => $data, "expires" => $expires]);
    }

    protected function sqlUpdate($name, $data, int $expires)
    {
        return (bool) Database\Capsule::table(self::DB_TABLE)->where("name", $name)->update(["data" => $data, "expires" => $expires]);
    }

    public function sqlDelete($name)
    {
        return (bool) Database\Capsule::table(self::DB_TABLE)->where("name", $name)->delete();
    }
}

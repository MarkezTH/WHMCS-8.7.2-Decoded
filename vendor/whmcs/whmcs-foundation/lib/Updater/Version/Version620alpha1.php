<?php

namespace WHMCS\Updater\Version;

class Version620alpha1 extends IncrementalVersion
{
    protected $updateActions = ["createUuidColumnsIfNecessary", "createUuids"];

    public function createUuidColumnsIfNecessary()
    {
        $pdo = \Illuminate\Database\Capsule\Manager::connection();
        if (!\Illuminate\Database\Capsule\Manager::schema()->hasColumn("tblclients", "uuid")) {
            $pdo->unprepared("ALTER TABLE `tblclients` ADD COLUMN `uuid` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`;");
        }
        if (!\Illuminate\Database\Capsule\Manager::schema()->hasColumn("tbladmins", "uuid")) {
            $pdo->unprepared("ALTER TABLE `tbladmins` ADD COLUMN `uuid` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`;");
        }
    }

    public function createUuids()
    {
        $pdo = \Illuminate\Database\Capsule\Manager::connection();
        foreach (["tblclients", "tbladmins"] as $table) {
            $countQuery = $pdo->raw("SELECT count(id) as count from " . $table . " where uuid = \"\"");
            $needsUuidQuery = $pdo->raw("SELECT id from " . $table . " where uuid = \"\" limit 5000");
            $updateUuidQuery = $pdo->raw("UPDATE %s SET uuid = \"%s\" WHERE id = \"%s\";");
            $uuidCountResult = $pdo->select($countQuery);
            while ($uuidCountResult[0]->count) {
                $userRows = $pdo->select($needsUuidQuery);
                $statement = "";
                foreach ($userRows as $user) {
                    $uuid = \Ramsey\Uuid\Uuid::uuid4();
                    $statement .= sprintf($updateUuidQuery, $table, $uuid->toString(), (int) $user->id);
                }
                $pdo->unprepared($statement);
                $uuidCountResult = $pdo->select($countQuery);
            }
        }
        return $this;
    }
}

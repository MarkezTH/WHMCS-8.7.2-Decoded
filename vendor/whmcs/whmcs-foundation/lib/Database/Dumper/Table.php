<?php

namespace WHMCS\Database\Dumper;

class Table
{
    protected $database = NULL;
    protected $tableName = NULL;
    protected $dumpSchema = true;
    protected $dumpData = true;
    protected $addDropTable = true;
    protected $addTableLocks = true;
    protected $lockTableDuringDump = true;
    protected $selectBatchSize = 100;

    public function __construct(\WHMCS\Database\DatabaseInterface $database, $tableName, $options = [])
    {
        $this->setDatabase($database)->setTableName($tableName);
        if (isset($options["dumpSchema"])) {
            $this->setDumpSchema($options["dumpSchema"]);
        }
        if (isset($options["dumpData"])) {
            $this->setDumpData($options["dumpData"]);
        }
        if (isset($options["addDropTable"])) {
            $this->setAddDropTable($options["addDropTable"]);
        }
        if (isset($options["addTableLocks"])) {
            $this->setAddTableLocks($options["addTableLocks"]);
        }
        if (isset($options["lockTableDuringDump"])) {
            $this->setLockTableDuringDump($options["lockTableDuringDump"]);
        }
        if (isset($options["selectBatchSize"])) {
            $this->setSelectBatchSize($options["selectBatchSize"]);
        }
    }

    protected function setDatabase(\WHMCS\Database\DatabaseInterface $database)
    {
        $this->database = $database;
        return $this;
    }

    protected function getDatabase()
    {
        return $this->database;
    }

    protected function setTableName($tableName)
    {
        if (!is_string($tableName) || trim($tableName) == "") {
            throw new \WHMCS\Exception("Please provide a table name.");
        }
        $this->tableName = $tableName;
        return $this;
    }

    protected function getTableName()
    {
        return $this->tableName;
    }

    protected function setDumpSchema($dumpSchema)
    {
        if (!is_bool($dumpSchema)) {
            throw new \WHMCS\Exception("Invalid dump schema option.");
        }
        $this->dumpSchema = $dumpSchema;
        return $this;
    }

    protected function getDumpSchema()
    {
        return $this->dumpSchema;
    }

    protected function setDumpData($dumpData)
    {
        if (!is_bool($dumpData)) {
            throw new \WHMCS\Exception("Invalid dump data option.");
        }
        $this->dumpData = $dumpData;
        return $this;
    }

    protected function getDumpData()
    {
        return $this->dumpData;
    }

    protected function setAddDropTable($addDropTable)
    {
        if (!is_bool($addDropTable)) {
            throw new \WHMCS\Exception("Invalid add drop table option.");
        }
        $this->addDropTable = $addDropTable;
        return $this;
    }

    protected function getAddDropTable()
    {
        return $this->addDropTable;
    }

    protected function setAddTableLocks($addTableLocks)
    {
        if (!is_bool($addTableLocks)) {
            throw new \WHMCS\Exception("Invalid add table locks option.");
        }
        $this->addTableLocks = $addTableLocks;
        return $this;
    }

    protected function getAddTableLocks()
    {
        return $this->addTableLocks;
    }

    public function setLockTableDuringDump($lockTableDuringDump)
    {
        if (!is_bool($lockTableDuringDump)) {
            throw new \WHMCS\Exception("Invalid lock tables option.");
        }
        $this->lockTableDuringDump = $lockTableDuringDump;
        return $this;
    }

    public function getLockTableDuringDump()
    {
        return $this->lockTableDuringDump;
    }

    protected function setSelectBatchSize($selectBatchSize)
    {
        if (!is_int($selectBatchSize) || $selectBatchSize < 0) {
            throw new \WHMCS\Exception("Invalid select batch size option.");
        }
        $this->selectBatchSize = $selectBatchSize;
        return $this;
    }

    protected function getSelectBatchSize()
    {
        return $this->selectBatchSize;
    }

    public function dump($fh)
    {
        if (!is_resource($fh)) {
            throw new \WHMCS\Exception("Please provide a valid fopen() handle.");
        }
        if ($this->getDumpSchema()) {
            $result = fwrite($fh, $this->generateSchemaHeader());
            if ($result === false || $result === 0) {
                throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " schema header.");
            }
            $result = fwrite($fh, $this->generateSchema());
            if ($result === false || $result === 0) {
                throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " schema.");
            }
            $result = fwrite($fh, $this->generateSchemaFooter());
            if ($result === false || $result === 0) {
                throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " schema footer.");
            }
        }
        if ($this->getDumpData()) {
            $result = fwrite($fh, $this->generateDataHeader());
            if ($result === false || $result === 0) {
                throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " data header.");
            }
            if ($this->getAddTableLocks()) {
                $result = fwrite($fh, $this->generateLockTableHeader());
                if ($result === false || $result === 0) {
                    throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " table lock.");
                }
            }
            $this->lock();
            $startRow = 0;
            $rowCount = $this->getRowCount();
            if (0 < $rowCount) {
                while ($startRow < $rowCount) {
                    $rowBatch = $this->getDataBatch($startRow, $this->getSelectBatchSize());
                    foreach ($rowBatch as $row) {
                        $result = fwrite($fh, $this->generateDataRow((array) $row));
                        if ($result === false || $result === 0) {
                            throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " data.");
                        }
                    }
                    $startRow += $this->getSelectBatchSize();
                }
            }
            $this->unlock();
            if ($this->getAddTableLocks()) {
                $result = fwrite($fh, $this->generateLockTableFooter());
                if ($result === false || $result === 0) {
                    throw new \WHMCS\Exception("Unable to write " . $this->getTableName() . " table unlock.");
                }
            }
        }
        return $this;
    }

    protected function getRowCount()
    {
        try {
            return \WHMCS\Database\Capsule::table($this->getTableName())->count();
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \WHMCS\Exception("Unable to retrieve " . $this->getTableName() . " row count: " . $e->getMessage() . ".");
        }
    }

    protected function getDataBatch($Collection, $offset, int $limit)
    {
        try {
            return \WHMCS\Database\Capsule::table($this->getTableName())->limit($limit)->offset($offset)->get();
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \WHMCS\Exception("Unable to retrieve " . $this->getTableName() . " data batch: " . $e->getMessage() . ".");
        }
    }

    protected function generateDataRow($row)
    {
        if (count($row) == 0 || array_values($row) === $row) {
            throw new \WHMCS\Exception("Unable to generate an insert statement from an empty row.");
        }
        foreach ($row as $key => $value) {
            if (is_null($value)) {
                $row[$key] = "NULL";
            } else {
                $row[$key] = \WHMCS\Database\Capsule::getInstance()->getConnection()->getPdo()->quote($value);
            }
        }
        $fields = implode("`, `", array_keys($row));
        $values = implode(", ", array_values($row));
        return "INSERT INTO `" . $this->getTableName() . "` (`" . $fields . "`) VALUES (" . $values . ");" . PHP_EOL;
    }

    protected function generateSchema()
    {
        try {
            $query = \WHMCS\Database\Capsule::select(\WHMCS\Database\Capsule::raw("SHOW CREATE TABLE `" . $this->getTableName() . "`"));
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \WHMCS\Exception("Unable to generate " . $this->getTableName() . " schema: " . $e->getMessage() . ".");
        }
        if (!isset($query[0]->_obfuscated_437265617465205461626C65_)) {
            throw new \WHMCS\Exception("Unable to retrieve " . $this->getTableName() . " schema.");
        }
        return $query[0]->_obfuscated_437265617465205461626C65_ . ";" . PHP_EOL;
    }

    protected function generateSchemaHeader()
    {
        $return = "--\n-- Table structure for table `" . $this->getTableName() . "`\n--\n\n";
        if ($this->getAddDropTable()) {
            $return .= $this->generateDropTable();
        }
        $return .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!40101 SET character_set_client = utf8 */;\n";
        return $return;
    }

    protected function generateSchemaFooter()
    {
        return "/*!40101 SET character_set_client = @saved_cs_client */;\n\n";
    }

    protected function generateDataHeader()
    {
        return "--\n-- Dumping data for table `" . $this->getTableName() . "`\n--\n\n";
    }

    protected function generateLockTableHeader()
    {
        return "LOCK TABLES `" . $this->getTableName() . "` WRITE;\n/*!40000 ALTER TABLE `" . $this->getTableName() . "` DISABLE KEYS */;\n";
    }

    protected function generateLockTableFooter()
    {
        return "/*!40000 ALTER TABLE `" . $this->getTableName() . "` ENABLE KEYS */;\nUNLOCK TABLES;\n";
    }

    protected function generateDropTable()
    {
        return "DROP TABLE IF EXISTS `" . $this->getTableName() . "`;\n";
    }

    protected function lock()
    {
        try {
            \WHMCS\Database\Capsule::getInstance()->getConnection()->getPdo()->exec("LOCK TABLES `" . $this->getTableName() . "` WRITE");
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \WHMCS\Exception("Unable to lock " . $this->getTableName() . " for writing: " . $e->getMessage());
        }
        return $this;
    }

    protected function unlock()
    {
        try {
            \WHMCS\Database\Capsule::getInstance()->getConnection()->getPdo()->exec("UNLOCK TABLES");
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \WHMCS\Exception("Unable to unlock tables: " . $e->getMessage());
        }
        return $this;
    }
}

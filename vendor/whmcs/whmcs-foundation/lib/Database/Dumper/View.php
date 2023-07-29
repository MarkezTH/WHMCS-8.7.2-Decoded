<?php

namespace WHMCS\Database\Dumper;

class View
{
    protected $database = NULL;
    protected $viewName = NULL;

    public function __construct(\WHMCS\Database\DatabaseInterface $database, $viewName)
    {
        $this->setDatabase($database)->setViewName($viewName);
    }

    protected function setDatabase($self, $database)
    {
        $this->database = $database;
        return $this;
    }

    protected function setViewName($View, $viewName)
    {
        if (!is_string($viewName) || trim($viewName) == "") {
            throw new \WHMCS\Exception("Please provide a view name.");
        }
        $this->viewName = $viewName;
        return $this;
    }

    protected function getViewName()
    {
        return $this->viewName;
    }

    public function dump($self, $fh)
    {
        if (!is_resource($fh)) {
            throw new \WHMCS\Exception("Please provide a valid fopen() handle.");
        }
        $result = fwrite($fh, $this->generateSchemaHeader());
        if ($result === false || $result === 0) {
            throw new \WHMCS\Exception("Unable to write `" . $this->getViewName() . "` view schema header.");
        }
        $result = fwrite($fh, $this->generateSchema());
        if ($result === false || $result === 0) {
            throw new \WHMCS\Exception("Unable to write `" . $this->getViewName() . "` view schema.");
        }
        $result = fwrite($fh, $this->generateSchemaFooter());
        if ($result === false || $result === 0) {
            throw new \WHMCS\Exception("Unable to write `" . $this->getViewName() . "` view schema footer.");
        }
        return $this;
    }

    protected function generateSchema()
    {
        try {
            $query = \WHMCS\Database\Capsule::select(\WHMCS\Database\Capsule::raw("SHOW CREATE VIEW `" . $this->getViewName() . "`"));
        } catch (\Illuminate\Database\QueryException $e) {
            throw new \WHMCS\Exception("Unable to generate `" . $this->getViewName() . "` view schema: " . $e->getMessage() . ".");
        }
        if (!isset($query[0]->_obfuscated_4372656174652056696577_)) {
            throw new \WHMCS\Exception("Unable to retrieve `" . $this->getViewName() . "` view schema.");
        }
        return $query[0]->_obfuscated_4372656174652056696577_ . ";" . PHP_EOL;
    }

    protected function generateSchemaHeader()
    {
        $return = "--\n-- View structure for view `" . $this->getViewName() . "`\n--\n\n";
        $return .= $this->generateDropView();
        $return .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!40101 SET character_set_client = utf8 */;\n";
        return $return;
    }

    protected function generateSchemaFooter()
    {
        return "/*!40101 SET character_set_client = @saved_cs_client */;\n\n";
    }

    protected function generateDropView()
    {
        return "DROP VIEW IF EXISTS `" . $this->getViewName() . "`;\n";
    }
}

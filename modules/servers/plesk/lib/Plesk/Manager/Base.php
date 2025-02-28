<?php

abstract class Plesk_Manager_Base
{
    public function __call($name, $args)
    {
        $methodName = "_" . $name;
        if (!method_exists($this, $methodName)) {
            throw new Exception(Plesk_Registry::getInstance()->translator->translate("ERROR_NO_TEMPLATE_TO_API_VERSION", ["METHOD" => $methodName, "API_VERSION" => $this->getVersion()]));
        }
        $reflection = new ReflectionClass(get_class($this));
        $declaringClassName = $reflection->getMethod($methodName)->getDeclaringClass()->name;
        $declaringClass = new $declaringClassName();
        $version = $declaringClass->getVersion();
        $currentApiVersion = Plesk_Registry::getInstance()->version ?? NULL;
        Plesk_Registry::getInstance()->version = $version;
        $result = call_user_func_array([$this, $methodName], $args);
        Plesk_Registry::getInstance()->version = $currentApiVersion;
        return $result;
    }

    public function getVersion()
    {
        $className = get_class($this);
        return implode(".", str_split(substr($className, strrpos($className, "V") + 1)));
    }

    public function createTableForAccountStorage()
    {
        if (Illuminate\Database\Capsule\Manager::schema()->hasTable("mod_pleskaccounts")) {
            return NULL;
        }
        Illuminate\Database\Capsule\Manager::schema()->create("mod_pleskaccounts", function ($table) {
            $table->engine = "InnoDB";
            $table->integer("userid");
            $table->string("usertype");
            $table->string("panelexternalid");
            $table->primary(["userid", "usertype"]);
        });
    }

    protected function _checkErrors($result)
    {
        if (Plesk_Api::STATUS_OK === (string) $result->status) {
            return NULL;
        }
        switch ((int) $result->errcode) {
            case Plesk_Api::ERROR_AUTHENTICATION_FAILED:
                $errorMessage = Plesk_Registry::getInstance()->translator->translate("ERROR_AUTHENTICATION_FAILED");
                break;
            case Plesk_Api::ERROR_AGENT_INITIALIZATION_FAILED:
                $errorMessage = Plesk_Registry::getInstance()->translator->translate("ERROR_AGENT_INITIALIZATION_FAILED");
                break;
            default:
                $errorMessage = (string) $result->errtext;
                throw new Exception($errorMessage, (int) $result->errcode);
        }
    }
}

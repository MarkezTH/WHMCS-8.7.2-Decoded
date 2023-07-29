<?php

namespace WHMCS\Config;

class Application extends AbstractConfig implements DatabaseInterface
{
    protected $loadedFilename = NULL;
    protected $rootDir = NULL;
    protected $requireConfigurationValues = ["license" => "License Key", "db_host" => "Database Hostname", "db_username" => "Database Username", "db_password" => "Database Password", "db_name" => "Database Name", "mysql_charset" => "MySQL Charset", "cc_encryption_hash" => "Encryption Hash", "templates_compiledir" => "Template Compile Directory"];
    const WHMCS_DEFAULT_CONFIG_FILE = "configuration.php";
    const DEFAULT_ATTACHMENTS_FOLDER = "attachments";
    const DEFAULT_DOWNLOADS_FOLDER = "downloads";
    const DEFAULT_COMPILED_TEMPLATES_FOLDER = "templates_c";
    const DEFAULT_ADMIN_FOLDER = "admin";
    const DEFAULT_CRON_FOLDER = "crons";
    const DEFAULT_PROJECT_FOLDER = "attachments/projects";
    const DEFAULT_SQL_MODE = "";

    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->rootDir = ROOTDIR;
    }

    public function isConfigFileLoaded()
    {
        return !empty($this->loadedFilename);
    }

    public function getLoadedFilename()
    {
        return $this->loadedFilename;
    }

    protected function setLoadedFilename($filename)
    {
        $this->loadedFilename = $filename;
        return $this;
    }

    public function validConfigVariables()
    {
        return ["api_access_key", "api_enable_logging", "attachments_dir", "customadminpath", "cc_encryption_hash", "crons_dir", "disable_iconv", "disable_admin_ticket_page_counts", "disable_auto_ticket_refresh", "disable_clients_list_services_summary", "display_errors", "db_host", "db_name", "db_username", "db_password", "db_port", "downloads_dir", "error_reporting_level", "license", "license_debug", "mysql_charset", "disable_hook_loading", "DomainMinLengthRestrictions", "DomainMaxLengthRestrictions", "DomainRenewalMinimums", "overidephptimelimit", "pleskpacketversion", "plesk8packetversion", "plesk10packetversion", "smtp_debug", "serialize_input_max_length", "serialize_array_max_length", "serialize_array_max_depth", "templates_compiledir", "use_legacy_client_ip_logic", "smarty_security_policy", "use_internal_update_resources", "update_dry_run_only", "use_internal_licensing_validation", "outbound_http_proxy", "sql_mode", "session_handling", "outbound_http_ssl_verifyhost", "outbound_http_ssl_verifypeer", "use_marketplace_testing_env", "use_marketplace_local_testing_env", "enable_safe_include", "disable_to_do_list_entries", "disable_whmcs_domain_lookup", "domain_lookup_url", "domain_lookup_key", "pop_cron_debug", "hooks_debug_whitelist", "skip_mail_ssl_validation", "enable_transaction_formatting", "automationStatus", "internalErrorLogMaxRows", "internalErrorLogMaxDays", "allow_external_login_forms", "use_validation_com_sandbox", "disable_php8_warning_suppression", "surface_errors_as_exceptions"];
    }

    public function loadConfigFile($file)
    {
        $file = $this->getAbsolutePath($file);
        if ($this->configFileExists($file)) {
            ob_start();
            $loaded = (include $file);
            ob_end_clean();
            if ($loaded === false) {
                return false;
            }
            $validVars = $this->validConfigVariables();
            $data = [];
            foreach ($validVars as $var) {
                if ($var == "outbound_http_ssl_verifyhost" || $var == "outbound_http_ssl_verifypeer") {
                    $data[$var] = isset($var) ? (bool) ${$var} : false;
                } else {
                    $data[$var] = isset($var) ? ${$var} : NULL;
                }
            }
            if (isset($data["db_host"])) {
                list($host, $port) = $this->parseDatabasePortFromHost($data["db_host"]);
                $data["db_host"] = $host;
                if ($port && !$data["db_port"]) {
                    $data["db_port"] = $port;
                }
            }
            $data = $data + $this->getData();
            $this->setData($data);
            $this->loadedFilename = $file;
            return $this;
        } else {
            return false;
        }
    }

    public function configFileExists($file)
    {
        $file = $this->getAbsolutePath($file);
        return file_exists($file) ? true : false;
    }

    protected function getAbsolutePath($file)
    {
        if (strpos($file, ROOTDIR) !== 0) {
            $file = ROOTDIR . DIRECTORY_SEPARATOR . $file;
        }
        return $file;
    }

    public function stripRootPath($path)
    {
        if (strpos($path, $this->getRootDir()) !== 0) {
            return $path;
        }
        $path = substr($path, strlen($this->getRootDir()));
        $path = ltrim($path, "/");
        return $path;
    }

    public function makeAbsoluteToRootIfNot($file)
    {
        return \WHMCS\Utility\File::makePathAbsolute($file, $this->getRootDir());
    }

    public function getDatabaseName()
    {
        return $this->OffsetGet("db_name");
    }

    public function getDatabaseUserName()
    {
        return $this->OffsetGet("db_username");
    }

    public function getDatabasePassword()
    {
        return $this->OffsetGet("db_password");
    }

    public function getDatabaseHost()
    {
        return $this->OffsetGet("db_host");
    }

    public function getDatabaseCharset()
    {
        return $this->OffsetGet("mysql_charset");
    }

    public function getDatabasePort()
    {
        return $this->OffsetGet("db_port");
    }

    public function setDatabaseCharset($charset)
    {
        $this->OffsetSet("mysql_charset", $charset);
        return $this;
    }

    public function setDatabaseName($name)
    {
        $this->OffsetSet("db_name", $name);
        return $this;
    }

    public function setDatabaseUsername($username)
    {
        $this->OffsetSet("db_username", $username);
        return $this;
    }

    public function setDatabaseHost($host)
    {
        $this->OffsetSet("db_host", $host);
        return $this;
    }

    protected function parseDatabasePortFromHost($host)
    {
        $port = "";
        $address = "";
        $colons = substr_count($host, ":");
        if (!$colons) {
            $address = $host;
        } else {
            if (1 < $colons) {
                $address = $host;
            } else {
                if ($colons == 1) {
                    list($address, $port) = explode(":", $host);
                }
            }
        }
        return [$address, $port];
    }

    public function setDatabasePassword($password)
    {
        $this->OffsetSet("db_password", $password);
        return $this;
    }

    public function setDatabasePort($port)
    {
        $this->OffsetSet("db_port", $port);
        return $this;
    }

    public function getDefaultApplicationConfigFilename()
    {
        return static::WHMCS_DEFAULT_CONFIG_FILE;
    }

    public function hasCustomWritableDirectories()
    {
        return $this->OffsetGet("attachments_dir") != ROOTDIR . DIRECTORY_SEPARATOR . self::DEFAULT_ATTACHMENTS_FOLDER && $this->OffsetGet("downloads_dir") != ROOTDIR . DIRECTORY_SEPARATOR . self::DEFAULT_DOWNLOADS_FOLDER && $this->OffsetGet("templates_compiledir") != ROOTDIR . DIRECTORY_SEPARATOR . self::DEFAULT_COMPILED_TEMPLATES_FOLDER && $this->OffsetGet("crons_dir") != ROOTDIR . DIRECTORY_SEPARATOR . self::DEFAULT_CRON_FOLDER;
    }

    public function getRootDir()
    {
        return $this->rootDir;
    }

    public function getAttachmentsDefaultPath()
    {
        return $this->getAbsolutePath(static::DEFAULT_ATTACHMENTS_FOLDER);
    }

    public function getAbsoluteAttachmentsPath()
    {
        $attachmentsDir = "";
        if (!$this->attachments_dir || $this->attachments_dir == static::DEFAULT_ATTACHMENTS_FOLDER) {
            $attachmentsDir = $this->getAttachmentsDefaultPath();
        }
        if ($this->attachments_dir && $this->attachments_dir != $attachmentsDir) {
            $attachmentsDir = $this->attachments_dir;
        }
        return $attachmentsDir;
    }

    public function getSqlMode()
    {
        $sqlMode = NULL;
        if ($this->offsetExists("sql_mode")) {
            $sqlMode = $this->sql_mode;
        }
        if (!is_string($sqlMode)) {
            $sqlMode = static::DEFAULT_SQL_MODE;
        }
        return $sqlMode;
    }

    public function isTransactionFormattingEnabled()
    {
        $enabled = false;
        if ($this->offsetExists("enable_transaction_formatting")) {
            $enabled = (bool) $this->enable_transaction_formatting;
        }
        return $enabled;
    }

    public function invalidConfigurationValues()
    {
        $config = $this;
        $invalid = [];
        foreach ($this->requireConfigurationValues as $key => $value) {
            if (is_null($config[$key])) {
                $invalid[$key] = $value . " is not defined in configuration file.";
            } else {
                if (empty($config[$key])) {
                    $invalid[$key] = $value . " is required and cannot be empty.";
                }
            }
        }
        return $invalid;
    }

    public function write($config, $file)
    {
        $configContents[] = "<?php";
        $configContents[] = "";
        foreach ($this->validConfigVariables() as $name) {
            if (isset($config[$name])) {
                $configContents[] = sprintf("\$%s='%s';", $name, \WHMCS\Input\Sanitize::escapeSingleQuotedString($config[$name]));
            }
        }
        $configContents[] = "";
        return file_put_contents($file, implode("\n", $configContents));
    }
}

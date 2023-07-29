<?php

if (!defined("ROOTDIR")) {
    define("ROOTDIR", dirname(__DIR__));
}
if (file_exists(ROOTDIR . DIRECTORY_SEPARATOR . "c3.php")) {
    include ROOTDIR . DIRECTORY_SEPARATOR . "c3.php";
}
if (!defined("INSTALLER_DIR")) {
    define("INSTALLER_DIR", __DIR__);
}
ini_set("eaccelerator.enable", 0);
ini_set("eaccelerator.optimizer", 0);
require_once ROOTDIR . "/vendor/autoload.php";
require_once ROOTDIR . "/includes/functions.php";
require_once ROOTDIR . "/includes/dbfunctions.php";
require_once INSTALLER_DIR . "/functions.php";
$debugErrorLevel = 22519;
$errorLevel = basename(INSTALLER_DIR) == "install2" ? $debugErrorLevel : 0;
$errMgmt = WHMCS\Utility\ErrorManagement::boot();
if (empty($errorLevel)) {
    $errMgmt::disableIniDisplayErrors();
} else {
    $errMgmt::enableIniDisplayErrors();
}
$errMgmt::setErrorReportingLevel($errorLevel);
set_time_limit(0);
$runtimeStorage = new WHMCS\Config\RuntimeStorage();
$runtimeStorage->errorManagement = $errMgmt;
WHMCS\Utility\Bootstrap\Installer::boot($runtimeStorage);
$errMgmt->loadApplicationHandlers()->loadDeferredHandlers();
Log::debug("Installer bootstrapped");
WHMCS\Security\Environment::setHttpProxyHeader(DI::make("config")->outbound_http_proxy);
$whmcsInstaller = new WHMCS\Installer\Installer(new WHMCS\Version\SemanticVersion(WHMCS\Installer\Installer::DEFAULT_VERSION), new WHMCS\Version\SemanticVersion(WHMCS\Application::FILES_VERSION));
$whmcsInstaller->setInstallerDirectory(INSTALLER_DIR);
$step = isset($_REQUEST["step"]) ? trim($_REQUEST["step"]) : "";
$type = isset($_REQUEST["type"]) ? trim($_REQUEST["type"]) : "";
$doUpgradeFromInstall = isset($_REQUEST["do-upgrade-from-install"]) ? (bool) (int) $_REQUEST["do-upgrade-from-install"] : false;
$licenseKey = isset($_REQUEST["licenseKey"]) ? trim($_REQUEST["licenseKey"]) : "";
$databaseHost = isset($_REQUEST["databaseHost"]) ? trim($_REQUEST["databaseHost"]) : "";
$databasePort = isset($_REQUEST["databasePort"]) ? trim($_REQUEST["databasePort"]) : "";
$databaseUsername = isset($_REQUEST["databaseUsername"]) ? trim($_REQUEST["databaseUsername"]) : "";
$databasePassword = isset($_REQUEST["databasePassword"]) ? trim($_REQUEST["databasePassword"]) : "";
$databaseName = isset($_REQUEST["databaseName"]) ? trim($_REQUEST["databaseName"]) : "";
$firstName = isset($_REQUEST["firstName"]) ? WHMCS\Input\Sanitize::encode(trim($_REQUEST["firstName"])) : "";
$lastName = isset($_REQUEST["lastName"]) ? WHMCS\Input\Sanitize::encode(trim($_REQUEST["lastName"])) : "";
$email = isset($_REQUEST["email"]) ? WHMCS\Input\Sanitize::encode(trim($_REQUEST["email"])) : "";
$username = isset($_REQUEST["username"]) ? WHMCS\Input\Sanitize::encode(trim($_REQUEST["username"])) : "";
$password = isset($_REQUEST["password"]) ? WHMCS\Input\Sanitize::encode(trim($_REQUEST["password"])) : "";
$confirmPassword = isset($_REQUEST["confirmPassword"]) ? WHMCS\Input\Sanitize::encode(trim($_REQUEST["confirmPassword"])) : "";
$validationError = "";
try {
    $dbErrorMessage = "";
    if ($whmcsInstaller->isInstalled()) {
        DI::make("db")->getSqlVersion();
    }
} catch (Exception $e) {
    $dbErrorMessage = $e->getMessage();
}
$systemRequirements = [10 => ["Requirement" => "PHP Version", "CurrentValue" => PHP_VERSION, "RequiredValue" => "7.2.0", "PassingStatus" => version_compare(PHP_VERSION, "7.2.0", ">="), "Help" => "WHMCS requires a specific version of PHP. We always recommend running the latest available stable version."], 11 => ["Requirement" => "PHP Memory Limit", "CurrentValue" => WHMCS\Environment\Php::getIniSetting("memory_limit"), "RequiredValue" => "64M", "PassingStatus" => 67108864 <= WHMCS\Environment\Php::getPhpMemoryLimitInBytes() || ini_get("memory_limit") == -1, "Help" => "WHMCS requires a minimum PHP memory limit setting of 64M. We recommend setting this to 128M for the best experience. Increase the limit and try again."], 50 => ["Requirement" => "cURL with SSL Support", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => extension_loaded("curl") && WHMCS\Environment\Php::functionEnabled("curl_init") && WHMCS\Environment\Php::functionEnabled("curl_exec"), "Help" => "WHMCS requires cURL for external communication. The cURL extension is either missing or disabled."], 60 => ["Requirement" => "JSON", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => extension_loaded("json") && WHMCS\Environment\Php::functionEnabled("json_encode"), "Help" => "WHMCS requires JSON. The JSON extension is either missing or disabled. As of PHP 5.2.0, the JSON extension is bundled and compiled into PHP by default."], 70 => ["Requirement" => "PDO", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => extension_loaded("pdo"), "Help" => "WHMCS requires PDO for database connectivity. Load the PDO extension and try again."], 80 => ["Requirement" => "PDO-MySQL", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => extension_loaded("pdo_mysql"), "Help" => "WHMCS requires the PDO MySQL® driver for database connectivity. Load the PDO_MYSQL extension and try again."], 90 => ["Requirement" => "GD", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => extension_loaded("gd") && WHMCS\Environment\Php::functionEnabled("imagecreate"), "Help" => "WHMCS requires GD libraries for PHP in order to perform image processing. Proceeding without GD libraries may not allow WHMCS to function properly."], 95 => ["Requirement" => "XML", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => extension_loaded("xml"), "Help" => "WHMCS requires the XML library for PHP in order to use API connections with many popular services. Proceeding without the XML library may not allow WHMCS to function properly."]];
$systemRecommendations = [10 => ["Recommendation" => "Windows® Operating System Detected", "Condition" => "\\" == DIRECTORY_SEPARATOR, "Help" => "We validate WHMCS to run in Linux®-based environments running the Apache webserver. Other environments, like Windows-based configurations, may experience compatibility issues and are not supported."], 20 => ["Recommendation" => "Unable to Override Max Execution Time", "Condition" => WHMCS\Environment\Php::getIniSetting("max_execution_time") !== "0" && WHMCS\Environment\Php::getIniSetting("max_execution_time") <= 120, "Help" => "Currently, the PHP max_execution_time setting cannot be overridden. Proceeding may not allow the installation process to complete. We recommend setting the max_execution_time to at least 200 seconds."]];
if ($whmcsInstaller->isInstalled()) {
    $systemRequirements[15] = ["Requirement" => "MySQL® Connection", "RequiredValue" => "Available", "FailureValue" => "Unavailable", "PassingStatus" => $dbErrorMessage == "", "Help" => "There was an error while connecting to the MySQL database: " . $dbErrorMessage . ". Please correct the error before you proceed."];
}
$configFilename = ROOTDIR . DIRECTORY_SEPARATOR . "configuration.php";
$configFilePassingStatus = is_file($configFilename) && !is_link($configFilename) && is_writable($configFilename);
if (!$configFilePassingStatus) {
    @file_put_contents($configFilename, "<?php" . PHP_EOL . "// Auto-created by installer");
    $configFilePassingStatus = is_file($configFilename) && !is_link($configFilename) && is_writable($configFilename);
    if ($configFilePassingStatus && file_exists($configFilename . ".new")) {
        @unlink($configFilename . ".new");
    }
}
$fileWritePermissions = [10 => ["Requirement" => "Configuration File", "Path" => "/configuration.php", "PassingStatus" => $configFilePassingStatus, "Help" => file_exists($configFilename) ? "The configuration.php file must be writeable." : "The system could not create the configuration file. Rename the configuration.php.new file to configuration.php.  within the root directory of your WHMCS installation to continue."], 20 => ["Requirement" => "Attachments Directory", "Path" => "/attachments/", "PassingStatus" => is_writable(ROOTDIR . "/attachments/"), "Help" => is_dir(ROOTDIR . "/attachments/") ? "The attachments directory is not writeable." : "The system could not find the attachments directory. Create it and try again."], 30 => ["Requirement" => "Downloads Directory", "Path" => "/downloads/", "PassingStatus" => is_writable(ROOTDIR . "/downloads/"), "Help" => is_dir(ROOTDIR . "/downloads/") ? "The downloads directory is not writeable." : "The system could not find the downloads directory. Create it and try again."], 40 => ["Requirement" => "Templates Compile Directory", "Path" => "/templates_c/", "PassingStatus" => is_writable(ROOTDIR . "/templates_c/"), "Help" => is_dir(ROOTDIR . "/templates_c/") ? "The templates_c directory is not writeable." : "The system could not find the templates_c directory. Create it and try again."]];
echo "<!DOCTYPE html>\n<html>\n    <head>\n        <title>WHMCS Install/Upgrade Process</title>\n        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">\n        <link href=\"../assets/css/bootstrap.min.css\" rel=\"stylesheet\">\n        <link href=\"../assets/css/fontawesome-all.min.css\" rel=\"stylesheet\">\n        <link href=\"../assets/css/install.css\" rel=\"stylesheet\">\n        <script type=\"text/javascript\" src=\"../assets/js/jquery.min.js\"></script>\n        <script type=\"text/javascript\" src=\"../assets/js/bootstrap.min.js\"></script>\n\n        <script>\n            function hideLoading() {\n                jQuery(\"#submitButton\").removeAttr(\"disabled\");\n                jQuery(\".loading\").fadeOut();\n            }\n            function showLoading() {\n                jQuery(\"#submitButton\").attr(\"disabled\",\"disabled\");\n                jQuery(\".loading\").fadeIn();\n            }\n            function setUpgradeInProgress() {\n                jQuery(\"#btnConfirmBackup\").attr(\"disabled\", \"disabled\");\n                jQuery(\"#btnCloseUpgradeBackupModal\").attr(\"disabled\", \"disabled\");\n                jQuery(\"#btnConfirmBackup\").html('<i class=\"fas fa-spinner fa-spin\"></i> Upgrade in progress... This may take a few moments... ');\n                jQuery(\"#upgradeDurationMsg\").hide().removeClass('hidden').slideDown();\n            }\n        </script>\n\n    </head>\n\n    <body onunload=\"\">\n        <div class=\"wrapper\">\n            <div class=\"version\">V";
echo $whmcsInstaller->getLatestMajorMinorVersion();
echo "</div>\n            <div style=\"margin:30px;\">\n                <a href=\"https://www.whmcs.com/\" target=\"_blank\"><img src=\"//www.whmcs.com/images/logo.png\" alt=\"WHMCS - The Complete Client Management, Billing, and Support Solution\" border=\"0\" /></a>\n            </div>\n            ";
if ($step == "4") {
    if (!$licenseKey) {
        $validationError = "A license key is required. If you don't have one, please visit <a href=\"https://www.whmcs.com/order\">www.whmcs.com</a> to purchase one.";
    } else {
        if (!$databaseHost) {
            $validationError = "A database hostname is required.";
        } else {
            if (!$databaseUsername) {
                $validationError = "A database username is required.";
            } else {
                if (!$databasePassword) {
                    $validationError = "A database password is required.";
                } else {
                    if (!$databaseName) {
                        $validationError = "A database name is required.";
                    } else {
                        $tmpConfig = new WHMCS\Config\Application();
                        $tmpConfig->setDatabaseCharset("utf8")->setDatabaseHost($databaseHost)->setDatabaseName($databaseName)->setDatabaseUsername($databaseUsername)->setDatabasePassword($databasePassword);
                        if ($databasePort) {
                            $tmpConfig->setDatabasePort($databasePort);
                        }
                        try {
                            $db = new WHMCS\Database($tmpConfig);
                        } catch (Exception $e) {
                            $validationError = "The system could not connect to the database server: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
    if ($validationError) {
        $step = "3";
    }
}
if ($step == "5") {
    $adminUsernameValidationError = false;
    try {
        (new WHMCS\User\Admin())->validateUsername($username);
    } catch (Exception $e) {
        $adminUsernameValidationError = $e->getMessage();
    }
    if (!$firstName) {
        $validationError = "A first name is required.";
    } else {
        if (!$email) {
            $validationError = "An email address is required.";
        } else {
            if ($adminUsernameValidationError) {
                $validationError = $adminUsernameValidationError;
            } else {
                if (!$password) {
                    $validationError = "A password is required.";
                } else {
                    if (strlen(WHMCS\Input\Sanitize::decode($password)) < 5) {
                        $validationError = "The password must be at least five characters long.";
                    } else {
                        if (!$confirmPassword) {
                            $validationError = "You must confirm your password.";
                        } else {
                            if ($password != $confirmPassword) {
                                $validationError = "The passwords you entered do not match.";
                            }
                        }
                    }
                }
            }
        }
    }
    if ($validationError) {
        $step = "4";
    }
}
if ($step == "") {
    echo "<h1>End User License Agreement</h1>";
    if (isset($_REQUEST["disagree"]) && $_REQUEST["disagree"]) {
        echo "<div class=\"alert alert-danger text-center\" role=\"alert\">\n                        You cannot continue with the installation unless you agree to the End User License Agreement.\n                    </div>";
    }
    echo "                <p>Please review the license terms before installing or upgrading WHMCS. By installing, copying, or otherwise using the software, you are agreeing to be bound by the terms of the EULA.</p>\n                <p align=\"center\">\n                        <textarea class=\"form-control\" style=\"font-family: Tahoma, sans-serif; font-size: 12px; color: #666666;\" rows=\"25\" readonly>\n                            ";
    $eulaText = (new WHMCS\Utility\Eula())->getEulaText();
    if ($eulaText) {
        echo $eulaText;
        echo "                        </textarea>\n                </p>\n\n                <p align=\"center\">\n                    <a href=\"install.php?step=2\" class=\"btn btn-success btn-lg\" id=\"btnEulaAgree\">I AGREE</a>\n                    <a href=\"install.php?disagree=1\" class=\"btn btn-default btn-lg\" id=\"btnEulaDisagree\">I DISAGREE</a>\n\n            ";
    } else {
        echo "EULA.txt is missing. The system cannot continue.</textarea>";
        exit;
    }
} else {
    if ($step == "2") {
        echo "\n";
        $isUpgrade = false;
        $isInstallation = false;
        if ($whmcsInstaller->isInstalled()) {
            if ($doUpgradeFromInstall && $step == 2) {
                $newCcHash = $_REQUEST["upgrade-cc-hash"];
                $output = getConfigurationFileContentWithNewCcHash($newCcHash);
                $fp = fopen($configFilename, "w");
                fwrite($fp, $output);
                fclose($fp);
            }
            Log::info("Previous installation detected.");
            $installedVersion = $whmcsInstaller->getVersion()->getCasual();
            echo "<h1>Upgrade Your Installation</h1>";
            if ($whmcsInstaller->isUpToDate()) {
                Log::debug("The installation is already up-to-date.");
                echo "<p>We have detected that you are already running WHMCS version " . $installedVersion . ".</p>" . "<p>This installation script can only upgrade as far as " . $installedVersion . ". There is no update to perform.</p>" . "<br /><p><small><em>Do you want to perform a new installation?</em> To do this, you must first drop all existing tables from your WHMCS database. Then, try running the installation script again. (Warning: You will lose all existing data if you do this.)</small></p>";
            } else {
                if ($whmcsInstaller->getInstalledVersionNumeric() < 320) {
                    Log::debug("Installation is too old to upgrade.");
                    echo "<p>We have detected that you are currently running WHMCS version " . $installedVersion . ".</p>" . "<p>This version of WHMCS is too old to upgrade automatically.</p>" . "<p>To update, we recommend purchasing our professional upgrade service at <a href=\"https://www.whmcs.com/services/\">www.whmcs.com/services/</a> to have it manually updated.</p>";
                } else {
                    Log::debug(sprintf("An upgrade from %s to %s will be attempted.", $whmcsInstaller->getVersion()->getCanonical(), $whmcsInstaller->getLatestVersion()->getCanonical()));
                    echo "<p>We have detected that you are currently running WHMCS version " . $installedVersion . ".</p>" . "<p>This update process will upgrade your installation to " . $whmcsInstaller->getLatestVersion()->getCasual() . ".</p>";
                    $isUpgrade = true;
                }
            }
        } else {
            Log::info("A previous installation was not detected; a new installation will be attempted.");
            echo "<h1>New Installation</h1><p>No existing installation was detected.</p><p class=\"text-muted\">Do you want to perform an upgrade? Do not continue. Instead, <a href=\"https://docs.whmcs.com/Upgrading_New_Installation_Prompt\" target=\"_blank\">click here for help</a>.</p>";
            $isInstallation = true;
        }
        if ($isInstallation || $isUpgrade) {
            if (extension_loaded("PDO") && extension_loaded("pdo_mysql") && $dbErrorMessage == "") {
                $systemRequirements[20] = ["Requirement" => "MySQL® Version", "CurrentValue" => $isUpgrade ? DI::make("db")->getSqlVersion() : "Version Unavailable", "RequiredValue" => "5.2.3", "PassingStatus" => true, "Help" => "WHMCS uses MySQL as its database engine. Currently, the MySQL extension is either missing or disabled."];
                if ($isUpgrade) {
                    $systemRequirements[30] = ["Requirement" => "MySQL® Strict Mode", "RequiredValue" => "Off", "FailureValue" => "On", "PassingStatus" => !DI::make("db")->isSqlStrictMode(), "Help" => "MySQL Strict Mode must be disabled."];
                }
            }
            $meetsRequirements = true;
            $systemRequirementsOutput = "";
            $filePermissionRequirementsOutput = "";
            $systemRequirementsFailures = "";
            $filePermissionRequirementsFailures = "";
            ksort($systemRequirements);
            foreach ($systemRequirements as $i => $values) {
                $requirementOutput = "<tr>\n            <td>" . $values["Requirement"] . "</td>\n            <td>" . (isset($values["CurrentValue"]) ? $values["CurrentValue"] : ($values["PassingStatus"] ? $values["RequiredValue"] : $values["FailureValue"])) . "</td>\n            <td>" . $values["RequiredValue"] . "</td>\n            <td>" . ($values["PassingStatus"] ? "<i class=\"far fa-check-square icon-success\"></i>" : "<button type=\"button\" class=\"btn btn-info btn-xs help-icon\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"" . $values["Help"] . "\"><i class=\"fas fa-question\"></i></button>") . "</td>\n        </tr>";
                $systemRequirementsOutput .= $requirementOutput;
                if (!$values["PassingStatus"]) {
                    $systemRequirementsFailures .= $requirementOutput;
                    $meetsRequirements = false;
                }
            }
            $systemRecommendationsOutput = "";
            if ($isInstallation) {
                ksort($fileWritePermissions);
                foreach ($fileWritePermissions as $i => $values) {
                    $filePermissionOutput = "<tr>\n                <td>" . $values["Requirement"] . "</td>\n                <td>" . $values["Path"] . "</td>\n                <td>" . ($values["PassingStatus"] ? "<i class=\"far fa-check-square icon-success\"></i>" : "<button type=\"button\" class=\"btn btn-info btn-xs help-icon\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"" . $values["Help"] . "\"><i class=\"fas fa-question\"></i></button>") . "</td>\n            </tr>";
                    $filePermissionRequirementsOutput .= $filePermissionOutput;
                    if (!$values["PassingStatus"]) {
                        $filePermissionRequirementsFailures .= $filePermissionOutput;
                        $meetsRequirements = false;
                    }
                }
                ksort($systemRecommendations);
                foreach ($systemRecommendations as $i => $systemRecommendation) {
                    if ($systemRecommendation["Condition"]) {
                        $systemRecommendationsOutput .= "<tr><td>" . $systemRecommendation["Recommendation"] . "</td>" . "<td><button type=\"button\" class=\"btn btn-info btn-xs help-icon\" data-toggle=\"tooltip\" data-placement=\"right\" title=\"" . $systemRecommendation["Help"] . "\"><i class=\"fas fa-question\"></i></button></td></tr>";
                    }
                }
            }
            if ($meetsRequirements) {
                if ($systemRecommendationsOutput) {
                    $systemRecommendationsOutput = "<table class=\"table table-striped requirements\">\n            <tr>\n                <th>Information</th>\n                <th></th>\n            </tr>" . $systemRecommendationsOutput . "\n            </table>\n            <script>\njQuery(function () {\n    jQuery('[data-toggle=\"tooltip\"]').tooltip()\n})\n</script>";
                }
                echo "<br /><div class=\"alert alert-success text-center\" role=\"alert\"  id=\"requirementsSummary\"><strong><i class=\"fas fa-check-circle\"></i> System Requirements Check Passed</strong><div style=\"font-size:0.9em;padding:6px;\">Your system meets the requirements to run this version of WHMCS.</div><a href=\"#\" id=\"btnDetailedCheckResults\" class=\"btn btn-default btn-sm\" data-toggle=\"modal\" data-target=\"#requirementsFullResults\">View detailed check results.</a></div>\n" . $systemRecommendationsOutput . "\n<div class=\"modal fade\" id=\"requirementsFullResults\">\n  <div class=\"modal-dialog\">\n    <div class=\"modal-content\">\n      <div class=\"modal-header\">\n        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>\n        <h4 class=\"modal-title\">System Requirements Check Results</h4>\n      </div>\n      <div class=\"modal-body\">\n\n        <table class=\"table table-striped requirements\">\n            <tr>\n                <th>Requirement</th>\n                <th>Your Value</th>\n                <th>Required Value</th>\n                <th></th>\n            </tr>" . $systemRequirementsOutput . "\n        </table>";
                if ($filePermissionRequirementsOutput) {
                    echo "<table class=\"table table-striped requirements\">\n            <tr>\n                <th>Read/Write Permissions</th>\n                <th>File or Directory Path</th>\n                <th></th>\n            </tr>" . $filePermissionRequirementsOutput . "\n        </table>";
                }
                echo "\n      </div>\n    </div>\n  </div>\n</div>\n\n";
            } else {
                echo "<br /><div class=\"alert alert-danger text-center\" role=\"alert\" id=\"requirementsSummary\"><strong><i class=\"fas fa-exclamation-triangle\"></i> System Requirements Check Failed</strong><div style=\"font-size:0.9em;padding:6px;\">Your system <strong>does not</strong> meet the requirements to run this version of WHMCS.<br />You must resolve the issues below before you can continue with installation.</div></div>\n<script>\njQuery(function () {\n    jQuery('[data-toggle=\"tooltip\"]').tooltip()\n})\n</script>";
                if ($systemRequirementsFailures) {
                    echo "<table class=\"table table-striped requirements\">\n    <tr>\n        <th>Requirement</th>\n        <th>Your Value</th>\n        <th>Required Value</th>\n        <th>Help</th>\n    </tr>\n    " . $systemRequirementsFailures . "\n</table>";
                }
                if ($filePermissionRequirementsFailures) {
                    echo "<table class=\"table table-striped requirements\">\n    <tr>\n        <th>Read/Write Permissions</th>\n        <th>File/Directory Path</th>\n        <th>Help</th>\n    </tr>\n    " . $filePermissionRequirementsFailures . "\n</table>";
                }
                if ($systemRecommendationsOutput) {
                    echo "<table class=\"table table-striped requirements\">\n            <tr>\n                <th>Information</th>\n                <th></th>\n            </tr>" . $systemRecommendationsOutput . "\n            </table>";
                }
                echo "<p>Please address the issues above. Then, click the button below to check the requirements again and continue.</p><p align=\"center\"><a href=\"?step=2\" id=\"btnRecheckRequirements\" class=\"btn btn-success\">Recheck Requirements</a></p>";
                $isInstallation = false;
                $isUpgrade = false;
            }
        }
        if ($isUpgrade) {
            echo "<h2>Ready to Begin?</h2><p>The upgrade process may require additional time depending on the size of your database. Do not stop it or navigate away from the page while it is running.</p><p>If a problem occurs during the upgrade, the process will halt. In that scenario, we recommend that you restore your backup and contact our support team for assistance.</p>";
        }
        if ($isInstallation) {
            echo "<br /><p align=\"center\"><a href=\"?step=3\" id=\"btnBeginInstallation\" class=\"btn btn-danger btn-lg\">Begin Installation</a></p>";
        }
        if ($isUpgrade) {
            echo "<br />";
            if (is_writable(__DIR__ . DIRECTORY_SEPARATOR . "log") && (is_writable(__DIR__ . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR . "installer.log") || !file_exists(__DIR__ . DIRECTORY_SEPARATOR . "log" . DIRECTORY_SEPARATOR . "installer.log"))) {
                echo "<div class=\"alert alert-info text-center\" role=\"alert\">\n        You can access a log of events at <em>/install/log/installer.log</em>. Use this log if you encounter problems.\n    </div>";
            } else {
                echo "<div class=\"alert alert-warning text-center\" role=\"alert\">\n        The <em>/install/log/installer.log</em> file is not writeable or cannot be created. If you continue, the PHP Error Log defined in your <em>php.ini</em> configuration will be used.\n\n    </div>";
            }
            echo "<p align=\"center\"><button type=\"button\" id=\"btnUpgradeContinue\" class=\"btn btn-danger btn-lg\" data-toggle=\"modal\" data-target=\"#upgradeBackup\" data-backdrop=\"static\" data-keyboard=\"false\">Continue</button></p>";
        }
        echo "<div class=\"modal fade\" id=\"upgradeBackup\">\n  <div class=\"modal-dialog\">\n    <div class=\"modal-content\">\n      <div class=\"modal-header\">\n        <button type=\"button\" class=\"close\" id=\"btnCloseUpgradeBackupModal\" data-dismiss=\"modal\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>\n        <h4 class=\"modal-title\"><i class=\"fas fa-exclamation-triangle\"></i><br />Backup Confirmation</h4>\n      </div>\n      <div class=\"modal-body\">\n\n<div class=\"alert alert-danger\">\n<p>Before proceeding, please ensure that you have a recent database backup that you could restore if the upgrade fails for any reason.</p>\n</div>\n<p>If the upgrade fails or is interrupted, a backup is<br />essential to being able to get back online quickly.</p>\n<p>If you don't have a backup, please generate one now.</p>\n\n      </div>\n      <div class=\"modal-footer\">\n        <form method=\"post\" action=\"install.php?step=upgrade\" onsubmit=\"setUpgradeInProgress()\">\n            <input type=\"hidden\" name=\"confirmBackup\" value=\"1\" />\n            <button type=\"submit\" id=\"btnConfirmBackup\" class=\"btn btn-info btn-lg\">\n                I have a backup. Start the upgrade.\n            </button>\n        </form>\n        <div class=\"upgrade-duration-msg text-muted hidden\" id=\"upgradeDurationMsg\">\n            Depending on the size of your database, upgrades can take several minutes to complete.\n        </div>\n      </div>\n    </div>\n  </div>\n</div>";
    } else {
        if ($step == "3") {
            if ($validationError) {
                echo "<div class=\"alert alert-danger text-center\" role=\"alert\">\n                        " . $validationError . "\n                    </div>";
            }
            echo "\n                <form method=\"post\" action=\"install.php?step=4\" onsubmit=\"showLoading()\">\n                    <h1>License Key</h1>\n\n                    <p>You can find your license key in our <a href=\"https://www.whmcs.com/members/clientarea.php\" target=\"_blank\">Members Area</a>. If you obtained a license from a reseller, they should have already provided a license key to you.</p>\n\n                    <table class=\"table-padded\">\n                        <tr>\n                            <td width=\"200\">\n                                <label for=\"licenseKey\">License Key:</label>\n                            </td>\n                            <td width=\"350\">\n                                <input type=\"text\" name=\"licenseKey\" id=\"licenseKey\" value=\"";
            echo htmlspecialchars($licenseKey);
            echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                    </table>\n                    <br>\n\n                    <h1>Database Connection Details</h1>\n                    <p>WHMCS requires a MySQL® database. If you have not already created one, please create one now.</p>\n\n                    <table class=\"table-padded\">\n                        <tr>\n                            <td width=\"200\">\n                                <label for=\"databaseHost\">Database Host:</label>\n                            </td>\n                            <td width=\"200\">\n                                <input type=\"text\" name=\"databaseHost\" id=\"databaseHost\" size=\"20\" value=\"";
            echo $databaseHost ? htmlspecialchars($databaseHost) : "localhost";
            echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"databasePort\">Database Port:</label>\n                            </td>\n                            <td>\n                                <input type=\"text\" name=\"databasePort\" id=\"databasePort\" size=\"15\" value=\"";
            echo $databasePort ? htmlspecialchars($databasePort) : "";
            echo "\" class=\"form-control\" placeholder=\"3306\">\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"databaseUsername\">Database Username:</label>\n                            </td>\n                            <td>\n                                <input type=\"text\" name=\"databaseUsername\" id=\"databaseUsername\" size=\"20\" value=\"";
            echo htmlspecialchars($databaseUsername);
            echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"databasePassword\">Database Password:</label>\n                            </td>\n                            <td>\n                                <input type=\"password\" name=\"databasePassword\" id=\"databasePassword\" size=\"20\" value=\"";
            echo htmlspecialchars($databasePassword);
            echo "\" class=\"form-control\" autocomplete=\"off\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"databaseName\">Database Name:</label>\n                            </td>\n                            <td>\n                                <input type=\"text\" name=\"databaseName\" id=\"databaseName\" size=\"20\" value=\"";
            echo htmlspecialchars($databaseName);
            echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                    </table>\n\n                    <br />\n\n                    <p align=\"center\">\n                        <input type=\"submit\" value=\"Continue &raquo;\" class=\"btn btn-lg btn-primary\" id=\"submitButton\" />\n                    </p>\n\n                    <div class=\"loading\">Initialising Database... This may take a few moments... <br>\n                        <img src=\"../assets/img/loading.gif\">\n                    </div>\n                </form>\n                <script>\n                    jQuery(document).ready(function(){\n                        hideLoading();\n                    });\n                </script>\n            ";
        } else {
            if ($step == "4") {
                $goToPreviousStep = "<script>jQuery(\"#previousStep\").click(\n                    function(e){\n                        e.preventDefault();\n                        window.history.go(-1)\n                    });</script>";
                if ($licenseKey) {
                    $length = 64;
                    $seeds = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                    $encryptionHash = NULL;
                    $seeds_count = strlen($seeds) - 1;
                    for ($i = 0; $i < $length; $i++) {
                        $encryptionHash .= $seeds[rand(0, $seeds_count)];
                    }
                    $output = "<?php\n\$license = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($licenseKey) . "';\n\$db_host = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($databaseHost) . "';\n\$db_port = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($databasePort) . "';\n\$db_username = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($databaseUsername) . "';\n\$db_password = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($databasePassword) . "';\n\$db_name = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($databaseName) . "';\n\$cc_encryption_hash = '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($encryptionHash) . "';\n\$templates_compiledir = 'templates_c';\n\$mysql_charset = 'utf8';";
                    $configurationFile = ROOTDIR . DIRECTORY_SEPARATOR . "configuration.php";
                    $fp = fopen($configurationFile, "w");
                    if (fwrite($fp, $output) !== false) {
                        fclose($fp);
                        Log::info("The new configuration file has been written.");
                        if (function_exists("opcache_invalidate")) {
                            opcache_invalidate($configurationFile);
                        }
                        $previousDatabaseFound = false;
                        $databaseExists = false;
                        $databaseConnectFailed = false;
                        $strictSqlMode = false;
                        try {
                            $whmcsInstaller->checkIfInstalled(true);
                            if ($whmcsInstaller->isInstalled()) {
                                $previousDatabaseFound = true;
                                $databaseExists = true;
                            } else {
                                if ($whmcsInstaller->getDatabase()) {
                                    $databaseExists = true;
                                } else {
                                    $databaseConnectFailed = true;
                                }
                            }
                            $strictSqlMode = DI::make("db")->isSqlStrictMode();
                        } catch (Exception $e) {
                            $databaseConnectFailed = true;
                        }
                        if ($databaseConnectFailed) {
                            Log::error("Failed to connect to database '" . $databaseName . "'");
                            echo "                        <h1>New Installation</h1>\n                        <div class=\"alert alert-danger text-center\" role=\"alert\">\n                            <strong>\n                                <i class=\"fas fa-exclamation-triangle\"></i>\n                                Oops! There's a problem\n                            </strong>\n                        </div>\n                        <p><strong>Could not connect to the database</strong></p>\n                        <p>Check the database connection details you provided and ensure that the MySQL® user has access to that database name.</p>\n                        <br />\n                        <p><a id=\"previousStep\" href=\"#\" class=\"btn btn-default\">&laquo; Go back and try again.</a></p>\n                        ";
                            echo $goToPreviousStep;
                            exit;
                        }
                        if ($strictSqlMode) {
                            Log::error("SQL strict mode detected");
                            echo "                        <h1>New Installation</h1>\n                        <div class=\"alert alert-danger text-center\" role=\"alert\">\n                            <strong>\n                                <i class=\"fas fa-exclamation-triangle\"></i>\n                                Oops! There's a problem.\n                            </strong>\n                        </div>\n                        <p><strong>Strict SQL Mode Detected</strong></p>\n                        <p>Disable strict SQL mode before continuing.</p>\n                        <p>See <a href=\"https://docs.whmcs.com/Database_Setup#Strict_SQL_Mode\" target=\"_blank\">our database setup documentation</a> for more information.</p>\n                        <br />\n                        <p><a id=\"previousStep\" href=\"#\" class=\"btn btn-default\">&laquo; Go back and try again.</a></p>\n                        ";
                            echo $goToPreviousStep;
                            exit;
                        }
                        if ($databaseExists && $previousDatabaseFound) {
                            Log::error("A previous WHMCS database was found after the configuration file was created.");
                            echo "                        <h1>New Installation</h1>\n                        <div class=\"alert alert-danger text-center\" role=\"alert\">\n                            <strong>\n                                <i class=\"fas fa-exclamation-triangle\"></i>\n                                Warning! Existing Installation Detected\n                            </strong>\n                        </div>\n                        <p><strong>Existing WHMCS Database Found</strong></p>\n                        <p>The provided database details are for a pre-existing WHMCS database.</p>\n                        <p>New installations require a database that does not contain an existing WHMCS installation.</p>\n                        <p>For a new installation, either create a new database or drop the existing WHMCS tables and data from the provided database. Then, try again.</p>\n                        <p>To upgrade the existing database, enter your Credit Card Encryption Hash below.</p>\n                        <form action=\"install.php\" method=\"post\">\n                            <input type=\"hidden\" name=\"step\" value=\"2\" />\n                            <input type=\"hidden\" name=\"do-upgrade-from-install\" value=\"1\" />\n                            <p>\n                                <label for=\"upgradeCcHash\">Credit Card Encryption Hash:</label><br>\n                                <input type=\"password\" id=\"upgradeCcHash\" name=\"upgrade-cc-hash\" class=\"form-control\" required=\"required\" />\n                            </p>\n                            <br />\n                            <p><a id=\"previousStep\" href=\"#\" class=\"btn btn-default\">&laquo; Go back and try again.</a> <button id=\"doUpgrade\" type=\"submit\" class=\"btn btn-danger pull-right\">Continue with Upgrade</button></p>\n                        </form>\n                        ";
                            echo $goToPreviousStep;
                            exit;
                        }
                        if ($databaseExists && !$previousDatabaseFound) {
                            Log::info("Applying base SQL schema");
                            $whmcsInstaller->seedDatabase();
                            $whmcsInstaller->setSystemUrl()->persistSystemUrl();
                        }
                    } else {
                        echo "                        <h1>New Installation</h1>\n                        <div class=\"alert alert-danger text-center\" role=\"alert\">\n                            <strong>\n                                <i class=\"fas fa-exclamation-triangle\"></i>\n                                Oops! There's a problem\n                            </strong>\n                        </div>\n                        <p><strong>Could not write configuration file</strong></p>\n                        <p>Ensure that the system can write to the /configuration.php file.</p>\n                        <br />\n                        <p><a id=\"previousStep\" href=\"#\" class=\"btn btn-default\">&laquo; Go back and try again.</a></p>\n                        ";
                        echo $goToPreviousStep;
                        exit;
                    }
                }
                if ($validationError) {
                    echo "<div class=\"alert alert-danger text-center\" role=\"alert\">\n                        " . $validationError . "\n                    </div>";
                }
                echo "\n                <h1>Set Up Administrator Account</h1>\n\n                <form method=\"post\" action=\"install.php?step=5\" onsubmit=\"showLoading()\">\n                    <p>You now need to set up your administrator account.</p>\n\n                    <table class=\"table-padded\">\n                        <tr>\n                            <td width=\"200\">\n                                <label for=\"firstName\">First Name:</label>\n                            </td>\n                            <td width=\"350\">\n                                <input type=\"text\" name=\"firstName\" id=\"firstName\" value=\"";
                echo $firstName;
                echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"lastName\">Last Name:</label>\n                            </td>\n                            <td>\n                                <input type=\"text\" name=\"lastName\" id=\"lastName\" value=\"";
                echo $lastName;
                echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"email\">Email:</label>\n                            </td>\n                            <td>\n                                <input type=\"email\" name=\"email\" id=\"email\" value=\"";
                echo $email;
                echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"username\">Username:</label>\n                            </td>\n                            <td>\n                                <input type=\"text\" name=\"username\" id=\"username\" autocomplete=\"off\" value=\"";
                echo $username;
                echo "\" class=\"form-control\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"password\">Password:</label>\n                            </td>\n                            <td>\n                                <input type=\"password\" name=\"password\" id=\"password\" value=\"";
                echo $password;
                echo "\" class=\"form-control\" autocomplete=\"off\" required>\n                            </td>\n                        </tr>\n                        <tr>\n                            <td>\n                                <label for=\"confirmPassword\">Confirm Password:</label>\n                            </td>\n                            <td>\n                                <input type=\"password\" name=\"confirmPassword\" id=\"confirmPassword\" value=\"";
                echo $confirmPassword;
                echo "\" class=\"form-control\" autocomplete=\"off\" required>\n                            </td>\n                        </tr>\n                    </table>\n\n                    <br />\n\n                    <p align=\"center\">\n                        <input type=\"submit\" value=\"Complete Setup &raquo;\" class=\"btn btn-primary btn-lg\" id=\"submitButton\" />\n                    </p>\n\n                    <div class=\"loading\">Setting Up System for First Use... This may take a few moments... <br>\n                        <img src=\"../assets/img/loading.gif\">\n                    </div>\n                </form>\n            ";
            } else {
                if ($step == "5") {
                    include ROOTDIR . "/configuration.php";
                    DI::make("db");
                    Log::info("Creating initial admin account");
                    echo "<h1>New Installation</h1>";
                    $errorMsg = "";
                    try {
                        $whmcsInstaller->createInitialAdminUser($_REQUEST["username"], $_REQUEST["firstName"], $_REQUEST["lastName"], $password, $_REQUEST["email"]);
                        $whmcsInstaller->performNonSeedIncrementalChange();
                        $whmcsInstaller->setReleaseTierPin();
                        $admin = WHMCS\User\Admin::query()->orderBy("id")->first();
                        (new WHMCS\Utility\Eula())->markAsAccepted($admin);
                    } catch (Exception $e) {
                        $errorMsg = $e->getMessage();
                    }
                    if ($errorMsg) {
                        Log::error("Installation process terminated due to error.");
                        echo "\n                    <div class=\"alert alert-danger text-center\" role=\"alert\">\n                        <strong>\n                            <i class=\"fas fa-exclamation-triangle\"></i>\n                            Installation Failed\n                        </strong>\n                    </div>\n\n                    <p>A problem was encountered during the installation process.</p>\n\n                    <p>The installation script returned the following error message:</p>\n\n                    <div class=\"well\">\n                        ";
                        echo $errorMsg;
                        echo "                    </div>\n\n                    <h2>How do I get help?</h2>\n\n                    <p>We want to make sure you can start using our product as soon as possible.</p>\n                    <p><a href=\"https://www.whmcs.com/support/\" target=\"_blank\">Please open a ticket with our support team</a> and include a copy of your installation log file from <em>/install/log/</em>. This will help them diagnose what caused the failure and what needs to be done before attempting the installation process again.</p>\n\n                    ";
                    } else {
                        Log::info("Installation process completed.");
                        echo "\n                    <div class=\"alert alert-success text-center\" role=\"alert\">\n                        <strong>\n                            <i class=\"fas fa-check-circle\"></i>\n                            Installation Completed Successfully!\n                        </strong>\n                    </div>\n\n                    <h2>Next Steps</h2>\n\n                    <p><strong>1. Delete the Install Folder</strong></p>\n                    <p>Delete the <em>/install/</em> directory from your server.</p>\n\n                    <p><strong>2. Secure the Writable Directories</strong></p>\n                    <p>We recommend moving all writeable directories to a non-public directory above your web root to prevent web-based access. For details on how to do this and many other security hardening tips, see <a href=\"https://docs.whmcs.com/Further_Security_Steps\" target=\"_blank\">Further Security Steps</a>.</p>\n\n                    <p><strong>3. Set Up the Daily Cron Job</strong></p>\n                    <p>Set up a cron job in your control panel to run the following command every five minutes or as frequently as your hosting provider allows:<br /><br />\n                    <input type=\"text\" value=\"";
                        echo WHMCS\Environment\Php::getPreferredCliBinary();
                        echo " -q ";
                        echo ROOTDIR;
                        echo "/crons/cron.php\" class=\"form-control\" readonly>\n                    </p>\n\n                    <p><strong>4. Configure WHMCS</strong></p>\n                    <p>Next, you can configure your WHMCS installation.</p>\n\n                    <div class=\"alert alert-info text-center\" role=\"alert\">\n                        For many <strong>helpful resources and guides</strong> on setting up and using your new WHMCS system, find our comprehensive online documentation at <a href=\"https://docs.whmcs.com/\" target=\"_blank\">https://docs.whmcs.com/</a>. You can access the documentation at any time in the Admin Area by going to <strong><i class=\"far fa-question-circle\"></i> &gt; Documentation</strong> or clicking <strong>Documentation</strong> in the lower right-side corner.\n                    </div>\n\n                    <br>\n\n                    <p align=\"center\">\n                        <a href=\"../admin/\" id=\"btnGoToAdminArea\" class=\"btn btn-default\">Go to the Admin Area Now &raquo;</a>\n                    </p>\n\n                    <br>\n                    ";
                    }
                    echo "<h2>Thank you for choosing WHMCS!</h2>";
                } else {
                    if ($step == "upgrade") {
                        if (!isset($_REQUEST["confirmBackup"])) {
                            echo "<h1>Did you perform a backup?</h1><p>You must create a backup before you upgrade. Please go back and try again.";
                        } else {
                            Log::info("Applying incremental updates to existing installation.");
                            echo "<h1>Upgrade Your Installation</h1>";
                            $errorMsg = "";
                            $currentVersion = $whmcsInstaller->getVersion()->getCanonical();
                            $updateVersion = $whmcsInstaller->getLatestVersion()->getCanonical();
                            logActivity(sprintf("An upgrade from %s to %s will be attempted.", $currentVersion, $updateVersion));
                            try {
                                $whmcsInstaller->runUpgrades();
                                $whmcsInstaller->setReleaseTierPin();
                                $admin = WHMCS\User\Admin::query()->orderBy("id")->first();
                                (new WHMCS\Utility\Eula())->markAsAccepted($admin);
                                logActivity(sprintf("Update from %s to %s completed successfully.", $currentVersion, $updateVersion));
                            } catch (Exception $e) {
                                $errorMsg = $e->getMessage();
                                logActivity("Update Failed: " . $errorMsg);
                            }
                            try {
                                $whmcsInstaller->clearCompiledTemplates();
                            } catch (Exception $e) {
                                logActivity("Error cleaning template cache during upgrade: " . $e->getMessage());
                            }
                            if ($errorMsg) {
                                Log::error("Upgrade process terminated due to error.");
                                echo "\n                        <div class=\"alert alert-danger text-center\" role=\"alert\">\n                            <strong>\n                                <i class=\"fas fa-exclamation-triangle\"></i>\n                                Upgrade Failed\n                            </strong>\n                        </div>\n\n                        <p>A problem was encountered while attempting to apply the database schema updates.</p>\n\n                        <p>The update process returned the following error message:</p>\n\n                        <div class=\"well\">\n                            ";
                                echo $errorMsg;
                                echo "                        </div>\n\n                        <h2>How do I get help?</h2>\n\n                        <p>First, to get your installation back online as soon as possible, we recommend that you restore the backup you took before you began the upgrade process.</p>\n                        <p>Then, <a href=\"https://www.whmcs.com/support/\" target=\"_blank\">open a ticket with our support team</a> and include a copy of your upgrade log file from <em>/install/log/</em>. This will help them diagnose the cause of the failure and the actions to perform before attempting the upgrade process again.</p>\n\n                        ";
                            } else {
                                Log::info("Upgrade process completed.");
                                echo "\n                        <div class=\"alert alert-success text-center\" role=\"alert\">\n                            <strong>\n                                <i class=\"fas fa-check-circle\"></i>\n                                Upgrade Completed Successfully!\n                            </strong>\n                        </div>\n\n                        <p>You are now running WHMCS version ";
                                echo $whmcsInstaller->getLatestVersion()->getCasual();
                                echo ".</p>\n                        <p>We strongly recommend that you read the <a href=\"https://docs.whmcs.com/Release_Notes\" target=\"_blank\">Release Notes</a> for this version to ensure that you are aware of any changes that require your attention.</p>\n                        <p>You should now delete the <em>/install/</em> directory from your server.</p>\n\n                        <p align=\"center\">\n                            <a href=\"../";
                                echo $whmcsInstaller->getAdminPath();
                                echo "/\" id=\"btnGoToAdminArea\" class=\"btn btn-default\">Go to the Admin Area Now &raquo;</a>\n                        </p>\n\n                        <br />\n\n                        <h2>Thank you for choosing WHMCS!</h2>\n\n                        ";
                            }
                        }
                    }
                }
            }
        }
    }
}
echo "\n            <br>\n            <br>\n\n            <div align=\"center\"><small>Copyright &copy; WHMCS 2005-";
echo date("Y");
echo "<br>\n                <a href=\"https://www.whmcs.com/\" target=\"_blank\">www.whmcs.com</a></small>\n            </div>\n        </div>\n    </body>\n</html>\n";

<?php

function mailchimp_config()
{
    return ["name" => "MailChimp", "description" => "Integrates with the MailChimp email service for newsletters and email marketing automation.", "author" => "WHMCS", "language" => "english", "version" => "1.0", "fields" => []];
}

function mailchimp_activate()
{
}

function mailchimp_deactivate()
{
    $sql = "DROP TABLE IF EXISTS `mod_mailchimp_optins`";
    full_query($sql);
}

function mailchimp_output($vars)
{
    $action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";
    $dispatcher = new WHMCS\Module\Addon\Mailchimp\Dispatcher();
    $response = $dispatcher->dispatch($action, $vars);
    if (is_array($response)) {
        echo json_encode($response);
        exit;
    }
    echo $response;
}

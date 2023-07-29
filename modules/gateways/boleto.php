<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function boleto_config()
{
    $configarray = ["FriendlyName" => ["Type" => "System", "Value" => "Boleto"], "banco" => ["FriendlyName" => "Banco", "Type" => "dropdown", "Options" => "banestes,bb,bradesco,cef,hsbc,itau,nossacaixa,real,unibanco"], "taxa" => ["FriendlyName" => "Taxa", "Type" => "text", "Size" => "10"], "agencia" => ["FriendlyName" => "Agencia", "Type" => "text", "Size" => "20"], "conta" => ["FriendlyName" => "Conta", "Type" => "text", "Size" => "20"], "conta_cedente" => ["FriendlyName" => "Conta Cedente", "Type" => "text", "Size" => "20", "Description" => "ContaCedente do Cliente, sem digito (Somente Números)"], "conta_cedente_dv" => ["FriendlyName" => "Conta Cedente DV", "Type" => "text", "Size" => "20", "Description" => "Digito da ContaCedente do Cliente"], "convenio" => ["FriendlyName" => "Convenio", "Type" => "text", "Size" => "20"], "contrato" => ["FriendlyName" => "Contrato", "Type" => "text", "Size" => "20"]];
    return $configarray;
}

function boleto_link($params)
{
    $code = "<input type=\"button\" value=\"" . $params["langpaynow"] . "\" onClick=\"window.location='" . $params["systemurl"] . "/modules/gateways/boleto/boleto.php?invoiceid=" . $params["invoiceid"] . "'\" />";
    return $code;
}

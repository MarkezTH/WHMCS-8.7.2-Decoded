<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$GATEWAYMODULE["navigatename"] = "navigate";
$GATEWAYMODULE["navigatevisiblename"] = "NaviGate";
$GATEWAYMODULE["navigatetype"] = "CC";
class navigate_class
{
    public $gateway_url = NULL;
    public $field_string = NULL;
    public $fields = [];
    public $gatewayurls = [];
    public $response_string = NULL;
    public $response = [];

    public function seturl($url)
    {
        $this->gateway_url = $url;
    }

    public function add_field($field, $value)
    {
        $this->fields[(string) $field] = urlencode($value);
    }

    public function process()
    {
        foreach ($this->fields as $key => $value) {
            $this->field_string .= $key . "=" . htmlentities($value) . "&";
        }
        $ch = curl_init($gateway_url);
        curl_setopt($ch, CURLOPT_URL, $this->gateway_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, rtrim($this->field_string, "& "));
        $this->response_string = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->response["Response Reason Text"] = curl_error($ch);
            return 3;
        }
        curl_close($ch);
        $temp_values = explode("|", $this->response_string);
        $temp_keys = ["Response Code", "Response Subcode", "Response Reason Code", "Response Reason Text", "Approval Code", "AVS Result Code", "Transaction ID", "Invoice Number", "Description", "Amount", "Method", "Transaction Type", "Customer ID", "Cardholder First Name", "Cardholder Last Name", "Company", "Billing Address", "City", "State", "Zip", "Country", "Phone", "Fax", "Email", "Ship to First Name", "Ship to Last Name", "Ship to Company", "Ship to Address", "Ship to City", "Ship to State", "Ship to Zip", "Ship to Country", "Tax Amount", "Duty Amount", "Freight Amount", "Tax Exempt Flag", "PO Number", "MD5 Hash", "Card Code (CVV2/CVC2/CID) Response Code", "Cardholder Authentication Verification Value (CAVV) Response Code"];
        for ($i = 0; $i <= 27; $i++) {
            array_push($temp_keys, "Reserved Field " . $i);
        }
        for ($i = 0; sizeof($temp_keys) < sizeof($temp_values); $i++) {
            array_push($temp_keys, "Merchant Defined Field " . $i);
        }
        for ($i = 0; $i < sizeof($temp_values); $i++) {
            $this->response[(string) $temp_keys[$i]] = $temp_values[$i];
        }
        return $this->response["Response Code"];
    }

    public function get_response_reason_text()
    {
        return $this->response["Response Reason Text"];
    }

    public function dump_fields()
    {
    }

    public function dump_response()
    {
        foreach ($this->response as $key => $value) {
            if ($value != "") {
                $response .= $key . " => " . $value . "\n";
            }
        }
        return $response;
    }
}

function navigate_activate()
{
    defineGatewayField("navigate", "text", "loginid", "", "Login ID", "20", "Enter your account id used to identify you as a merchant");
    defineGatewayField("navigate", "text", "transkey", "", "Transaction Key", "20", "Enter your secret key used to verify transactions are valid");
}

function navigate_capture($params)
{
    $params["clientdetails"]["firstname"] = preg_replace("/[^(\\x20-\\x7F)]*/", "", $params["clientdetails"]["firstname"]);
    $params["clientdetails"]["lastname"] = preg_replace("/[^(\\x20-\\x7F)]*/", "", $params["clientdetails"]["lastname"]);
    $params["clientdetails"]["address1"] = preg_replace("/[^(\\x20-\\x7F)]*/", "", $params["clientdetails"]["address1"]);
    $params["clientdetails"]["city"] = preg_replace("/[^(\\x20-\\x7F)]*/", "", $params["clientdetails"]["city"]);
    $params["clientdetails"]["state"] = preg_replace("/[^(\\x20-\\x7F)]*/", "", $params["clientdetails"]["state"]);
    $params["clientdetails"]["postcode"] = preg_replace("/[^(\\x20-\\x7F)]*/", "", $params["clientdetails"]["postcode"]);
    $auth = new navigate_class();
    $gateway_url = "https://gateway.merchantplus.com/cgi-bin/PAWebClient.cgi";
    $auth->seturl($gateway_url);
    $auth->add_field("x_login", $params["loginid"]);
    $auth->add_field("x_tran_key", $params["transkey"]);
    $auth->add_field("x_version", "3.1");
    $auth->add_field("x_type", "AUTH_CAPTURE");
    if ($params["testmode"] == "on") {
        $auth->add_field("x_test_request", "TRUE");
    }
    $auth->add_field("x_relay_response", "FALSE");
    $auth->add_field("x_delim_data", "TRUE");
    $auth->add_field("x_delim_char", "|");
    $auth->add_field("x_encap_char", "");
    $auth->add_field("x_invoice_num", $params["invoiceid"]);
    $auth->add_field("x_description", "Invoice #" . $params["invoiceid"]);
    $auth->add_field("x_first_name", foreignChrReplace($params["clientdetails"]["firstname"]));
    $auth->add_field("x_last_name", foreignChrReplace($params["clientdetails"]["lastname"]));
    $auth->add_field("x_address", foreignChrReplace($params["clientdetails"]["address1"]));
    $auth->add_field("x_city", foreignChrReplace($params["clientdetails"]["city"]));
    $auth->add_field("x_state", foreignChrReplace($params["clientdetails"]["state"]));
    $auth->add_field("x_zip", foreignChrReplace($params["clientdetails"]["postcode"]));
    $auth->add_field("x_country", $params["clientdetails"]["country"]);
    $auth->add_field("x_phone", $params["clientdetails"]["phonenumber"]);
    $auth->add_field("x_method", "CC");
    $auth->add_field("x_card_num", $params["cardnum"]);
    $auth->add_field("x_amount", $params["amount"]);
    $auth->add_field("x_exp_date", $params["cardexp"]);
    $auth->add_field("x_card_code", $params["cccvv"]);
    $desc = "Action => Auth_Capture\nClient => " . $params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"] . "\n";
    $auth->process();
    switch ($auth->process()) {
        case 1:
            return ["status" => "success", "transid" => $auth->response["Transaction ID"], "rawdata" => $auth->dump_response()];
            break;
        case 2:
            return ["status" => "declined", "rawdata" => $auth->dump_response()];
            break;
        default:
            return ["status" => "error", "rawdata" => $auth->dump_response()];
    }
}

function navigate_refund($params)
{
    global $CONFIG;
    $auth = new navigate_class();
    $gateway_url = "https://gateway.merchantplus.com/cgi-bin/PAWebClient.cgi";
    $auth->seturl($gateway_url);
    $auth->add_field("x_login", $params["loginid"]);
    $auth->add_field("x_tran_key", $params["transkey"]);
    $auth->add_field("x_version", "3.1");
    $auth->add_field("x_type", "CREDIT");
    if ($params["testmode"] == "on") {
        $auth->add_field("x_test_request", "TRUE");
    }
    $auth->add_field("x_relay_response", "FALSE");
    $auth->add_field("x_delim_data", "TRUE");
    $auth->add_field("x_delim_char", "|");
    $auth->add_field("x_encap_char", "");
    $auth->add_field("x_invoice_num", $params["invoiceid"]);
    $auth->add_field("x_description", $CONFIG["CompanyName"] . " Invoice #" . $params["invoiceid"]);
    $auth->add_field("x_first_name", $params["clientdetails"]["firstname"]);
    $auth->add_field("x_last_name", $params["clientdetails"]["lastname"]);
    $auth->add_field("x_address", $params["clientdetails"]["address1"]);
    $auth->add_field("x_city", $params["clientdetails"]["city"]);
    $auth->add_field("x_state", $params["clientdetails"]["state"]);
    $auth->add_field("x_zip", $params["clientdetails"]["postcode"]);
    $auth->add_field("x_country", $params["clientdetails"]["country"]);
    $auth->add_field("x_phone", $params["clientdetails"]["phonenumber"]);
    $auth->add_field("x_email", $params["clientdetails"]["email"]);
    $auth->add_field("x_email_customer", "FALSE");
    $auth->add_field("x_method", "CC");
    $auth->add_field("x_card_num", $params["cardnum"]);
    $auth->add_field("x_amount", $params["amount"]);
    $auth->add_field("x_exp_date", $params["cardexp"]);
    $auth->add_field("x_card_code", $params["cccvv"]);
    $auth->add_field("x_trans_id", $params["transid"]);
    $desc = "Action => Refund\nClient => " . $params["clientdetails"]["firstname"] . " " . $params["clientdetails"]["lastname"] . "\n";
    $auth->process();
    switch ($auth->process()) {
        case 1:
            return ["status" => "success", "transid" => $auth->response["Transaction ID"], "rawdata" => $auth->dump_response()];
            break;
        case 2:
            return ["status" => "declined", "rawdata" => $auth->dump_response()];
            break;
        default:
            return ["status" => "error", "rawdata" => $auth->dump_response()];
    }
}

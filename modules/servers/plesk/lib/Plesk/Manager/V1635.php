<?php

class Plesk_Manager_V1635 extends Plesk_Manager_V1632
{
    protected function _getSsoRedirectUrl($params, $username)
    {
        $address = $params["serverhostname"] ?: $params["serverip"];
        $port = $params["serveraccesshash"] ?: "8443";
        $secure = $params["serversecure"] ? "https" : "http";
        if (empty($address)) {
            return "";
        }
        $request = new WHMCS\Http\Request($_SERVER);
        $result = Plesk_Registry::getInstance()->api->session_create(["login" => $username, "userIp" => base64_encode($request->getClientIP()), "domain" => $params["domain"]]);
        $sessionId = $result->server->create_session->result->id;
        if (is_null($sessionId)) {
            return "";
        }
        return sprintf("%s://%s:%s/enterprise/rsession_init.php?PLESKSESSID=%s", $secure, $address, $port, urlencode((string) $sessionId));
    }
}

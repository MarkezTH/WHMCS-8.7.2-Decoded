<?php

namespace WHMCS\Environment;

class Http
{
    public function siteIsConfiguredForSsl()
    {
        try {
            \App::getSystemSSLURLOrFail();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function siteHasVerifiedSslCert()
    {
        try {
            $url = \App::getSystemSSLURLOrFail();
            $whmcsHeaderVersion = \App::getVersion()->getMajor();
            $request = new \WHMCS\Http\Client\HttpClient(["verify" => true]);
            $request->get($url, ["headers" => ["User-Agent" => "WHMCS/" . $whmcsHeaderVersion]]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

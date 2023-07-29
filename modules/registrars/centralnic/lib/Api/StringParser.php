<?php

namespace WHMCS\Module\Registrar\CentralNic\Api;

class StringParser implements ParserInterface
{
    public function buildPayload($params)
    {
        $str = "";
        foreach ($params as $key => $value) {
            $str .= $key . "=" . $value . "\n";
        }
        return $str;
    }

    public function parseResponse($response)
    {
        $result = [];
        $tmp = preg_replace("/\r\n/", "\n", $response);
        if (is_null($tmp)) {
            $tmp = $response;
        }
        $rlist = explode("\n", $tmp);
        foreach ($rlist as $item) {
            if (preg_match("/^([^\\=]*[^\\t\\= ])[\\t ]*=[\\t ]*(.*)\$/", $item, $m)) {
                $attr = trim($m[1]);
                $value = rtrim($m[2]);
                if (preg_match("/^property\\[([^\\]]*)\\]/i", $attr, $m)) {
                    if (!array_key_exists("PROPERTY", $result)) {
                        $result["PROPERTY"] = [];
                    }
                    $prop = strtoupper($m[1]);
                    $tmp = preg_replace("/\\s/", "", $prop);
                    if (!is_null($tmp)) {
                        $prop = $tmp;
                    }
                    if (array_key_exists($prop, $result["PROPERTY"])) {
                        $result["PROPERTY"][$prop][] = $value;
                    } else {
                        $result["PROPERTY"][$prop] = [$value];
                    }
                } else {
                    $result[strtoupper($attr)] = $value;
                }
            }
        }
        return $result;
    }

    public function getResponseDataValue($key, $data)
    {
        if (empty($data[$key])) {
            return "";
        }
        if (is_array($data[$key])) {
            if (1 < count($data[$key])) {
                return $data[$key];
            }
            return $data[$key][0];
        }
        return $data[$key];
    }

    public function getResponseCode($response)
    {
        return (int) ($response["code"] ?? 0);
    }

    public function getResponseDescription($response)
    {
        return (string) ($response["description"] ?? "");
    }

    public function getResponseData($response)
    {
        return (array) ($response["property"] ?? []);
    }
}

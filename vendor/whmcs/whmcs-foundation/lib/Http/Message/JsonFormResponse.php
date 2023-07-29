<?php

namespace WHMCS\Http\Message;

class JsonFormResponse extends JsonResponse
{
    public static function createWithSuccess($data = NULL)
    {
        return new static(["data" => $data]);
    }

    public static function createWithErrors($data)
    {
        return new static(["fields" => $data], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
    }
}

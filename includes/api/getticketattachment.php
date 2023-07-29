<?php

$relatedId = App::getFromRequest("relatedid");
$type = App::getFromRequest("type");
$index = App::getFromRequest("index");
if (!$relatedId) {
    $apiresults = ["result" => "error", "message" => "Related ID Required"];
} else {
    if (!in_array($type, ["ticket", "reply", "note"])) {
        $apiresults = ["result" => "error", "message" => "Invalid Type. Must be one of ticket, reply, note"];
    } else {
        if (!App::isInRequest("index")) {
            $apiresults = ["result" => "error", "message" => "Attachment Index Required"];
        } else {
            $field = "attachment";
            switch ($type) {
                case "reply":
                    $table = "tblticketreplies";
                    break;
                case "note":
                    $table = "tblticketnotes";
                    $field = "attachments";
                    break;
                default:
                    $table = "tbltickets";
                    $relatedData = WHMCS\Database\Capsule::table($table)->find($relatedId, [$field, "attachments_removed"]);
                    if (!$relatedData) {
                        $apiresults = ["result" => "error", "message" => "Related ID Not Found"];
                    } else {
                        if (!$relatedData->{$field}) {
                            $apiresults = ["result" => "error", "message" => "No Attachments Found"];
                        } else {
                            if ($relatedData->attachments_removed) {
                                $apiresults = ["result" => "error", "message" => "Attachments Deleted"];
                            } else {
                                $attachments = explode("|", $relatedData->{$field});
                                if (!array_key_exists($index, $attachments)) {
                                    $apiresults = ["result" => "error", "message" => "Invalid Attachment Index"];
                                } else {
                                    $file = $attachments[$index];
                                    $fileName = substr($file, 7);
                                    $storage = Storage::ticketAttachments();
                                    try {
                                        $stream = $storage->readStream($file);
                                        $data = base64_encode(stream_get_contents($stream));
                                        fclose($stream);
                                    } catch (Exception $e) {
                                        $apiresults = ["result" => "error", "message" => $e->getMessage()];
                                        return NULL;
                                    }
                                    $apiresults = ["result" => "success", "filename" => $fileName, "data" => $data];
                                }
                            }
                        }
                    }
            }
        }
    }
}

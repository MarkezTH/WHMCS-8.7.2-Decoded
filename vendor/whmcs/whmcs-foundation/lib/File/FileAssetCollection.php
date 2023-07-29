<?php

namespace WHMCS\File;

class FileAssetCollection extends \Illuminate\Support\Collection
{
    public static function forAssetType($assetType)
    {
        switch ($assetType) {
            case FileAsset::TYPE_CLIENT_FILES:
                $items = \WHMCS\Database\Capsule::table("tblclientsfiles")->where("filename", "!=", "")->pluck("filename")->all();
                break;
            case FileAsset::TYPE_DOWNLOADS:
                $items = \WHMCS\Database\Capsule::table("tbldownloads")->pluck("location")->all();
                break;
            case FileAsset::TYPE_EMAIL_ATTACHMENTS:
                throw new \WHMCS\Exception\Storage\StorageException("Email attachment files cannot be listed");
                break;
            case FileAsset::TYPE_EMAIL_TEMPLATE_ATTACHMENTS:
                $templateAttachmentLists = \WHMCS\Database\Capsule::table("tblemailtemplates")->where("attachments", "!=", "")->pluck("attachments")->all();
                $items = [];
                foreach ($templateAttachmentLists as $list) {
                    $items = array_merge($items, explode(",", $list));
                }
                break;
            case FileAsset::TYPE_KB_IMAGES:
                $items = \WHMCS\Knowledgebase\Image::all()->pluck("filename")->toArray();
                break;
            case FileAsset::TYPE_EMAIL_IMAGES:
                $items = \WHMCS\Mail\Image::all()->pluck("filename")->toArray();
                break;
            case FileAsset::TYPE_PM_FILES:
                $items = [];
                if (\WHMCS\Database\Capsule::schema()->hasTable("mod_project_management_files")) {
                    $pmFiles = \WHMCS\Database\Capsule::table("mod_project_management_files")->get(["project_id", "filename"])->all();
                    foreach ($pmFiles as $pmFile) {
                        $items[] = $pmFile->project_id . DIRECTORY_SEPARATOR . $pmFile->filename;
                    }
                }
                break;
            case FileAsset::TYPE_TICKET_ATTACHMENTS:
                $attachmentsInDb = \WHMCS\Database\Capsule::table("tbltickets")->where("attachment", "!=", "")->where("attachments_removed", 0)->union(\WHMCS\Database\Capsule::table("tblticketreplies")->where("attachment", "!=", "")->select("attachment")->where("attachments_removed", 0))->union(\WHMCS\Database\Capsule::table("tblticketnotes")->where("attachments", "!=", "")->select("attachments")->where("attachments_removed", 0))->pluck("attachment")->all();
                $items = [];
                foreach ($attachmentsInDb as $attachment) {
                    $attachments = explode("|", $attachment);
                    foreach ($attachments as $a) {
                        $a = trim($a);
                        if ($a) {
                            array_push($items, $a);
                        }
                    }
                }
                return new static($items);
                break;
            default:
                throw new \WHMCS\Exception\Storage\StorageException("Unknown asset type: " . $assetType);
        }
    }
}

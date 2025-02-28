<?php

namespace WHMCS\File;

class Upload
{
    protected $uploadedFile = NULL;
    protected $uploadFilename = NULL;
    protected $uploadTmpName = NULL;
    protected $uploadFilenameCleaned = "";
    protected $defaultName = "";
    const DEFAULT_UPLOAD_FILENAME = "attachment";

    public function __construct($nameOrFile, $key = NULL)
    {
        if ($nameOrFile instanceof \Laminas\Diactoros\UploadedFile) {
            $this->uploadedFile = $nameOrFile;
        } else {
            if (!isset($_FILES[$nameOrFile])) {
                throw new \WHMCS\Exception\File\NotUploaded("Check name and key parameters.");
            }
            if (is_numeric($key)) {
                $this->uploadFilename = $_FILES[$nameOrFile]["name"][$key];
                $this->uploadTmpName = $_FILES[$nameOrFile]["tmp_name"][$key];
            } else {
                $this->uploadFilename = $_FILES[$nameOrFile]["name"];
                $this->uploadTmpName = $_FILES[$nameOrFile]["tmp_name"];
            }
            if (!$this->isUploaded()) {
                throw new \WHMCS\Exception\File\NotUploaded(\Lang::trans("filemanagement.nofileuploaded"));
            }
            if (!\WHMCS\File::isFileNameSafe($this->getCleanName())) {
                throw new \WHMCS\Exception(\Lang::trans("filemanagement.invalidname"));
            }
        }
    }

    public static function getUploadedFiles($name)
    {
        $attachments = [];
        $uploadedFiles = \WHMCS\Http\Message\ServerRequest::fromGlobals()->getUploadedFiles();
        if (isset($uploadedFiles[$name])) {
            $fileNumber = 1;
            if (is_array($uploadedFiles[$name])) {
                foreach ($uploadedFiles[$name] as $file) {
                    if ($file->getClientFilename() && $file->getClientMediaType()) {
                        $attachments[] = (new self($file))->setDefaultName(self::DEFAULT_UPLOAD_FILENAME . "_" . $fileNumber++);
                    }
                }
            } else {
                if ($uploadedFiles[$name]->getClientFilename() && $uploadedFiles[$name]->getClientMediaType()) {
                    $attachments[] = (new self($uploadedFiles[$name]))->setDefaultName(self::DEFAULT_UPLOAD_FILENAME . "_" . $fileNumber);
                }
            }
        }
        return $attachments;
    }

    public function store(Filesystem $storage, $fileNameToSave)
    {
        $stream = $this->uploadedFile->getStream()->detach();
        $storage->writeStream($fileNameToSave, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        return $fileNameToSave;
    }

    public function storeAsClientFile()
    {
        $storage = \Storage::clientFiles();
        $fileNameToSave = "file" . (new \WHMCS\Utility\Random())->number(6) . "_" . $this->getCleanName();
        if ($storage->has($fileNameToSave)) {
            if (true) {
            }
        }
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsDownload()
    {
        $storage = \Storage::downloads();
        $fileNameToSave = $this->getCleanName();
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsEmailAttachment()
    {
        $storage = \Storage::emailAttachments();
        $fileNameToSave = "attach" . (new \WHMCS\Utility\Random())->number(6) . "_" . $this->getCleanName();
        if ($storage->has($fileNameToSave)) {
            if (true) {
            }
        }
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsEmailTemplateAttachment()
    {
        $storage = \Storage::emailTemplateAttachments();
        $fileNameToSave = (new \WHMCS\Utility\Random())->number(6) . "_" . $this->getCleanName();
        if ($storage->has($fileNameToSave)) {
            if (true) {
            }
        }
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsProjectFile($projectId)
    {
        $storage = \Storage::projectManagementFiles($projectId);
        $fileNameToSave = (new \WHMCS\Utility\Random())->number(6) . "_" . $this->getCleanName();
        if ($storage->has($fileNameToSave)) {
            if (true) {
            }
        }
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsTicketAttachment()
    {
        $storage = \Storage::ticketAttachments();
        $fileNameToSave = (new \WHMCS\Utility\Random())->number(6) . "_" . $this->getCleanName();
        if ($storage->has($fileNameToSave)) {
            if (true) {
            }
        }
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsKbImage()
    {
        $storage = \Storage::kbImages();
        do {
            $hasUniqueName = false;
            $fileNameToSave = (new \WHMCS\Utility\Random())->string(8, 2, 2, 0) . $this->getExtension();
            if (!$storage->has($fileNameToSave)) {
                $hasUniqueName = true;
            }
        } while ($hasUniqueName);
        return $this->store($storage, $fileNameToSave);
    }

    public function storeAsEmailImage()
    {
        $storage = \Storage::emailImages();
        do {
            $hasUniqueName = false;
            $fileNameToSave = (new \WHMCS\Utility\Random())->string(8, 2, 2, 0) . $this->getExtension();
            if (!$storage->has($fileNameToSave)) {
                $hasUniqueName = true;
            }
        } while ($hasUniqueName);
        return $this->store($storage, $fileNameToSave);
    }

    public function getFileName()
    {
        return !is_null($this->uploadedFile) ? $this->uploadedFile->getClientFilename() : $this->uploadFilename;
    }

    public function getExtension()
    {
        $fileNameParts = explode(".", $this->getFileName());
        return "." . strtolower(end($fileNameParts));
    }

    public function getFileTmpName()
    {
        return $this->uploadTmpName;
    }

    public function getSize()
    {
        return $this->uploadedFile->getSize();
    }

    public function getClientMediaType()
    {
        return $this->uploadedFile->getClientMediaType();
    }

    public function getCleanName()
    {
        if ($this->getFileName() && strlen($this->uploadFilenameCleaned) == 0) {
            $this->uploadFilenameCleaned = \WHMCS\File::getFileName($this->getFileName(), $this->getDefaultName());
        }
        return $this->uploadFilenameCleaned;
    }

    protected function getDefaultName()
    {
        return scoalesce($this->defaultName, self::DEFAULT_UPLOAD_FILENAME);
    }

    public function setDefaultName($self, $defaultName)
    {
        $this->defaultName = $defaultName;
        return $this;
    }

    public function isUploaded()
    {
        return is_uploaded_file($this->getFileTmpName());
    }

    public function move($dest_dir = "", $prefix = "")
    {
        if (!is_writable($dest_dir)) {
            throw new \WHMCS\Exception(\Lang::trans("filemanagement.couldNotSaveFile") . " " . \Lang::trans("filemanagement.checkPermissions"));
        }
        $destinationPath = $this->generateUniqueDestinationPath($dest_dir, $prefix);
        if (!move_uploaded_file($this->getFileTmpName(), $destinationPath)) {
            throw new \WHMCS\Exception(\Lang::trans("filemanagement.couldNotSaveFile") . " " . \Lang::trans("filemanagement.checkAvailableDiskSpace"));
        }
        return basename($destinationPath);
    }

    protected function generateUniqueDestinationPath($dest_dir, $prefix)
    {
        mt_srand($this->makeRandomSeed());
        $i = 1;
        while ($i <= 30) {
            $rand = mt_rand(100000, 999999);
            $destinationPath = $dest_dir . DIRECTORY_SEPARATOR . str_replace("{RAND}", $rand, $prefix) . $this->getCleanName();
            $file = new \WHMCS\File($destinationPath);
            if ($file->exists()) {
                if (strpos($prefix, "{RAND}") === false) {
                    throw new \WHMCS\Exception(\Lang::trans("filemanagement.couldNotSaveFile") . " " . \Lang::trans("filemanagement.fileAlreadyExists"));
                }
                $i++;
            } else {
                return $destinationPath;
            }
        }
        throw new \WHMCS\Exception(\Lang::trans("filemanagement.couldNotSaveFile") . " " . \Lang::trans("filemanagement.noUniqueName"));
    }

    protected function makeRandomSeed()
    {
        list($usec, $sec) = explode(" ", microtime());
        return (double) $sec + (double) $usec * 100000;
    }

    public function checkExtension()
    {
        return self::isExtensionAllowed($this->getFileName());
    }

    public static function isExtensionAllowed($filename)
    {
        if (strpos($filename, ".") === 0) {
            return false;
        }
        $whmcs = \DI::make("app");
        $alwaysBannedExtensions = ["php", "cgi", "pl", "htaccess"];
        $extensionArray = explode(",", strtolower($whmcs->get_config("TicketAllowedFileTypes")));
        $filenameParts = pathinfo($filename);
        $fileExtension = strtolower($filenameParts["extension"]);
        if (in_array($fileExtension, $alwaysBannedExtensions)) {
            return false;
        }
        if (in_array("." . $fileExtension, $extensionArray)) {
            return true;
        }
        return false;
    }

    public function contents()
    {
        return file_get_contents($this->getFileTmpName());
    }

    public function setFilename($name)
    {
        $this->uploadFilename = $name;
    }
}

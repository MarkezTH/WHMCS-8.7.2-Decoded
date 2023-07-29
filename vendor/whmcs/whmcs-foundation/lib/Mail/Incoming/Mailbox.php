<?php

namespace WHMCS\Mail\Incoming;

class Mailbox implements MailboxInterface
{
    use MailboxOauthTokenTrait;
    private $storage = NULL;

    public static function createForDepartment($department, $isTest = false)
    {
        $self = new static();
        $self->isTest = $isTest;
        $isPop3 = true;
        $sslType = NULL;
        switch ($department->port) {
            case 995:
                $sslType = "ssl";
                break;
            case 143:
            case 993:
                $isPop3 = false;
                if ($department->port == 993) {
                    $sslType = "ssl";
                }
                break;
            default:
                if ($isPop3) {
                    $protocol = new Protocol\Pop3();
                } else {
                    $protocol = new Protocol\Imap();
                }
                $protocol->connectWithSslIfEnforced($department->host, $department->port, $sslType);
                switch ($department->mailAuthConfig["auth_type"]) {
                    case \WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2:
                        if (!$protocol instanceof Protocol\Oauth2Interface) {
                            throw new \WHMCS\Exception("Selected mail protocol does not support Oauth2");
                        }
                        $accessToken = $self->getOauth2AccessToken($department);
                        $protocol->oauth2Login($department->login, $accessToken);
                        break;
                    default:
                        $protocol->login($department->login, $department->password);
                        if ($isPop3) {
                            $self->storage = new \Laminas\Mail\Storage\Pop3($protocol);
                        } else {
                            $self->storage = new \Laminas\Mail\Storage\Imap($protocol);
                        }
                        return $self;
                }
        }
    }

    public function getMessageCount()
    {
        return $this->storage->count();
    }

    public function getAllMessages($Iterator)
    {
        return $this->storage;
    }

    public function getRfcMessage($messageIndex, $messageData)
    {
        return $this->storage->getRawHeader($messageIndex) . \Laminas\Mail\Headers::EOL . $messageData->getContent();
    }

    public function deleteMessage($messageIndex)
    {
        $this->storage->removeMessage($messageIndex);
    }

    public function close()
    {
        $this->storage->close();
    }
}

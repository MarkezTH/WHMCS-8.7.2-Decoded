<?php

namespace WHMCS\Support\Traits;

trait Requestor
{
    public function getRequestorName()
    {
        if ($this->postedByAnAdmin()) {
            return $this->admin;
        }
        if (0 < $this->requestorId) {
            $user = \WHMCS\User\User::find($this->requestorId);
            return $user->fullName;
        }
        if (0 < $this->contactId) {
            $contact = \WHMCS\User\Client\Contact::find($this->contactId);
            if ($contact && $contact->userId === $this->userId) {
                return $contact->fullName;
            }
        }
        if (0 < $this->userId) {
            $client = \WHMCS\User\Client::find($this->userId);
            if ($client) {
                return $client->fullName;
            }
        }
        if ($this->name) {
            return $this->name;
        }
        return "Undefined";
    }

    public function getRequestorEmail()
    {
        if ($this->postedByAnAdmin()) {
            return "";
        }
        if (0 < $this->requestorId) {
            $user = \WHMCS\User\User::find($this->requestorId);
            return $user->email;
        }
        if (0 < $this->contactId) {
            $contact = \WHMCS\User\Client\Contact::find($this->contactId);
            if ($contact && $contact->userId === $this->userId) {
                return $contact->email;
            }
        }
        if (0 < $this->userId) {
            $client = \WHMCS\User\Client::find($this->userId);
            if ($client) {
                return $client->email;
            }
        }
        if ($this->email) {
            return $this->email;
        }
        return "";
    }

    public function getRequestorType()
    {
        if ($this->postedByAnAdmin()) {
            return \WHMCS\Support\Ticket\RequestorTypes::ADMIN;
        }
        if ($this->postedByGuest()) {
            return \WHMCS\Support\Ticket\RequestorTypes::GUEST;
        }
        if ($this->postedByUser()) {
            if ($this instanceof \WHMCS\Support\Ticket\Reply) {
                $ticket = $this->ticket;
            } else {
                $ticket = $this;
            }
            if ($ticket->client instanceof \WHMCS\User\Client) {
                $ticketOwner = $ticket->client->owner();
                if ($ticketOwner && $this->requestorId === $ticketOwner->id) {
                    return \WHMCS\Support\Ticket\RequestorTypes::OWNER;
                }
                if (in_array($this->requestorId, $ticket->client->getUserIds())) {
                    return \WHMCS\Support\Ticket\RequestorTypes::USER;
                }
            }
            return \WHMCS\Support\Ticket\RequestorTypes::REGISTERED_USER;
        }
        if (0 < $this->contactId) {
            return \WHMCS\Support\Ticket\RequestorTypes::LEGACY_SUBACCOUNT;
        }
        if (0 < $this->userId) {
            return \WHMCS\Support\Ticket\RequestorTypes::OWNER;
        }
        return \WHMCS\Support\Ticket\RequestorTypes::GUEST;
    }

    public function getRequestorDisplayLabel()
    {
        $label = "<span class=\"ticket-requestor-name\">" . $this->getRequestorName() . "</span>";
        $type = \WHMCS\Utility\Status::normalise($this->getRequestorType());
        $langString = defined("ADMINAREA") ? \AdminLang::trans("support.requestor." . $type) : \Lang::trans("support.requestor." . $type);
        if ($type) {
            $label .= " <span class=\"label requestor-type-" . $type . "\">" . $langString . "</span>";
        }
        return $label;
    }

    public function postedByAnAdmin()
    {
        return 0 < strlen($this->admin);
    }

    public function postedByGuest()
    {
        return 0 < strlen($this->email) && $this->requestorId == 0;
    }

    public function postedByUser()
    {
        return 0 < $this->requestorId;
    }

    public function requestor()
    {
        $this->getRequestorType();
        switch ($this->getRequestorType()) {
            case \WHMCS\Support\Ticket\RequestorTypes::LEGACY_SUBACCOUNT:
                $class = "WHMCS\\User\\Client\\Contact";
                break;
            default:
                $class = "WHMCS\\User\\User";
                return $this->belongsTo($class, "requestor_id");
        }
    }
}

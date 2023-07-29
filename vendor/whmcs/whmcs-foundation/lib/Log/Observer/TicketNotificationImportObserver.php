<?php

namespace WHMCS\Log\Observer;

class TicketNotificationImportObserver
{
    public function created($ticketImport)
    {
        \WHMCS\Support\Ticket\TicketImportNotification::storeEntry($ticketImport);
    }

    public function updated($ticketImport)
    {
        if (!$ticketImport->isPending()) {
            $ticketImport->notification()->delete();
        }
    }

    public function deleting($ticketImport)
    {
        $ticketImport->notification()->delete();
    }
}

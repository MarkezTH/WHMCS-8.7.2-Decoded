<?php

namespace WHMCS\Support\Ticket\Observers;

class TicketNotificationTicketObserver
{
    public function deleting($ticket)
    {
        $ticket->notifications()->delete();
    }
}

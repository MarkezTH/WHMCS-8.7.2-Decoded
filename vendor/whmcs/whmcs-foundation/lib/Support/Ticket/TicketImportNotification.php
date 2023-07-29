<?php

namespace WHMCS\Support\Ticket;

class TicketImportNotification extends \Illuminate\Database\Eloquent\Relations\Pivot
{
    protected $table = "tblticketpendingimports";
    public $timestamps = false;

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->integer("ticket_id");
                $table->integer("ticketmaillog_id");
                $table->index("ticket_id", "ticket_id_idx");
                $table->unique(["ticketmaillog_id", "ticket_id"], "ticketmaillog_id_ticket_id");
            });
        }
    }

    public function ticket($BelongsTo)
    {
        return $this->belongsTo("WHMCS\\Support\\Ticket", "ticket_id");
    }

    public function importLog($BelongsTo)
    {
        return $this->belongsTo("WHMCS\\Log\\TicketImport", "ticketmaillog_id");
    }

    public function scopeTicketId($Builder, $query, int $ticketId)
    {
        return $query->where("ticket_id", $ticketId);
    }

    public function scopeImportLogId($Builder, $query, int $importLogId)
    {
        return $query->where("ticketmaillog_id", $importLogId);
    }

    public static function storeEntry($ticketImport)
    {
        $ticket = $ticketImport->getTicket();
        if ($ticket && 0 < $ticketImport->id && $ticketImport->isPending()) {
            TicketImportNotification::insertOrIgnore(["ticketmaillog_id" => $ticketImport->id, "ticket_id" => $ticket->id]);
        }
    }
}

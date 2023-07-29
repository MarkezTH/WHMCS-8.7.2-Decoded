<?php

namespace WHMCS\Product\Server\Relations;

class ServerGroup extends \WHMCS\Model\Relations\AbstractPivot
{
    protected $table = "tblservergroupsrel";
    public $timestamps = false;
    public $incrementing = true;
}

<?php

namespace WHMCS\Model\Relations;

class AbstractPivot extends \Illuminate\Database\Eloquent\Relations\Pivot
{
    use \WHMCS\Model\Traits\DateTimeTrait;
}

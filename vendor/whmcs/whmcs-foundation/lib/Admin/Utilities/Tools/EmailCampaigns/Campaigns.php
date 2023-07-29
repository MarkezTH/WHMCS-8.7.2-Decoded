<?php

namespace WHMCS\Admin\Utilities\Tools\EmailCampaigns;

class Campaigns extends \WHMCS\TableModel
{
    public function _execute($Collection, $criteria)
    {
        return $this->getCampaigns($criteria);
    }

    protected function getCampaigns($Collection, $criteria)
    {
        $campaigns = \WHMCS\Mail\Campaign::with("admin");
        $this->getPageObj()->setNumResults($campaigns->count());
        $campaigns->orderBy($this->getPageObj()->getOrderBy(), $this->getPageObj()->getSortDirection())->limit($this->getRecordLimit())->offset($this->getRecordOffset());
        return $campaigns->get();
    }
}

<?php

namespace WHMCS\Admin\User;

class UsersFilter extends \WHMCS\TableModel
{
    public function _execute($implementationData = [])
    {
        return $this->getUsers($implementationData);
    }

    protected function getUsers($criteria = NULL)
    {
        $users = \WHMCS\User\User::where("id", "!=", 0);
        if (!empty($criteria["criteria"])) {
            $users->where(\WHMCS\Database\Capsule::raw("CONCAT_WS(' ', first_name, last_name)"), "like", "%" . $criteria["criteria"] . "%")->orWhere("email", "like", "%" . $criteria["criteria"] . "%");
        }
        $this->getPageObj()->setNumResults($users->count());
        $users->orderBy($this->getPageObj()->getOrderBy(), $this->getPageObj()->getSortDirection())->limit($this->getRecordLimit())->offset($this->getRecordOffset());
        return $users->get();
    }
}

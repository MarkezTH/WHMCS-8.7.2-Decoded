<?php

namespace WHMCS\Knowledgebase\View;

class Category extends Index
{
    protected function initializeView()
    {
        parent::initializeView();
        $this->assign("kbcurrentcat", ["editLink" => NULL]);
        $this->assign("kbcats", NULL);
        $this->assign("kbarticles", NULL);
        $this->setTemplate("knowledgebasecat");
    }
}

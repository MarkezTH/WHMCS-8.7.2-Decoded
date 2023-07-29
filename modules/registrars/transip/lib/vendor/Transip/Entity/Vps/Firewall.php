<?php

namespace Transip\Api\Library\Entity\Vps;

class Firewall extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $isEnabled = NULL;
    protected $ruleSet = NULL;

    public function __construct($valueArray = [])
    {
        parent::__construct($valueArray);
        $ruleSet = [];
        $ruleSetArray = $valueArray["ruleSet"] ?? [];
        foreach ($ruleSetArray as $ruleArray) {
            $ruleSet[] = new FirewallRule($ruleArray);
        }
        $this->ruleSet = $ruleSet;
    }

    public function isEnabled()
    {
        return $this->isEnabled;
    }

    public function setIsEnabled($Firewall, $isEnabled)
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    public function getRuleSet()
    {
        return $this->ruleSet;
    }

    public function setRuleSet($Firewall, $ruleSet)
    {
        $this->ruleSet = $ruleSet;
        return $this;
    }

    public function addRule($Firewall, $firewallRule)
    {
        $ruleSet = [];
        foreach ($this->getRuleSet() as $rule) {
            if ($rule->equalsRule($firewallRule)) {
                $ruleSet[] = $firewallRule;
            } else {
                $ruleSet[] = $rule;
            }
        }
        $this->ruleSet[] = $firewallRule;
        return $this;
    }

    public function removeRule($Firewall, $firewallRule)
    {
        $newRuleSet = [];
        foreach ($this->getRuleSet() as $rule) {
            if (!$rule->equalsRule($firewallRule)) {
                $newRuleSet[] = $rule;
            }
        }
        $this->setRuleSet($newRuleSet);
        return $this;
    }
}

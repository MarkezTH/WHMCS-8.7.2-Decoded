<?php

namespace WHMCS\User\Observers;

class SecurityQuestionObserver
{
    public function created($question)
    {
        logAdminActivity("Security Question Created - Security Question ID: " . $question->id);
    }

    public function deleted($question)
    {
        logAdminActivity("Security Question Deleted - Security Question ID: " . $question->id);
    }

    public function updated($question)
    {
        $changeList = $question->getChanges();
        if (0 < count($changeList)) {
            logAdminActivity("Security Question Modified - Security Question ID: " . $question->id);
        }
    }
}

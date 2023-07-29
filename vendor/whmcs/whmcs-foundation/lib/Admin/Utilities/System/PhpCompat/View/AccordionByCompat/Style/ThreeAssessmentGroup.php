<?php

namespace WHMCS\Admin\Utilities\System\PhpCompat\View\AccordionByCompat\Style;

class ThreeAssessmentGroup
{
    protected $assessmentGroups = [];

    public function __construct()
    {
        $this->assessmentGroups = $this->defaultAssessmentGroups();
    }

    public function defaultAssessmentGroups($phpVersionId = NULL)
    {
        if (in_array($phpVersionId, ["0506", "0700", "0801"])) {
            $unlikelyCompatText = \AdminLang::trans("phpCompatUtil.compatUnknownDesc1");
        } else {
            $unlikelyCompatText = \AdminLang::trans("phpCompatUtil.compatUnknownDesc2");
        }
        return [\WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO => ["type" => "Incompat", "desc" => \AdminLang::trans("phpCompatUtil.compatNoDesc"), "title" => \AdminLang::trans("phpCompatUtil.compatNoTitle"), "titleCssClass" => "default", "data" => []], \WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_UNLIKELY => ["type" => "Unknown", "desc" => $unlikelyCompatText, "title" => \AdminLang::trans("phpCompatUtil.compatUnknownTitle"), "titleCssClass" => "default", "data" => []], \WHMCS\Environment\Ioncube\Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_YES => ["type" => "Compat", "desc" => \AdminLang::trans("phpCompatUtil.compatYesDesc"), "title" => \AdminLang::trans("phpCompatUtil.compatYesTitle"), "titleCssClass" => "success", "data" => []]];
    }
}

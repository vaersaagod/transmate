<?php
namespace vaersaagod\transmate\base;

use vaersaagod\transmate\helpers\TranslateHelper;

trait TranslatableFieldActionsTrait
{
    public function actionMenuItems(?\craft\base\ElementInterface $element = null, bool $static = false): array
    {
        $actionMenuItems = parent::actionMenuItems($element, $static);

        if ($static || !$this->translatable($element)) {
            return $actionMenuItems;
        }
        $translateFieldAction = TranslateHelper::getTranslateFieldAction($this, $element);
        
        return array_filter([...$actionMenuItems, $translateFieldAction]);
    }

    public function getLabel(): ?string
    {
        return $this->showLabel() ? $this->label() : null;
    }
}

<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;
use vaersaagod\transmate\models\fieldprocessors\ProcessorInterface;
use vaersaagod\transmate\translators\TranslatorInterface;

class TranslatableContent extends Model
{
    /** @var array */
    public array $fields = [];

    public function translate(TranslatorInterface $translator): void
    {
        // TODO: Add option to get a full XML document to translate
        foreach ($this->fields as $fieldProcessor) {
            /** @var $fieldProcessor ProcessorInterface */
            $fieldProcessor->translate($translator);
        }
    }

    public function addField(string $handle, ProcessorInterface $processor): void
    {
        $this->fields[$handle] = $processor;
    }

    public function hasFieldWithHandle(string $handle): bool
    {
        return isset($this->fields[$handle]);
    }

}

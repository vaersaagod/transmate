<?php
namespace vaersaagod\transmate\models\fieldprocessors;

use vaersaagod\transmate\translators\TranslatorInterface;

interface ProcessorInterface 
{
    public function getValue(): mixed;
    public function setTranslatedValue(mixed $translatedValue): void;
    public function translate(TranslatorInterface $translator): void;
}

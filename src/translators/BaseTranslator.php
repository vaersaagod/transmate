<?php
namespace vaersaagod\transmate\translators;

abstract class BaseTranslator implements TranslatorInterface
{
    public mixed $config = [];
    public string $fromLanguage = '';
    public string $toLanguage = '';
}

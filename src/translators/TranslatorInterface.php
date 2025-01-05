<?php
namespace vaersaagod\transmate\translators;

interface TranslatorInterface 
{
    public function translate(string $content, array $params = []): mixed;
}

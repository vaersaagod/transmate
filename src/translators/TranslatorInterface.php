<?php
namespace vaersaagod\transmate\translators;

interface TranslatorInterface 
{
    public const HANDLE = '';

    public function translate(string $content, array $params = []): mixed;
}

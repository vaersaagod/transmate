<?php
namespace vaersaagod\transmate\translators;

interface TranslatorInterface 
{
    public const HANDLE = '';

    public function translate(string $content, array $params = []): mixed;

    /**
     * Translates multiple strings that share a source/target language pair, preserving the input
     * array's keys. Translators that support native batching should do it in a single request.
     *
     * @param array $contents Array of strings to translate (keys are preserved on the result)
     * @param array $params
     * @return array
     */
    public function translateMany(array $contents, array $params = []): array;
}

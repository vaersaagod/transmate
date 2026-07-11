<?php
namespace vaersaagod\transmate\translators;

abstract class BaseTranslator implements TranslatorInterface
{
    public mixed $config = [];
    public string $fromLanguage = '';
    public string $toLanguage = '';

    /**
     * Default batch implementation: loop the single-string translate(). Translators that support
     * native batching (e.g. DeepL) should override this to translate in a single request.
     *
     * @param array $contents
     * @param array $params
     * @return array
     */
    public function translateMany(array $contents, array $params = []): array
    {
        $result = [];
        foreach ($contents as $key => $content) {
            $result[$key] = $this->translate($content, $params);
        }
        return $result;
    }
}

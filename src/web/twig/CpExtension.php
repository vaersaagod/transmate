<?php

namespace vaersaagod\transmate\web\twig;

use craft\base\ElementInterface;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use vaersaagod\transmate\helpers\TranslateHelper;

/**
 * Twig extension
 */
class CpExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('getTranslateToSites', static fn (?ElementInterface $element) => TranslateHelper::getAllowedSitesForTranslation($element)),
            new TwigFunction('getTranslateFromSites', static fn (?ElementInterface $element) => TranslateHelper::getAllowedSitesForTranslation($element, enabledSitesOnly: true)),
        ];
    }
}

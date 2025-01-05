<?php

namespace vaersaagod\transmate\helpers;

use craft\base\Element;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\models\Site;

class ElementHelper
{
    
    public static function getTargetEntry(Element $element, Site $site): Element
    {
        $targetElement = \Craft::$app->elements->getElementById($element->id, null, $site->id);

        if ($targetElement === null) {
            if ($element instanceof Entry && $element->sectionId !== null && $element->section->propagationMethod === PropagationMethod::Custom) {
                // The section has custom propagation, ie "Let each entry choose...".
                // The entry doesn't exist in the target site, so let's create it.
                $sitesEnabled = $element->getEnabledForSite();
                $targetSiteId = $site->id;
                
                if (!isset($sitesEnabled[$targetSiteId])) {
                    $sitesEnabled[$targetSiteId] = $element->enabledForSite;
                } else {
                    $sitesEnabled = [
                        $element->site->id => $element->enabledForSite,
                        $targetSiteId => $element->enabledForSite,
                    ];
                }

                $element->setEnabledForSite($sitesEnabled);

                // Let's match the source element's state
                if ($element->getIsDraft()) {
                    \Craft::$app->drafts->saveElementAsDraft($element);
                } else {
                    \Craft::$app->elements->saveElement($element);
                }

                $targetElement = \Craft::$app->elements->getElementById($element->id, Entry::class, $targetSiteId);
                
            } elseif ($element instanceof Entry && $element->sectionId !== null && $element->section->propagationMethod === PropagationMethod::All) {
                // Should have been there, let's just propagate it now.
                $targetElement = \Craft::$app->elements->propagateElement($element, $site->id, false);
                
            } else {
                // Duplicates element to new site. Which is necessary for nested entries to work.
                // But this probably means that an element can be duplicated and translated for a
                // section that doesn't propagate. In whichcase there will be duplicates if it's
                // done more than once. More of a cave-at and/or feature, than a bug? 
                $targetElement = \Craft::$app->elements->duplicateElement($element, ['siteId' => $site->id]);
            }
        }

        return $targetElement;
    }
}



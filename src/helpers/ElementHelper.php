<?php

namespace vaersaagod\transmate\helpers;

use craft\base\Element;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\models\Site;

class ElementHelper
{
    
    public static function getTargetEntry(Element $element, Site $site, ?Element $owner=null): Element
    {
        $targetElement = \Craft::$app->elements->getElementById($element->id, null, $site->id);
        
        if ($targetElement === null) {
            if ($element instanceof Entry && $element->sectionId !== null && $element->section->propagationMethod === PropagationMethod::Custom) {
                // The section has custom propagation, ie "Let each entry choose...".
                // The entry doesn't exist in the target site, so let's create it.
                $targetSiteId = $site->id;
                
                $sitesEnabled = [
                    $element->site->id => $element->enabledForSite,
                    $targetSiteId => $element->enabledForSite,
                ];

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
                // Catches instances where the section might have changed propagation method and entries haven't been resaved. 
                
                $targetElement = \Craft::$app->elements->propagateElement($element, $site->id, false);
                
            } else {
                // Duplicates element to new site. Which is necessary for nested entries to work.
                // Also enables an element to be duplicated and translated for a section that doesn't propagate.
                // In whichcase there will be duplicates if it's done more than once. It's a cave-at and/or feature, not a bug.
                // We need to set owner and primary owner here to avoid these being set to entries in different sites
                // for nested entries in sections/fields that don't propagate.
                // And we need to set ownerId to null when the owner is a draft, because Craft has to figure it out itself.
                // Mats think there be dragons here. I'm super positive. 
                
                $targetElement = \Craft::$app->elements->duplicateElement($element, ['siteId' => $site->id, 'ownerId' => $owner && !$owner->getIsDraft() ? $owner->id : null, 'primaryOwnerId' => $owner?->id]);
            }
        }

        return $targetElement;
    }
}



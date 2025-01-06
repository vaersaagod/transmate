<?php

namespace vaersaagod\transmate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Site;
use vaersaagod\transmate\helpers\ElementHelper;
use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\models\fieldprocessors\ProcessorInterface;
use vaersaagod\transmate\translators\BaseTranslator;
use vaersaagod\transmate\translators\DeepLTranslator;
use vaersaagod\transmate\translators\OpenAITranslator;
use vaersaagod\transmate\TransMate;
use yii\base\InvalidConfigException;

/**
 * Translate Service
 *
 * @author    Værsågod
 * @package   TransMate
 * @since     1.0.0
 */
class Translate extends Component
{

    /**
     * @param \craft\base\Element $element
     * @param \craft\models\Site  $fromSite
     * @param \craft\models\Site  $toSite
     * @param string|null         $language
     *
     * @return \craft\base\Element|null
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function translateElement(Element $element, Site $fromSite, Site $toSite, ?string $language = null): ?Element
    {
        if ($language === null) {
            $language = $toSite->getLocale()->getLanguageID();
        }

        $targetElement = ElementHelper::getTargetEntry($element, $toSite);

        if (TransMate::getInstance()->getSettings()->disableTranslationProperty !== null && $targetElement->{TransMate::getInstance()->getSettings()->disableTranslationProperty}) {
            // TBD: Klassisk dilemma, skal jeg returnere null her? Eller f.eks targetElement uendret? Eller noe annet? 
            // return null;
            return $targetElement;
        }
        
        // Create translator
        $translator = $this->getTranslator();

        if ($translator === null) {
            throw new InvalidConfigException('Translator could not be created.');
        }

        $translator->fromLanguage = $fromSite->getLocale()->getLanguageID();
        $translator->toLanguage = $language;

        $translatableContent = TranslateHelper::getTranslatableContentFromElement($element, $targetElement);
        $translatableContent->translate($translator);
        
        foreach ($translatableContent->fields as $handle => $processor) {
            /** @var $processor ProcessorInterface */
            
            if ($handle === 'title') { // Handling native field "title"
                $targetElement->title = $processor->getValue();
            } elseif ($handle === 'alt' && $targetElement instanceof Asset) { // Handling native field "alt", but only for assets.
                $targetElement->alt = $processor->getValue();
            } else {
                $targetElement->setFieldValue($handle, $processor->getValue());
            }
        }
        
        // Handling slug
        if (TranslateHelper::shouldTranslateSlug($element) && $translatableContent->hasFieldWithHandle('title')) { 
            $targetElement->slug = null;
        }
        

        $revisionNotes = 'Translated from "'.$fromSite->name.'" ('.$fromSite->getLocale()->getLanguageID().')';

        if ($targetElement instanceof Entry && $targetElement->getIsDraft()) {
            \Craft::$app->drafts->saveElementAsDraft($targetElement, TransMate::getInstance()->getSettings()->creatorId, 'Translated draft', $revisionNotes);
        } elseif ($targetElement instanceof Entry && TransMate::getInstance()->getSettings()->saveMode === 'draft') {
            $targetElement = \Craft::$app->drafts->createDraft($targetElement, TransMate::getInstance()->getSettings()->creatorId, 'Translated draft', $revisionNotes);
        } else {
            $targetElement->setRevisionNotes($revisionNotes);
            \Craft::$app->elements->saveElement($targetElement);
        }

        return $targetElement;
    }

    public function getTranslator(): ?BaseTranslator
    {
        $translator = TransMate::getInstance()->getSettings()->translator;
        
        if (empty($translator)) {
            return null;
        }
        
        if ($translator === 'deepl') {
            return new DeepLTranslator(TransMate::getInstance()->getSettings()->translatorConfig[$translator] ?? null);
        } 
        
        if ($translator === 'openai') {
            return new OpenAITranslator(TransMate::getInstance()->getSettings()->translatorConfig[$translator] ?? null);
        }
        
        return null;
    }
    
}

<?php

namespace vaersaagod\transmate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\i18n\I18N;
use craft\models\Site;
use vaersaagod\transmate\helpers\ElementHelper;
use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\jobs\TranslateJob;
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
     * @param Element $element
     * @param Site $fromSite
     * @param Site $toSite
     * @param string|null $language
     * @param string|null $saveMode
     * @param bool $saveElement
     * @param Element|null $owner
     * @param array|null $attributes
     * @return Element|null
     * @throws InvalidConfigException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function translateElement(Element $element, Site $fromSite, Site $toSite, ?string $language = null, ?string $saveMode = null, bool $saveElement = true, ?Element $owner = null, ?array $attributes = null): ?Element
    {
        $language = $language ?? $toSite->getLocale()->getLanguageID();
        $saveMode = $saveMode ?? TransMate::getInstance()->getSettings()->saveMode;
        $saveAsDraft = in_array($saveMode, ['draft', 'provisional'], true);
        $userId = Craft::$app->getUser()->getIdentity()?->getId();

        $targetElement = ElementHelper::getTargetEntry($element, $toSite, $owner);

        if (TransMate::getInstance()->getSettings()->disableTranslationProperty !== null && isset($targetElement->{TransMate::getInstance()->getSettings()->disableTranslationProperty}) && $targetElement->{TransMate::getInstance()->getSettings()->disableTranslationProperty}) {
            // TBD: Klassisk dilemma, skal jeg returnere null her? Eller f.eks targetElement uendret? Eller noe annet? 
            // return null;
            return $targetElement;
        }

        // If saving as draft, ensure we are working with a draft
        if ($saveAsDraft && !$targetElement->getIsDraft()) {
            $name = 'Translation from "'.(Craft::$app->getI18n()->getLocaleById($fromSite->getLocale()->getLanguageID())->displayName).'" to "'.(Craft::$app->getI18n()->getLocaleById($language)->displayName).'"';
            $targetElement = Craft::$app->drafts->createDraft($targetElement, $userId, $name, provisional: $saveMode === 'provisional');
        }

        // Create translator
        $translator = $this->getTranslator();

        if ($translator === null) {
            throw new InvalidConfigException('Translator could not be created.');
        }

        $translator->fromLanguage = $fromSite->getLocale()->getLanguageID();
        $translator->toLanguage = $language;

        $translatableContent = TranslateHelper::getTranslatableContentFromElement($element, $targetElement, $attributes);
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
            $targetElement->slug = \craft\helpers\ElementHelper::generateSlug($targetElement->title);
        }

        if ($saveElement) {
            $revisionNotes = 'Translated from "'.$fromSite->name.'" ('.$fromSite->getLocale()->getLanguageID().')';
            $targetElement->setRevisionNotes($revisionNotes);

            \Craft::$app->elements->saveElement($targetElement);
        }

        return $targetElement;
    }

    public function maybeAutoTranslate(ElementInterface $element): void
    {
        $settings = TransMate::getInstance()->getSettings();

        if ($element instanceof Asset && $element->getScenario() === Asset::SCENARIO_INDEX) {
            return;
        }

        if ($element->getIsRevision()) {
            return;
        }

        if (!$settings->autoTranslateDrafts && $element->getIsDraft()) {
            return;
        }

        /** @var \vaersaagod\transmate\models\AutoTranslateSettings $autoTranslateSettings */
        foreach ($settings->autoTranslate as $autoTranslateSettings) {
            $fromSite = is_string($autoTranslateSettings->fromSite) ? Craft::$app->sites->getSiteByHandle($autoTranslateSettings->fromSite) : Craft::$app->sites->getSiteById($autoTranslateSettings->fromSite);
            $toSites = !is_array($autoTranslateSettings->toSite) ? [$autoTranslateSettings->toSite] : $autoTranslateSettings->toSite;
            $elementType = $autoTranslateSettings->elementType;
            $criteria = $autoTranslateSettings->criteria;

            if ($fromSite === null || $element->siteId !== $fromSite->id) {
                continue;
            }

            if (!($element instanceof $elementType)) {
                continue;
            }

            if ($criteria) {
                /** @var \craft\db\Query $query */
                $query = $elementType::find();
                $criteria['id'] = $element->getId();
                $criteria['siteId'] = $fromSite->id;
                $criteria['status'] = null;

                if ($settings->autoTranslateDrafts) {
                    $criteria['drafts'] = true;
                }

                Craft::configure($query, $criteria);

                if ($query->count() === 0) {
                    continue;
                }
            }

            // We're good, create jobs

            /** @var Site $toSite */
            foreach ($toSites as $toSiteIdOrHandle) {
                $queue = Craft::$app->getQueue();
                $toSite = is_string($toSiteIdOrHandle) ? Craft::$app->sites->getSiteByHandle($toSiteIdOrHandle) : Craft::$app->sites->getSiteById($toSiteIdOrHandle);

                if ($toSite === null) {
                    continue;
                }

                $jobId = $queue->push(new TranslateJob([
                    'description' => Craft::t('transmate', 'Translating content'),
                    'elementId' => $element->id,
                    'fromSiteId' => $fromSite->id,
                    'toSiteId' => $toSite->id
                ]));

                Craft::info("Created transform job with ID $jobId for element with ID $element->id from site with ID $fromSite->id to site with ID $toSite->id", __METHOD__);
            }
        }
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

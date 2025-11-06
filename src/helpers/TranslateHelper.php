<?php

namespace vaersaagod\transmate\helpers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldLayoutElement;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\fieldlayoutelements\assets\AltField;
use craft\fieldlayoutelements\assets\AssetTitleField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\helpers\StringHelper;
use craft\models\Site;

use Illuminate\Support\Collection;

use vaersaagod\transmate\base\NativeFieldActionsEventTrait;
use vaersaagod\transmate\models\fieldprocessors\LinkMateProcessor;
use vaersaagod\transmate\models\fieldprocessors\MatrixProcessor;
use vaersaagod\transmate\models\fieldprocessors\RedactorProcessor;
use vaersaagod\transmate\TransMate;
use vaersaagod\transmate\models\fieldprocessors\CKEditorProcessor;
use vaersaagod\transmate\models\fieldprocessors\LinkProcessor;
use vaersaagod\transmate\models\fieldprocessors\PlainTextProcessor;
use vaersaagod\transmate\models\fieldprocessors\TableProcessor;
use vaersaagod\transmate\models\TranslatableContent;

class TranslateHelper
{

    public static function getTranslatableContentFromElement(Element $element, ?Element $targetElement, ?array $attributes = null): TranslatableContent
    {
        $translatableContent = new TranslatableContent();
        
        if (self::shouldTranslateTitle($element) && ($attributes === null || in_array('title', $attributes))) {
            $translatableContent->addField('title', new PlainTextProcessor(['field' => null, 'originalValue' => $element->title]));
        }
        
        if ($element instanceof Asset && $element->alt && $element->getVolume()->altTranslationMethod !== 'none' && ($attributes === null || in_array('alt', $attributes))) {
            $translatableContent->addField('alt', new PlainTextProcessor(['field' => null, 'originalValue' => $element->alt]));
        }
        
        $excludedFields = TransMate::getInstance()->getSettings()->excludedFields;
        
        foreach ($element->fieldLayout->getCustomFields() as $field) {
            $translatableField = $field->translationMethod !== Field::TRANSLATION_METHOD_NONE;
            
            if (in_array($field->handle, $excludedFields, true) || ($attributes !== null && !in_array($field->handle, $attributes, true))) {
                continue;
            }
            
            if ($field instanceof Matrix) {
                // We need to enter the matrix field and process that too, even if it's not "translatable" per se.
                $translatableContent->addField($field->handle, new MatrixProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle), 'source' => $element, 'target' => $targetElement]));
                // tbd: Maybe the same for assets? Not sure.
            } elseif ($translatableField) {
                if ($field instanceof PlainText) {
                    $translatableContent->addField($field->handle, new PlainTextProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle)]));
                } elseif ($field instanceof Table) {
                    $translatableContent->addField($field->handle, new TableProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle)]));
                } elseif ($field instanceof Link) {
                    $translatableContent->addField($field->handle, new LinkProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle)]));
                } elseif ($field instanceof \craft\ckeditor\Field) {
                    $translatableContent->addField($field->handle, new CKEditorProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle), 'source' => $element, 'target' => $targetElement]));
                } elseif ($field instanceof \craft\redactor\Field) {
                    $translatableContent->addField($field->handle, new RedactorProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle), 'source' => $element, 'target' => $targetElement]));
                } elseif ($field instanceof \vaersaagod\linkmate\fields\LinkField) {
                    $translatableContent->addField($field->handle, new LinkMateProcessor(['field' => $field, 'originalValue' => $element->getFieldValue($field->handle)]));
                } else {
                    // some other type of field
                }
            }
        }
        
        return $translatableContent;
    }
    
    public static function translateRefs(string $content, Site $sourceSite, Site $targetSite): ?string
    {
        if (empty($content)) {
            return $content;
        }
        
        $matches = [];
        
        // match link ref like "<a href="{entry:9999@1:url||https://example.com/slug}">link</a>"
        preg_match_all('/{(entry|asset):(\d+)@(\d+):/i', $content, $matches); 

        // should have four arrays: full matches, capture groups 1-3
        if (count($matches) === 4 && count($matches[0])) {
            foreach ($matches[0] as $i => $fullMatch) {
                $type = $matches[1][$i];
                $entryId = $matches[2][$i];
                $siteId = $matches[3][$i];
                $class = null;

                if ($type === 'entry') {
                    $class = Entry::class;
                } elseif ($type === 'asset') {
                    $class = Asset::class;
                }

                if ($sourceSite->id === (int)$siteId && $class) {
                    $targetEntry = $class::find()->siteId($targetSite->id)->status(null)->id($entryId)->one();
                    
                    if ($targetEntry) {
                        $targetSiteId = $targetSite->id;
                        $targetMatch = '{'.$type.':'.$entryId.'@'.$targetSiteId.':';
                        $content = str_replace($fullMatch, $targetMatch, $content);
                    }
                }
            }
        }

        return $content;
    }

    public static function shouldTranslateSlug(Element $element): bool
    {
        if (TransMate::getInstance()->getSettings()->resetSlugMode === 'never') {
            return false;
        }
        
        if ($element instanceof Entry && $element->typeId !== null) {
            $type = $element->type;
            
            if ($type->slugTranslationMethod === Field::TRANSLATION_METHOD_NONE) {
                return false;
            }
        }
        
        // todo: Handle resetSlugMode = 'new' if we decide to...
        
        return true;
    }

    public static function shouldTranslateTitle(Element $element): bool
    {
        if (!$element->getIsTitleTranslatable()) {
            return false;
        }
        
        if ($element instanceof Entry && $element->typeId !== null) {
            if (!empty($element->type->titleFormat) && str_contains($element->type->titleFormat, '{')) {
                return false;
            }
        }
        
        if (empty($element->title)) {
            return false;
        }
        
        // todo: Anything more/other we should account for 
        
        return true;
    }

    /**
     * @param ElementInterface|null $element
     * @param bool $enabledSitesOnly Pass `true` to only return sites the element currently exists in
     * @return array
     * @throws \Throwable
     */
    public static function getAllowedSitesForTranslation(?ElementInterface $element, bool $enabledSitesOnly = false): array
    {
        if (empty($element)) {
            return [];
        }

        $currentUser = self::currentUser();
        if (!$currentUser?->can('transmateCanTranslate')) {
            return [];
        }
        
        $elementSite = Craft::$app->getSites()->getSiteById($element->siteId);
        if (empty($elementSite)) {
            return [];
        }

        $sites = Craft::$app->getSites()->getAllSites(true);
        if ($enabledSitesOnly) {
            $enabledSiteIds = (new Query())
                ->select('siteId')
                ->from('{{%elements_sites}}')
                ->where([
                    'elementId' => $element->getCanonicalId(),
                ])
                ->distinct()
                ->column();
            $sites = array_filter($sites, static fn ($site) => in_array($site->id, $enabledSiteIds));
        }

        if (count($sites) <= 1) {
            return [];
        }

        // If this is an entry, get the section and make sure the user is at least allowed to create drafts in that section
        $section = null;
        if ($element->getRootOwner() instanceof Entry && $section = $element->getRootOwner()->getSection()) {
            if (!$currentUser->can('viewEntries:' . $section->uid)) {
                return [];
            }
        }

        // TODO Check necessary permissions for other element types?

        $allowedSites = [];
        foreach ($sites as $site) {
            if (
                self::areSitesInSameTranslationGroup($elementSite, $site)
                && ($currentUser->can('editSite:'.$elementSite->uid))
                && ($section === null || in_array($elementSite->id, $section->getSiteIds(), true))
            ) {
                $allowedSites[] = $site;
            }
        }

        // Return all the allowed sites; excluding the element's site
        return Collection::make($allowedSites)
            ->where('id', '!=', $element->siteId)
            ->values()
            ->all();
    }
    
    public static function areSitesInSameTranslationGroup($oneSite, $anotherSite): bool
    {
        $settings = TransMate::getInstance()->getSettings();
        
        if ($settings->translationGroups === null) {
            return true;
        }
        
        foreach ($settings->translationGroups as $group) {
            if (in_array($oneSite->handle, $group, true) && in_array($anotherSite->handle, $group, true)) {
                return true;
            }
        }
        
        return false;
    }

    public static function getTranslatableFieldLayoutElement(FieldLayoutElement $fieldLayoutElement): FieldLayoutElement
    {
        if ($fieldLayoutElement instanceof EntryTitleField) {
            return new class($fieldLayoutElement->getAttributes()) extends EntryTitleField {
                use NativeFieldActionsEventTrait;
            };
        }
        
        if ($fieldLayoutElement instanceof AssetTitleField) {
            return new class($fieldLayoutElement->getAttributes()) extends AssetTitleField {
                use NativeFieldActionsEventTrait;
            };
        }
        
        if ($fieldLayoutElement instanceof AltField) {
            return new class($fieldLayoutElement->getAttributes()) extends AltField {
                use NativeFieldActionsEventTrait;
            };
        }

        return $fieldLayoutElement;
    }    
    
    /**
     * @param FieldLayoutElement $fieldLayoutElement
     * @param ElementInterface|null $element
     * @return array|null
     * @throws \Throwable
     */
    public static function getTranslateFieldAction(FieldLayoutElement $fieldLayoutElement, ?ElementInterface $element): ?array
    {
        if (!self::isFieldLayoutElementTranslatableForElement($fieldLayoutElement, $element)) {
            return null;
        }

        // TODO account for disableTranslationProperty so that the translate field action isn't added to unsupported fields
        $translateFromSites = self::getAllowedSitesForTranslation($element, enabledSitesOnly: true);
        if (empty($translateFromSites)) {
            return null;
        }

        // prepare namespace for the purpose of translating
        $namespace = Craft::$app->getView()->getNamespace();
        $label = $fieldLayoutElement->label();
        if ($label === '__blank__') {
            $label = null;
        }

        return [
            'id' => 'transmate-translate-field-' . $fieldLayoutElement->uid,
            'icon' => 'language',
            'label' => Craft::t('transmate', 'Translate from site…'),
            'attributes' => [
                'data' => [
                    'transmate-field-translate' => true,
                    'element-id' => $element->id,
                    'site-id' => $element->siteId,
                    'sites' => json_encode(array_map(static fn (Site $site) => [
                        'id' => $site->id,
                        'name' => $site->name,
                    ], $translateFromSites), JSON_THROW_ON_ERROR),
                    'layout-element' => $fieldLayoutElement->uid,
                    'label' => $label,
                    'namespace' => ($namespace && $namespace !== 'fields')
                        ? StringHelper::removeRight($namespace, '[fields]')
                        : null,
                ],
            ],
        ];
    }

    /**
     * Returns the currently logged-in user.
     *
     * @param bool $autoRenew
     * @return User|null
     * @throws \Throwable
     */
    public static function currentUser(bool $autoRenew = true): ?User
    {
        return Craft::$app->getUser()->getIdentity($autoRenew);
    }

    /**
     * Check if a field layout element is TransMate-translatable for a given element
     *
     * @param FieldLayoutElement $fieldLayoutElement
     * @param ElementInterface|null $element
     * @return bool
     */
    protected static function isFieldLayoutElementTranslatableForElement(FieldLayoutElement $fieldLayoutElement, ?ElementInterface $element): bool
    {
        if ($fieldLayoutElement instanceof AltField) {
            return $fieldLayoutElement->getLayout()->provider?->altTranslationMethod !== Field::TRANSLATION_METHOD_NONE;
        }
        
        if ($fieldLayoutElement instanceof EntryTitleField || $fieldLayoutElement instanceof AssetTitleField) {
            return $fieldLayoutElement->getLayout()->provider?->titleTranslationMethod !== Field::TRANSLATION_METHOD_NONE;
        }
        
        if ($fieldLayoutElement->getField() instanceof Matrix) {
            return true;
        }
        return $fieldLayoutElement->getField()?->getIsTranslatable($element) ?? false;
    }
}

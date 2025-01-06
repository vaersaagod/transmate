<?php

namespace vaersaagod\transmate\helpers;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\models\Site;

use vaersaagod\transmate\models\fieldprocessors\MatrixProcessor;
use vaersaagod\transmate\TransMate;
use vaersaagod\transmate\models\fieldprocessors\CKEditorProcessor;
use vaersaagod\transmate\models\fieldprocessors\LinkProcessor;
use vaersaagod\transmate\models\fieldprocessors\PlainTextProcessor;
use vaersaagod\transmate\models\fieldprocessors\TableProcessor;
use vaersaagod\transmate\models\TranslatableContent;

class TranslateHelper
{

    public static function getTranslatableContentFromElement(Element $element, ?Element $targetElement): TranslatableContent
    {
        $translatableContent = new TranslatableContent();
        
        if (self::shouldTranslateTitle($element)) {
            $translatableContent->addField('title', new PlainTextProcessor(['field' => null, 'originalValue' => $element->title]));
        }
        
        if ($element instanceof Asset && $element->alt && $element->getVolume()->altTranslationMethod !== 'none') {
            $translatableContent->addField('alt', new PlainTextProcessor(['field' => null, 'originalValue' => $element->alt]));
        }
        
        $excludedFields = TransMate::getInstance()->getSettings()->excludedFields;
        
        foreach ($element->fieldLayout->getCustomFields() as $field) {
            $translatableField = $field->translationMethod !== Field::TRANSLATION_METHOD_NONE;
            
            if (in_array($field->handle, $excludedFields, true)) {
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
                    
                // } elseif (get_class($field) === 'craft\redactor\Field') { // Skal vi gidde?
                    
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
}

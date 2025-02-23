<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace vaersaagod\transmate\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Entry;
use yii\base\Exception;

/**
 * MoveToSection represents a Move to Section element action.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class TranslateTo extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('app', 'Translate to…');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: true,
        validateSelection: (selectedItems, elementIndex) => {
          return true;
        },
        activate: (selectedItems, elementIndex) => {
          let elementIds = [];
          for (let i = 0; i < selectedItems.length; i++) {
            elementIds.push(selectedItems.eq(i).find('.element').data('id'));
          }
          
          new Craft.TranslateElementsTo(elementIds, elementIndex.siteId);
        },
    });
})();
JS, [static::class]);

        return null;
    }
}

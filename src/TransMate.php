<?php

namespace vaersaagod\transmate;

use Craft;
use craft\elements\User;
use craft\events\DefineMenuItemsEvent;
use craft\events\FieldEvent;
use craft\helpers\ArrayHelper;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Event;
use craft\base\Field;
use craft\base\FieldLayoutElement;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\CreateFieldLayoutFormEvent;
use craft\events\DefineFieldHtmlEvent;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\log\MonologTarget;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\View;
use vaersaagod\transmate\actions\TranslateTo;
use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\models\Settings;
use vaersaagod\transmate\services\Translate;
use vaersaagod\transmate\web\twig\CpExtension;

/**
 * TransMate plugin
 *
 * @method static TransMate getInstance()
 * @method Settings getSettings()
 *
 * @property  Translate $translate
 *
 * @property-read Settings $settings
 */
class TransMate extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;

    public array $translatedElements = [];

    public static function config(): array
    {
        return [
            'components' => [
                'translate' => Translate::class
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Register a custom log target, keeping the format as simple as possible.
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'transmate',
            'categories' => ['transmate', 'vaersaagod\\transmate\\*'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            if (Craft::$app->request->getIsCpRequest() && !Craft::$app->getRequest()->getIsLoginRequest()) {
                Craft::$app->view->registerAssetBundle(TransmateBundle::class);
                Craft::$app->view->registerTwigExtension(new CpExtension());
            }
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {
        // Auto translate handler
        if (!empty($this->getSettings()->autoTranslate)) {
            Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function (ElementEvent $event) {
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if ($element->propagating || $element->resaving) { // Only trigger for the original site saved
                    return;
                }

                if (!isset($this->translatedElements[$key])) {
                    $this->translatedElements[$key] = true;
                    TransMate::getInstance()->translate->maybeAutoTranslate($element);
                }
            });
        }

        // User permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'TransMate',
                    'permissions' => [
                        'transmateCanTranslate' => [
                            'label' => 'Can translate content',
                        ],
                    ],
                ];
            }
        );

        // Buttons
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_ADDITIONAL_BUTTONS,
            function (DefineHtmlEvent $event) {
                $element = $event->sender;

                if ($element instanceof User) {
                    return;
                }

                $template = Craft::$app->getView()->renderTemplate('transmate/action-buttons', [
                    'element' => $element,
                    'pluginSettings' => $this->getSettings()
                ]);

                $event->html .= $template;
            }
        );

        // Element action
        Event::on(
            Element::class,
            Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) {
                $event->actions[] = TranslateTo::class;
            }
        );

        // Add translate field action to field layout elements
        // We wrap this in a FieldLayout::EVENT_CREATE_FORM event to access the element (which unfortunately is not exposed for the new Field::EVENT_DEFINE_ACTION_MENU_ITEMS event in Craft 5.7)
        Event::on(
            Field::class,
            Field::EVENT_DEFINE_INPUT_HTML,
            static function (DefineFieldHtmlEvent $event) {
                if (!$event->sender instanceof Field || $event->static || $event->inline) {
                    return;
                }

                $element = $event->element;
                if (!$element instanceof ElementInterface) {
                    return;
                }

                $layoutElement = $event->sender->layoutElement;
                if (!$layoutElement instanceof FieldLayoutElement) {
                    return;
                }

                Event::on(
                    Field::class,
                    Field::EVENT_DEFINE_ACTION_MENU_ITEMS,
                    static function (DefineMenuItemsEvent $event) use ($element, $layoutElement) {
                        if ($event->sender?->layoutElement->uid !== $layoutElement->uid) {
                            return;
                        }

                        $translateAction = TranslateHelper::getTranslateFieldAction($layoutElement, $element);
                        if (empty($translateAction)) {
                            return;
                        }

                        array_unshift($event->items, $translateAction);
                    }
                );
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

}

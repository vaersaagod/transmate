<?php

namespace vaersaagod\transmate;

use Craft;
use craft\elements\User;
use craft\events\DefineFieldActionsEvent;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Event;
use craft\base\FieldLayoutElement;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\DefineHtmlEvent;
use craft\events\ElementEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\ElementHelper;
use craft\log\MonologTarget;
use craft\services\Elements;
use craft\services\UserPermissions;

use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;

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

                if ($element instanceof User || ElementHelper::isRevision($element)) {
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
        Event::on(
            BaseField::class,
            BaseField::EVENT_DEFINE_ACTION_MENU_ITEMS,
            static function (DefineFieldActionsEvent $event) {
                if ($event->static || !$event->sender instanceof FieldLayoutElement) {
                    return;
                }

                $translateAction = TranslateHelper::getTranslateFieldAction($event->sender, $event->element);
                if (empty($translateAction)) {
                    return;
                }

                // Try to put it before the "Field settings" action, if it exists
                $fieldSettingsActionIndex = array_search(true, array_map(fn($id) => str_starts_with($id, 'action-edit-'), array_column($event->items, 'id')));
                if ($fieldSettingsActionIndex !== false) {
                    array_splice($event->items, $fieldSettingsActionIndex, 0, [$translateAction]);
                } else {
                    $event->items[] = $translateAction;
                }
            }
        );

    }

    /**
     * @return Model|null
     * @throws \yii\base\InvalidConfigException
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

}

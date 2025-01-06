<?php

namespace vaersaagod\transmate;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\ElementEvent;
use craft\log\MonologTarget;
use craft\services\Elements;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;

use vaersaagod\transmate\models\Settings;
use vaersaagod\transmate\services\Translate;

/**
 * TransMate plugin
 *
 * @method static TransMate getInstance()
 * @method Settings getSettings()
 *
 * @property  Translate    $translate
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
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    private function attachEventHandlers(): void
    {
        if (!empty($this->getSettings()->autoTranslate)) {
            Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function(ElementEvent $event) {
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
    }


}

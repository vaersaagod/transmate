<?php

namespace vaersaagod\transmate\console\controllers;

use Craft;
use craft\console\Controller;

use vaersaagod\transmate\TransMate;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;


class TranslateController extends Controller
{

    /** @var string */
    public $defaultAction = 'translate';

    public null|int $elementId = null;
    public null|string|int $fromSite = null;
    public null|string|int $toSite = null;
    public null|string|int $section = null;
    public string $language = '';
    public null|string $since = null;

    /**
     * @param $actionID
     *
     * @return array|string[]
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'elementId';
        $options[] = 'section';
        $options[] = 'fromSite';
        $options[] = 'toSite';
        $options[] = 'language';
        $options[] = 'since';

        return $options;
    }

    public function actionTranslate(): int
    {
        if ($this->fromSite === null || $this->toSite === null || ($this->elementId === null && $this->section === null)) {
            $this->stderr("Missing parameters; fromSite, toSite and elementId or section is required.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $fromSite = is_string($this->fromSite) ? Craft::$app->sites->getSiteByHandle($this->fromSite) : Craft::$app->sites->getSiteById($this->fromSite);
        $toSite = is_string($this->toSite) ? Craft::$app->sites->getSiteByHandle($this->toSite) : Craft::$app->sites->getSiteById($this->toSite);

        if ($fromSite === null || $toSite === null) {
            if ($fromSite === null) {
                $this->stderr("Could not find site (from) with value: ".$this->fromSite.PHP_EOL, BaseConsole::FG_RED);
            }
            if ($toSite === null) {
                $this->stderr("Could not find site (to) with value: ".$this->toSite.PHP_EOL, BaseConsole::FG_RED);
            }

            $this->stderr(PHP_EOL, BaseConsole::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->language === '') {
            $this->language = $toSite->language;
        }


        if ($this->elementId !== null) {
            $element = Craft::$app->elements->getElementById($this->elementId, null, $fromSite->id);

            if (!$element) {
                $this->stderr("Element with ID $this->elementId not found.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);

                return ExitCode::UNSPECIFIED_ERROR;
            }

            $translatedElement = TransMate::getInstance()->translate->translateElement($element, $fromSite, $toSite, $this->language);

            if ($translatedElement === null) {
                $this->stderr("Element could not be translated.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);

                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout("Element `$translatedElement->title` translated!".PHP_EOL.PHP_EOL, BaseConsole::FG_GREEN);
        } else {
            // todo : section
        }
        
        // todo: since parameter ^

        return ExitCode::OK;
    }

}

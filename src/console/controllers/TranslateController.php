<?php

namespace vaersaagod\transmate\console\controllers;

use Craft;
use craft\console\Controller;

use craft\elements\Entry;
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
    public bool $asDraft = false;

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
        $options[] = 'asDraft';

        return $options;
    }

    public function actionTranslate(): int
    {
        if ($this->fromSite === null || $this->toSite === null || ($this->elementId === null && $this->section === null)) {
            $this->stderr("Missing parameters; fromSite, toSite and elementId or section is required.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $fromSite = is_string($this->fromSite) ? Craft::$app->sites->getSiteByHandle($this->fromSite) : Craft::$app->sites->getSiteById($this->fromSite);
        
        if (is_string($this->toSite) && str_contains($this->toSite, ',')) {
            $toSites = explode(',', $this->toSite);
        } else {
            $toSites = [$this->toSite];
        }
        
        foreach ($toSites as $toSiteElem) {
            $toSite = is_string($toSiteElem) ? Craft::$app->sites->getSiteByHandle($toSiteElem) : Craft::$app->sites->getSiteById($toSiteElem);

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

            $language = !empty($this->language) ? $this->language : $toSite->language;
            
            if ($this->elementId !== null) {
                $element = Craft::$app->elements->getElementById($this->elementId, null, $fromSite->id);

                if (!$element) {
                    $this->stderr("Element with ID $this->elementId not found.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);

                    return ExitCode::UNSPECIFIED_ERROR;
                }

                $translatedElement = TransMate::getInstance()->translate->translateElement($element, $fromSite, $toSite, $language, $this->asDraft ? 'draft' : 'current');

                if ($translatedElement === null) {
                    $this->stderr("Element could not be translated.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);

                    return ExitCode::UNSPECIFIED_ERROR;
                }

                $this->stdout("Element `$translatedElement->title` translated!".PHP_EOL.PHP_EOL, BaseConsole::FG_GREEN);
            } else {
                $elements = Entry::find()->section($this->section)->status(null)->siteId($fromSite->id)->all();

                foreach ($elements as $element) {
                    $translatedElement = TransMate::getInstance()->translate->translateElement($element, $fromSite, $toSite, $language, $this->asDraft ? 'draft' : 'current');

                    if ($translatedElement === null) {
                        $this->stderr("Element `$element->title` could not be translated.".PHP_EOL, BaseConsole::FG_RED);
                    } else {
                        $this->stdout("Element `$translatedElement->title` translated to site `$toSite->name`!".PHP_EOL, BaseConsole::FG_GREEN);
                    }
                }
            }
            // todo: since parameter ^
        }

        return ExitCode::OK;
    }

}

<?php

namespace vaersaagod\transmate\console\controllers;

use Craft;
use craft\console\Controller;

use craft\elements\Entry;
use DeepL\GlossaryEntries;
use vaersaagod\transmate\models\DeepLSettings;
use vaersaagod\transmate\TransMate;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;


class DeeplGlossaryController extends Controller
{

    /** @var string */
    public $defaultAction = 'create';

    public string $name = '';
    public string $sourceLanguage = '';
    public string $targetLanguage = '';
    public string $entries = '';
    public string $glossaryId = '';

    /**
     * @param $actionID
     *
     * @return array|string[]
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'name';
        $options[] = 'sourceLanguage';
        $options[] = 'targetLanguage';
        $options[] = 'entries';
        $options[] = 'glossaryId';

        return $options;
    }
    
    public function actionCreate()
    {
        if (empty($this->name) || empty($this->sourceLanguage) || empty($this->targetLanguage) || empty($this->entries)) {
            $this->stderr("Required parameter missing.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $deeplConfig = TransMate::getInstance()->getSettings()->translatorConfig['deepl'] ?? [];
        
        if (empty($deeplConfig)) {
            $this->stderr("No DeepL config found".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $deeplConfig = new DeepLSettings($deeplConfig);
        $translator = new \DeepL\Translator($deeplConfig->apiKey);
        
        $glossaries = $translator->listGlossaries();
        
        // TODO: loop over and get glossaries with same name and source/target, get entries and merge.
        
        $entries = str_replace(['\n', '\t'], ["\n", "\t"], $this->entries);
        $info = $translator->createGlossary($this->name, $this->sourceLanguage, $this->targetLanguage, GlossaryEntries::fromTsv($entries));
        
        $this->stdout("Glossary with ID $info->glossaryId created!".PHP_EOL, BaseConsole::FG_GREEN);
        return ExitCode::OK;
    }

    public function actionDelete()
    {
        if (empty($this->glossaryId)) {
            $this->stderr("Required parameter missing.".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $deeplConfig = TransMate::getInstance()->getSettings()->translatorConfig['deepl'] ?? [];
        
        if (empty($deeplConfig)) {
            $this->stderr("No DeepL config found".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $deeplConfig = new DeepLSettings($deeplConfig);
        $translator = new \DeepL\Translator($deeplConfig->apiKey);
        
        $translator->deleteGlossary($this->glossaryId);
        return ExitCode::OK;
    }

    public function actionList()
    {
        $deeplConfig = TransMate::getInstance()->getSettings()->translatorConfig['deepl'] ?? [];
        
        if (empty($deeplConfig)) {
            $this->stderr("No DeepL config found".PHP_EOL.PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $deeplConfig = new DeepLSettings($deeplConfig);
        $translator = new \DeepL\Translator($deeplConfig->apiKey);
        
        $glossaries = $translator->listGlossaries();
        Craft::dd($glossaries);
        return ExitCode::OK;
    }
}

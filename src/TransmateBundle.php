<?php
namespace vaersaagod\transmate;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TransmateBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@vaersaagod/transmate/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'TranslateElementsTo.js',
            'transmate.js',
        ];

        $this->css = [
            'transmate.css',
        ];

        parent::init();
    }
}

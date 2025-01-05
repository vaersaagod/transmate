<?php

namespace vaersaagod\transmate\controllers;

use craft\web\Controller;

use vaersaagod\transmate\TransMate;

use yii\web\Response;

class DefaultController extends Controller
{

    /** @var array|bool|int */
    public array|bool|int $allowAnonymous = false;

    public function actionSomething(): ?Response
    {
        

    }


}

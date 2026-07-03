<?php

namespace vaersaagod\transmate\controllers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\User;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\Cp;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\web\Controller;

use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\jobs\TranslateJob;
use vaersaagod\transmate\TransMate;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class DefaultController extends Controller
{

    /** @var array|bool|int */
    public array|bool|int $allowAnonymous = false;

    /**
     * @inheritdoc
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // All actions mutate content or trigger paid API calls, and are only ever
        // called via POST from the plugin's CP JavaScript.
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission('transmateCanTranslate');

        return true;
    }

    public function actionTranslateFromSite()
    {
        $elementId = (int)$this->request->getRequiredParam('elementId');
        $elementSiteId = (int)$this->request->getRequiredParam('elementSiteId');
        $fromSiteId = (int)$this->request->getParam('fromSiteId');

        $currentSite = \Craft::$app->getSites()->getSiteById($elementSiteId);
        $fromSite = \Craft::$app->getSites()->getSiteById($fromSiteId);

        if ($currentSite === null) {
            throw new NotFoundHttpException("Current site with ID $elementSiteId not found");
        }

        if ($fromSite === null) {
            throw new NotFoundHttpException("From site with ID $fromSiteId not found");
        }

        $fromElement = \Craft::$app->getElements()->getElementById($elementId, null, $fromSite->id);

        if ($fromElement === null) {
            throw new NotFoundHttpException("Element not found");
        }

        if (!TranslateHelper::canTranslate($fromElement, $fromSite, $currentSite)) {
            throw new ForbiddenHttpException('You are not allowed to translate this element into the requested site.');
        }

        $translatedElement = TransMate::getInstance()->translate->translateElement($fromElement, $fromSite, $currentSite, null, 'provisional');

        if ($translatedElement !== null) {
            $successMessage = \Craft::t('transmate', 'Element translated!');
            $this->setSuccessFlash($successMessage);

            \Craft::$app->getSession()->broadcastToJs([
                'event' => 'saveElement',
                'id' => $elementId,
            ]);

            return $this->asSuccess($successMessage);
        }

        return $this->asFailure(\Craft::t('transmate', 'An error occurred when trying to translate element.'));
    }

    public function actionTranslateElementsToSites(): ?Response
    {
        $fromSiteId = (int)$this->request->getRequiredParam('siteId');
        $elementIds = $this->request->getRequiredParam('elementIds');
        $siteIds = $this->request->getRequiredParam('siteIds');
        $saveAsDraft = $this->request->getRequiredParam('saveAsDraft') === 'yes';

        $fromSite = \Craft::$app->getSites()->getSiteById($fromSiteId);
        if ($fromSite === null) {
            throw new BadRequestHttpException("Invalid site ID: $fromSiteId");
        }

        $queue = \Craft::$app->getQueue();
        $jobCount = 0;

        foreach ($elementIds as $elementId) {
            $element = \Craft::$app->getElements()->getElementById((int)$elementId, null, $fromSite->id);
            if ($element === null) {
                throw new NotFoundHttpException("Element with ID $elementId not found");
            }

            foreach ($siteIds as $toSiteId) {
                $toSite = \Craft::$app->getSites()->getSiteById((int)$toSiteId);
                if ($toSite === null) {
                    throw new BadRequestHttpException("Invalid site ID: $toSiteId");
                }

                if (!TranslateHelper::canTranslate($element, $fromSite, $toSite)) {
                    throw new ForbiddenHttpException('You are not allowed to translate this element into the requested site.');
                }

                $jobId = $queue->push(new TranslateJob([
                    'description' => \Craft::t('transmate', 'Translating content'),
                    'elementId' => $elementId,
                    'fromSiteId' => $fromSiteId,
                    'toSiteId' => $toSiteId,
                    'saveMode' => $saveAsDraft ? 'draft' : 'current',
                ]));

                $jobCount += 1;
            }
        }

        return $this->asSuccess(\Craft::t(
            'transmate',
            '{count} entries has been queued for translation.',
            ['count' => $jobCount]
        ));
    }

    public function actionTranslateToSiteModalData(): ?Response
    {
        $elementIds = $this->request->getRequiredParam('elementIds');
        $siteId = (int)$this->request->getRequiredParam('siteId');

        $user = \Craft::$app->getUser()->getIdentity();
        $sites = \Craft::$app->getSites()->getAllSites();
        $currentSite = \Craft::$app->getSites()->getSiteById($siteId);

        $element = \Craft::$app->getElements()->getElementById($elementIds[0]);

        if ($element instanceof Entry) {
            $section = $element->section;
        } else {
            $section = null;
        }

        $allowedSites = [];

        foreach ($sites as $site) {
            if ($user->can('editSite:'.$site->uid) && TranslateHelper::areSitesInSameTranslationGroup($currentSite, $site) && ($section === null || in_array($site->id, $section->getSiteIds(), true))) {
                $allowedSites[] = $site;
            }
        }

        $listHtml = '';

        foreach ($allowedSites as $site) {
            if ($site->id !== $siteId) {
                $listHtml .= Cp::chipHtml($site, [
                    'selectable' => true,
                    'class' => 'fullwidth',
                ]);
            }
        }

        $listHtml .= Cp::checkboxFieldHtml([
            'checkboxLabel' => \Craft::t('transmate', 'Save translated entry as draft'),
            'checked' => TransMate::getInstance()->getSettings()->saveMode === 'draft',
            'name' => 'transmateSaveAsDraft',
            'fieldClass' => 'transmate-modal__draft-checkbox'
        ]);

        return $this->asJson(['listHtml' => $listHtml]);
    }

    public function actionTranslateFieldFromSite(): Response
    {
        $elementId = (int)$this->request->getRequiredBodyParam('elementId');
        $siteId = (int)$this->request->getRequiredBodyParam('siteId');
        $element = \Craft::$app->getElements()->getElementById($elementId, siteId: $siteId);

        if (!$element || $element->getIsRevision()) {
            throw new BadRequestHttpException('No element was identified by the request.');
        }

        $fromSiteId = (int)$this->request->getRequiredBodyParam('fromSiteId');
        $fromSite = Craft::$app->getSites()->getSiteById($fromSiteId);
        if (!$fromSite) {
            throw new BadRequestHttpException("Invalid site ID: $fromSiteId");
        }

        $fromElement = Craft::$app->getElements()->getElementById($elementId, $element::class, $fromSite->id);
        if ($fromElement === null) {
            throw new NotFoundHttpException("Element not found");
        }

        if (!TranslateHelper::canTranslate($element, $fromSite, $element->getSite())) {
            throw new ForbiddenHttpException('You are not allowed to translate this element into the requested site.');
        }

        $layoutElementUid = $this->request->getRequiredBodyParam('layoutElementUid');
        $layoutElement = $element->getFieldLayout()->getElementByUid($layoutElementUid);
        if (!$layoutElement instanceof BaseField) {
            throw new BadRequestHttpException("Invalid layout element UUID: $layoutElementUid");
        }
        if ($layoutElement instanceof CustomField) {
            $fieldHandle = $layoutElement->getField()->handle;
        } else {
            $fieldHandle = $layoutElement->attribute();
        }

        // TODO maybe translateElement() itself should be wrapped in a transaction.
        // Would save us a lot of trouble in cases where something fails, somewhere.
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $translatedElement = TransMate::getInstance()->translate->translateElement(
                $fromElement,
                $fromSite,
                $element->getSite(),
                saveMode: 'provisional',
                attributes: [$fieldHandle],
            );

            $transaction->commit();
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            $transaction->rollBack();

            return $this->asFailure(
                message: Craft::t('transmate', 'An error occurred when trying to translate the field.')
            );
        }

        $namespace = $this->request->getBodyParam('namespace');

        $view = $this->getView();
        $html = $view->namespaceInputs(fn() => $layoutElement->formHtml($translatedElement), $namespace);

        if ($html) {
            $html = Html::modifyTagAttributes($html, [
                'data' => [
                    'layout-element' => $layoutElement->uid,
                ],
            ]);
        }

        $label = $this->request->getBodyParam('layoutElementLabel');
        if ($label) {
            $message = Craft::t('transmate', '{label} translated.', ['label' => $label]);
        } else {
            $message = Craft::t('transmate', 'Field translated.');
        }

        $data = [
            'fieldHtml' => $html,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
            'canonicalId' => $translatedElement->getCanonicalId(),
            'elementId' => $translatedElement->id,
            'draftId' => $translatedElement->draftId,
            'timestamp' => Craft::$app->getFormatter()->asTimestamp($translatedElement->dateUpdated, 'short', true),
            'modifiedAttributes' => $element->getModifiedAttributes(),
        ];
        
        if ($translatedElement->getIsDraft()) {
            $data += [
                'creator' => $translatedElement->getCreator()?->getName(),
                'draftName' => $translatedElement->draftName,
                'draftNotes' => $translatedElement->draftNotes,
            ];
        }

        return $this->asSuccess(
            $message,
            $data,
            $this->getPostedRedirectUrl($translatedElement),
            [
                'details' => Cp::elementChipHtml($translatedElement),
            ]
        );
    }

}

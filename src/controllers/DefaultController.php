<?php

namespace vaersaagod\transmate\controllers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
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
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class DefaultController extends Controller
{

    /** @var array|bool|int */
    public array|bool|int $allowAnonymous = false;

    public function actionTranslateFromSite()
    {
        $this->requireCpRequest();

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
        $this->requireCpRequest();

        $fromSiteId = (int)$this->request->getRequiredParam('siteId');
        $elementIds = $this->request->getRequiredParam('elementIds');
        $siteIds = $this->request->getRequiredParam('siteIds');
        $saveAsDraft = $this->request->getRequiredParam('saveAsDraft') === 'yes';

        $queue = \Craft::$app->getQueue();
        $jobCount = 0;

        foreach ($elementIds as $elementId) {
            foreach ($siteIds as $toSiteId) {
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
        $this->requireCpRequest();

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
        $this->requireCpRequest();

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
                message: $e->getMessage() // TODO would be cool with friendlier error messages
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

        return $this->asSuccess(
            $message,
            [
                'fieldHtml' => $html,
                'headHtml' => $view->getHeadHtml(),
                'bodyHtml' => $view->getBodyHtml(),
                'canonicalId' => $translatedElement->getCanonicalId(),
                'elementId' => $translatedElement->id,
                'draftId' => $translatedElement->draftId,
                'timestamp' => Craft::$app->getFormatter()->asTimestamp($translatedElement->dateUpdated, 'short', true),
                'creator' => $translatedElement->getCreator()?->getName(),
                'draftName' => $translatedElement->draftName,
                'draftNotes' => $translatedElement->draftNotes,
                'modifiedAttributes' => $element->getModifiedAttributes(),
            ],
            $this->getPostedRedirectUrl($translatedElement),
            [
                'details' => Cp::elementChipHtml($translatedElement),
            ]
        );

    }

}

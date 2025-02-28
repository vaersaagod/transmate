<?php

namespace vaersaagod\transmate\controllers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
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

    /*
    public function actionSidebarTranslate()
    {
        $this->requireCpRequest();

        $type = $this->request->getRequiredParam('type');
        $entryId = (int)$this->request->getRequiredParam('entryId');
        $entrySiteId = (int)$this->request->getRequiredParam('entrySiteId');
        $fromSiteHandle = $this->request->getParam('fromSiteHandle');
        $toSiteHandles = $this->request->getParam('toSiteHandles');
        $saveAsDraft = $this->request->getParam('saveAsDraft') === '1';

        $currentSite = \Craft::$app->getSites()->getSiteById($entrySiteId);

        if ($currentSite === null) {
            throw new NotFoundHttpException("Site with ID $entrySiteId not found");
        }

        if ($type === 'translateFrom') {
            $fromSite = \Craft::$app->getSites()->getSiteByHandle($fromSiteHandle);

            if ($fromSite === null) {
                throw new NotFoundHttpException("Site with handle $fromSiteHandle not found");
            }

            $fromEntry = Entry::find()->id($entryId)->siteId($fromSite->id)->status(null)->one();

            if ($fromEntry === null) {
                throw new NotFoundHttpException("Entry not found");
            }

            $translatedEntry = TransMate::getInstance()->translate->translateElement($fromEntry, $fromSite, $currentSite, null, 'provisional');

            if ($translatedEntry !== null) {
                $successMessage = \Craft::t('transmate', 'Entry translated!');
                $this->setSuccessFlash($successMessage);

                \Craft::$app->getSession()->broadcastToJs([
                    'event' => 'saveElement',
                    'id' => $entryId,
                ]);

                return $this->asSuccess($successMessage);
            } else {
                return $this->asFailure(\Craft::t('transmate', 'An error occurred when trying to translate entry.'));
            }
        }

        if ($type === 'translateTo') {
            $queue = \Craft::$app->getQueue();
            $jobCount = 0;

            foreach ($toSiteHandles as $toSiteHandle) {
                $toSite = \Craft::$app->getSites()->getSiteByHandle($toSiteHandle);

                if ($toSite === null) {
                    throw new NotFoundHttpException("Site with handle $toSiteHandle not found");
                }

                $jobId = $queue->push(new TranslateJob([
                    'description' => \Craft::t('transmate', 'Translating content'),
                    'elementId' => $entryId,
                    'fromSiteId' => $entrySiteId,
                    'toSiteId' => $toSite->id,
                    'saveMode' => $saveAsDraft ? 'draft' : 'current',
                ]));

                $jobCount += 1;
            }

            return $this->asSuccess(\Craft::t(
                'transmate',
                'Entry has been queued for translation to {count} sites.',
                ['count' => $jobCount]
            ));
        }


        return $this->asFailure(\Craft::t(
            'transmate',
            'Unknown translate type.',
            []
        ));
    }
    */

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

        // Make sure we are dealing with a provisional draft
        if (!$element->getIsDraft()) {
            $element = Craft::$app->drafts->createDraft($element, Craft::$app->getUser()->getIdentity()?->getId(), provisional: true);
        }

        $translatedElement = TransMate::getInstance()->translate->translateElement(
            $fromElement,
            $fromSite,
            $element->getSite(),
            saveMode: 'provisional',
            attributes: [$fieldHandle],
        );

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

        Craft::$app->getSession()->broadcastToJs([
            'event' => 'saveElement',
            'id' => $element->id,
        ]);

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
            ],
            $this->getPostedRedirectUrl($translatedElement),
            [
                'details' => Cp::elementChipHtml($translatedElement),
            ]
        );

    }

}

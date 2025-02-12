<?php

namespace vaersaagod\transmate\controllers;

use craft\elements\Entry;
use craft\helpers\Cp;
use craft\web\Controller;

use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\jobs\TranslateJob;
use vaersaagod\transmate\TransMate;

use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{

    /** @var array|bool|int */
    public array|bool|int $allowAnonymous = false;

    public function actionTranslateFromSite()
    {
        $this->requireCpRequest();
    }

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

    public function actionTranslateElementsToSites(): ?Response
    {
        $this->requireCpRequest();

        $fromSiteId = (int)$this->request->getRequiredParam('siteId');
        $entryIds = $this->request->getRequiredParam('entryIds');
        $siteIds = $this->request->getRequiredParam('siteIds');
        $saveAsDraft = $this->request->getRequiredParam('saveAsDraft') === 'yes';

        $queue = \Craft::$app->getQueue();
        $jobCount = 0;

        foreach ($entryIds as $entryId) {
            foreach ($siteIds as $toSiteId) {
                $jobId = $queue->push(new TranslateJob([
                    'description' => \Craft::t('transmate', 'Translating content'),
                    'elementId' => $entryId,
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

        $entryIds = $this->request->getRequiredParam('entryIds');
        $siteId = (int)$this->request->getRequiredParam('siteId');
        $currentSectionUid = $this->request->getRequiredParam('currentSectionUid');

        $user = \Craft::$app->getUser()->getIdentity();
        $sites = \Craft::$app->getSites()->getAllSites();
        $currentSite = \Craft::$app->getSites()->getSiteById($siteId);
        $section = \Craft::$app->getEntries()->getSectionByUid($currentSectionUid);

        $allowedSites = [];

        foreach ($sites as $site) {
            if ($user->can('editSite:'.$site->uid) && TranslateHelper::areSitesInSameTranslationGroup($currentSite, $site) && in_array($site->id, $section->getSiteIds(), true)) {
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

}

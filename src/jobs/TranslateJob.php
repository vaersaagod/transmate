<?php

namespace vaersaagod\transmate\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use vaersaagod\transmate\TransMate;
use yii\queue\Queue;

class TranslateJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var null|int
     */
    public ?int $elementId = null;
    
    /**
     * @var null|int
     */
    public ?int $fromSiteId = null;
    
    /**
     * @var null|int
     */
    public ?int $toSiteId = null;
    
    /**
     * @var null|string
     */
    public ?string $saveMode = null;
    

    // Public Methods
    // =========================================================================

    /**
     * @param QueueInterface|Queue $queue
     */
    public function execute($queue): void
    {
        $element = Craft::$app->elements->getElementById($this->elementId, null, $this->fromSiteId);
        $fromSite = Craft::$app->sites->getSiteById($this->fromSiteId);
        $toSite = Craft::$app->sites->getSiteById($this->toSiteId);
        
        TransMate::getInstance()->translate->translateElement($element, $fromSite, $toSite, null, $this->saveMode);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns a default description for [[getDescription()]], if [[description]] isn’t set.
     *
     * @return string|null The default task description
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('transmate', 'Translating content');
    }
}

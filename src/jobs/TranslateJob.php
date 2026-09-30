<?php

namespace vaersaagod\transmate\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use vaersaagod\transmate\TransMate;
use yii\queue\Queue;
use yii\queue\RetryableJobInterface;

class TranslateJob extends BaseJob implements RetryableJobInterface
{
    // Constants
    // =========================================================================

    /**
     * @var int Seconds a single attempt may run before the queue considers it failed
     */
    public const TTR = 300;

    /**
     * @var int Total number of times the job is attempted, including the first run
     */
    public const MAX_ATTEMPTS = 3;

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

    /**
     * @return int
     */
    public function getTtr(): int
    {
        return self::TTR;
    }

    /**
     * @param int $attempt
     * @param \Throwable|null $error
     * @return bool
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < self::MAX_ATTEMPTS;
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

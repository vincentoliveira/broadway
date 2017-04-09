<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\EventHandling;

use Broadway\Domain\DomainEventStream;

/**
 * Asynchronous publishing of events.
 */
class AsyncEventBus implements EventBus
{
    private $eventListeners = [];
    private $queue          = [];
    private $isPublishing   = false;

    /**
     * {@inheritDoc}
     */
    public function subscribe(EventListener $eventListener)
    {
        $this->eventListeners[] = $eventListener;
    }

    /**
     * {@inheritDoc}
     */
    public function publish(DomainEventStream $domainMessages)
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            // -1: error: dispatch synchronously
            $this->dispatch($domainMessages);
        } elseif ($pid === 0) {
            // 0: success: dispatch asynchronously
            $this->dispatch($domainMessages);
            // stop process here
            die;
        } else {
            // continue process without handling this message
        }
    }

    /**
     * Dispatches the events from the domain event stream to the listeners.
     *
     * @param DomainEventStream $domainMessages
     */
    public function dispatch(DomainEventStream $domainMessages)
    {
        foreach ($domainMessages as $domainMessage) {
            $this->queue[] = $domainMessage;
        }

        if (! $this->isPublishing) {
            $this->isPublishing = true;

            try {
                while ($domainMessage = array_shift($this->queue)) {
                    foreach ($this->eventListeners as $eventListener) {
                        $eventListener->handle($domainMessage);
                    }
                }
            } finally {
                $this->isPublishing = false;
            }
        }
    }
}
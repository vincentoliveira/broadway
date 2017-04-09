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
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\TestCase;


/**
 * @group async-event-bus
 */
class AsyncEventBusTest extends TestCase
{
    private $eventBus;

    public function setUp()
    {
        $this->eventBus = new AsyncEventBus();
    }
    /**
     * @test
     */
    public function it_subscribes_an_event_listener()
    {
        $domainMessage = $this->createDomainMessage(['foo' => 'bar']);

        $domainEventStream = new DomainEventStream([$domainMessage]);

        $eventListener = new AsyncEventBusTestListener($this->eventBus, $domainEventStream);

        $this->eventBus->subscribe($eventListener);
        $this->eventBus->publish($domainEventStream);

        // Not handled yet
        $this->assertFalse($eventListener->wasHandled(), 'AsyncEventBusTest: Failed to dispatch a domain message.');

        sleep(1);

        // Now it should hqve been handled
        $this->assertTrue($eventListener->wasHandled(), 'AsyncEventBusTest: Failed to dispatch a domain message.');
    }

    private function createDomainMessage($payload)
    {
        return DomainMessage::recordNow(1, 1, new Metadata([]), new SimpleEventBusTestEvent($payload));
    }
}

class AsyncEventBusTestEvent
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
}

class AsyncEventBusTestListener implements EventListener
{
    private $eventBus;
    private $handled = false;
    private $publishableStream;
    private $file;

    public function __construct($eventBus, $publishableStream)
    {
        $this->eventBus          = $eventBus;
        $this->publishableStream = $publishableStream;
        $this->file              = tmpfile();
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    public function handle(DomainMessage $domainMessage)
    {
        if (! $this->handled) {
            $this->eventBus->publish($this->publishableStream);
            $this->handled = true;

            fwrite($this->file, 1);
        }
    }

    public function wasHandled()
    {
        fseek($this->file, 0);
        $data = fread($this->file, 1024);

        return !empty($data);
    }
}
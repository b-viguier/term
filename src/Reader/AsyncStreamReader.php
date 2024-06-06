<?php

declare(strict_types=1);

namespace PhpTui\Term\Reader;

use PhpTui\Term\Reader;
use Revolt\EventLoop;
use RuntimeException;
use WeakMap;

final class AsyncStreamReader implements Reader
{
    private string $callbackId;

    /** @var WeakMap<EventLoop\Suspension<string>,true> suspensions */
    private WeakMap $suspensions;
    /**
     * @param resource $stream
     */
    private function __construct(private $stream)
    {
        \assert(\class_exists(EventLoop::class), '\Revolt\EventLoop class not found');
        $this->suspensions = new WeakMap();
        $this->callbackId = EventLoop::onReadable($this->stream, $this->readStream(...));
    }

    public function __destruct()
    {
        EventLoop::cancel($this->callbackId);
    }

    public static function tty(): self
    {
        \stream_set_blocking(STDIN, false) || throw new RuntimeException('Failed to set stream non-blocking');

        return new self(STDIN);
    }

    public function read(): ?string
    {
        /** @var EventLoop\Suspension<string> $suspension */
        $suspension = EventLoop::getSuspension();
        $this->suspensions[$suspension] = true;

        return $suspension->suspend();
    }

    public function disable(): void
    {
        EventLoop::disable($this->callbackId);
    }

    public function enable(): void
    {
        EventLoop::enable($this->callbackId);
    }

    private function isStreamDead(): bool
    {
        return !is_resource($this->stream) || @feof($this->stream);
    }

    private function resumeSuspensions(string $data): void
    {
        /** @var  EventLoop\Suspension<string> $suspension */
        foreach ($this->suspensions as $suspension => $_) {
            $suspension->resume($data);
        }
    }

    private function readStream(): void
    {
        $newData = stream_get_contents($this->stream);
        if ($newData !== false && $newData !== '') {
            $this->resumeSuspensions($newData);
        } elseif ($this->isStreamDead()) {
            EventLoop::cancel($this->callbackId);
        }
    }
}

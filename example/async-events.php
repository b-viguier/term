<?php

use PhpTui\Term\Actions;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Term\Reader\AsyncStreamReader;
use PhpTui\Term\EventProvider\AggregateEventProvider;
use PhpTui\Term\EventProvider\SyncTtyEventProvider;
use PhpTui\Term\EventProvider\SignalEventProvider;
use PhpTui\Term\EventParser;

require __DIR__ . '/../vendor/autoload.php';

$asyncReader = AsyncStreamReader::tty();

$terminal = Terminal::new(
    eventProvider: new AggregateEventProvider([
        new SyncTtyEventProvider($asyncReader, EventParser::new()),
        SignalEventProvider::registered(),
    ])
);
$terminal->enableRawMode();
$terminal->execute(Actions::printString('Entering event loop, press ESC to exit'));
$terminal->execute(Actions::moveCursorNextLine());
$terminal->execute(Actions::enableMouseCapture());

try {
    $timerId = \Revolt\EventLoop::repeat(1, function () use ($terminal) {
        static $tick = 0;
        $terminal->queue(
            Actions::setTitle(\sprintf('Background counter %d', ++$tick)),
        );
        $terminal->flush();
    });

    // enter the event loop
    eventLoop($terminal);
} finally {
    // restore the terminal to it's previous state
    $terminal->execute(Actions::disableMouseCapture());
    $terminal->disableRawMode();
    // cancel the timer
    \Revolt\EventLoop::cancel($timerId);
}

function eventLoop(Terminal $terminal): void
{
    // start the loop!
    while (true) {

        // drain any events from the event buffer and process them
        while ($event = $terminal->events()->next()) {

            // queue multiple actions
            $terminal->queue(
                Actions::printString($event->__toString()),
                Actions::moveCursorNextLine(),
            );

            // flush the buffer and write the actions to the terminal
            $terminal->flush();

            // events can be of different types containing different information
            if ($event instanceof CodedKeyEvent) {
                if ($event->code === KeyCode::Esc) {
                    return;
                }
            }

            // most events also have modifiers so you can see if the event happened
            // with a key modifier such as CONTROL or ALT
            if ($event instanceof CharKeyEvent) {
                if ($event->char === 'c' && $event->modifiers === KeyModifiers::CONTROL) {
                    return;
                }
            }
        }
        usleep(10000);
    }
}

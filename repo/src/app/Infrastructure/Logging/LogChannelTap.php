<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Illuminate\Log\Logger;

class LogChannelTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        // Add the sensitive data scrubber processor
        $monolog->pushProcessor(new SensitiveDataScrubber());

        // Set the structured formatter on all handlers
        $formatter = new StructuredLogFormatter();
        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter($formatter);
        }
    }
}

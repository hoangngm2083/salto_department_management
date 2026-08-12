<?php

namespace App\Services\Export;

use App\Enums\ExportType;
use RuntimeException;

class ExportStrategyResolver
{
    public function resolve(ExportType $type): AbstractExportHandler
    {
        $handlerClass = config("exports.handlers.{$type->value}");

        if ($handlerClass === null) {
            throw new RuntimeException("No export handler registered for type [{$type->value}].");
        }

        return app($handlerClass);
    }
}

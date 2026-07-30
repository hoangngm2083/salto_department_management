<?php

namespace App\Services\Import;

use App\Enums\ImportType;
use RuntimeException;

class ImportStrategyResolver
{
    public function resolve(ImportType $type): AbstractImportHandler
    {
        $handlerClass = config("imports.handlers.{$type->value}");

        if ($handlerClass === null) {
            throw new RuntimeException("No import handler registered for type [{$type->value}].");
        }

        return app($handlerClass);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Exceptions;

use App\Modules\CsvImports\Models\Import;
use InvalidArgumentException;

final class ExactFileDuplicateException extends InvalidArgumentException
{
    public function __construct(
        public readonly Import $existingImport,
    ) {
        parent::__construct(__('csv_import.commit.exact_file_duplicate', [
            'id' => $existingImport->id,
        ]));
    }
}

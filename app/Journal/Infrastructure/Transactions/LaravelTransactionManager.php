<?php

namespace App\Journal\Infrastructure\Transactions;

use App\Journal\Application\Transactions\TransactionManager;
use Illuminate\Database\DatabaseManager;

final readonly class LaravelTransactionManager implements TransactionManager
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    public function transactional(callable $operation): mixed
    {
        return $this
            ->database
            ->connection()
            ->transaction(fn () => $operation());
    }
}

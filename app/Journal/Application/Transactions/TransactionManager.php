<?php

namespace App\Journal\Application\Transactions;

interface TransactionManager
{
    public function transactional(callable $operation): mixed;
}

<?php

namespace App\Domain\Event;

final class WalletWasInitialized
{
    public function __construct(
        public string $walletId
    ){}
}
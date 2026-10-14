<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

final class FreigabeKette
{
    public function __construct(
        public ?int $glId,
        public ?int $alId,
        public ?int $gfId,
    ) {}
}

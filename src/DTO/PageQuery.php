<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PageQuery
{
    public function __construct(
        #[Assert\Range(min: 1, max: MovieListDTO::MAX_PAGES)]
        public int $page = 1,
    ) {
    }
}

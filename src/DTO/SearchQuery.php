<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchQuery
{
    public const int MIN_LENGTH = 2;
    public const int MAX_LENGTH = 100;

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: self::MIN_LENGTH, max: self::MAX_LENGTH, normalizer: 'trim')]
        public string $q = '',
        #[Assert\Range(min: 1, max: MovieListDTO::MAX_PAGES)]
        public int $page = 1,
    ) {
    }

    public function term(): string
    {
        return trim($this->q);
    }
}

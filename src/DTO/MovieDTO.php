<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class MovieDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $overview,
        public ?string $posterUrl,
        public ?int $releaseYear,
        public float $rating,
        public int $voteCount,
    ) {
    }
}

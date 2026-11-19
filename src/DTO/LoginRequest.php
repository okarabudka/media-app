<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $username = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 4096)]
        #[\SensitiveParameter]
        public string $password = '',
    ) {
    }
}

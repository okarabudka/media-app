<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\PageQuery;
use App\DTO\SearchQuery;
use App\Tmdb\TmdbClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/movies', name: 'api_movies_', methods: ['GET'])]
final class MovieController extends AbstractController
{
    public function __construct(
        private readonly TmdbClientInterface $tmdb,
    ) {
    }

    #[Route('', name: 'popular')]
    public function popular(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PageQuery $query = new PageQuery(),
    ): JsonResponse {
        return $this->json($this->tmdb->popular($query->page));
    }

    #[Route('/search', name: 'search')]
    public function search(
        // mapWhenEmpty: a request without "q" is validated (and rejected) instead of skipping validation.
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST, mapWhenEmpty: true)]
        SearchQuery $query,
    ): JsonResponse {
        return $this->json($this->tmdb->search($query->term(), $query->page));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '[1-9]\d{0,9}'])]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->tmdb->movie($id));
    }
}

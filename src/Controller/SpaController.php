<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the Vue app for every non-API path, so deep links and reloads work with HTML5 history routing.
 */
final class SpaController extends AbstractController
{
    #[Route('/{path}', name: 'spa', requirements: ['path' => '(?!api(?:/|$)).*'], defaults: ['path' => ''], methods: ['GET'], priority: -10)]
    public function __invoke(): Response
    {
        return $this->render('spa.html.twig');
    }
}

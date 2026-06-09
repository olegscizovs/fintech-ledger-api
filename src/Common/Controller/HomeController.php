<?php

declare(strict_types=1);

namespace App\Common\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    #[Route('/', name: 'api_home', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return new RedirectResponse('/index.html');
    }
}

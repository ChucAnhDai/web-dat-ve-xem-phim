<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\HomeService;

class HomeApiController
{
    private HomeService $homeService;

    public function __construct(?HomeService $homeService = null)
    {
        $this->homeService = $homeService ?? new HomeService();
    }

    public function getHomeData(Request $request, Response $response): void
    {
        $result = $this->homeService->getHomeData();
        
        $status = $result['success'] ? 200 : 500;
        $response->json($result, $status);
    }
}

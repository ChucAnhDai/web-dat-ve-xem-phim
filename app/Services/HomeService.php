<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\CinemaRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
use App\Repositories\ProductRepository;
use Throwable;

class HomeService
{
    private MovieRepository $movieRepository;
    private ProductRepository $productRepository;
    private CinemaRepository $cinemaRepository;
    private MovieReviewRepository $reviewRepository;
    private Logger $logger;

    public function __construct(
        ?MovieRepository $movieRepository = null,
        ?ProductRepository $productRepository = null,
        ?CinemaRepository $cinemaRepository = null,
        ?MovieReviewRepository $reviewRepository = null,
        ?Logger $logger = null
    ) {
        $this->movieRepository = $movieRepository ?? new MovieRepository();
        $this->productRepository = $productRepository ?? new ProductRepository();
        $this->cinemaRepository = $cinemaRepository ?? new CinemaRepository();
        $this->reviewRepository = $reviewRepository ?? new MovieReviewRepository();
        $this->logger = $logger ?? new Logger();
    }

    public function getHomeData(): array
    {
        try {
            $nowShowing = $this->movieRepository->paginatePublicCatalog([
                'status' => 'now_showing',
                'page' => 1,
                'per_page' => 10,
                'sort' => 'popular'
            ]);

            $comingSoon = $this->movieRepository->paginatePublicCatalog([
                'status' => 'coming_soon',
                'page' => 1,
                'per_page' => 6,
                'sort' => 'newest'
            ]);

            $products = $this->productRepository->paginatePublicCatalog([
                'page' => 1,
                'per_page' => 8,
                'sort' => 'featured'
            ], 10);

            $cinemaStats = $this->cinemaRepository->summarize(['scope' => 'active']);

            // Calculate global average rating from now_showing movies
            $avgRating = 0;
            if (!empty($nowShowing['items'])) {
                $ratings = array_column($nowShowing['items'], 'average_rating');
                $avgRating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;
            }

            // Pick hero movies (top 3 from now showing)
            $heroMovies = !empty($nowShowing['items']) ? array_slice($nowShowing['items'], 0, 5) : [];

            return [
                'success' => true,
                'data' => [
                    'hero_movies' => $heroMovies,
                    'stats' => [
                        'movies_count' => (int)$this->movieRepository->countPublicCatalog(),
                        'rooms_count' => (int)($cinemaStats['room_count'] ?? 0),
                        'products_count' => (int)($products['meta']['total'] ?? 0),
                        'avg_rating' => round($avgRating, 1) ?: 4.8 // Fallback to 4.8 if no movies
                    ],
                    'now_showing' => array_slice($nowShowing['items'], 0, 6),
                    'coming_soon' => $comingSoon['items'],
                    'popular_products' => $products['items']
                ]
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to fetch home page data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi khi tải dữ liệu trang chủ.'
            ];
        }
    }
}

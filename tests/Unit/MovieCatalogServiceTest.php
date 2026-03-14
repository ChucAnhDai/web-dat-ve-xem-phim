<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
use App\Repositories\ShowtimeRepository;
use App\Services\MovieCatalogService;
use App\Validators\MovieCatalogValidator;
use PHPUnit\Framework\TestCase;

class MovieCatalogServiceTest extends TestCase
{
    public function testListMoviesReturnsMappedCatalogData(): void
    {
        $movieRepo = new UnitFakeCatalogMovieRepository();
        $movieRepo->paginatedItems = [
            [
                'id' => 5,
                'primary_category_id' => 2,
                'primary_category_name' => 'Drama',
                'slug' => 'demo-movie',
                'title' => 'Demo Movie',
                'summary' => 'Summary',
                'duration_minutes' => 125,
                'release_date' => '2026-03-14',
                'poster_url' => 'https://example.com/poster.jpg',
                'age_rating' => 'T16',
                'language' => 'English',
                'average_rating' => 4.4,
                'review_count' => 23,
                'status' => 'coming_soon',
            ],
        ];
        $movieRepo->paginatedTotal = 4;

        $categoryRepo = new UnitFakeCatalogCategoryRepository();
        $categoryRepo->publicOptions = [
            ['id' => 2, 'name' => 'Drama', 'slug' => 'drama'],
            ['id' => 3, 'name' => 'Action', 'slug' => 'action'],
        ];

        $service = new MovieCatalogService(
            $movieRepo,
            $categoryRepo,
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger()
        );

        $result = $service->listMovies([
            'page' => 1,
            'per_page' => 6,
            'status' => 'coming_soon',
            'sort' => 'rating',
            'category_id' => '2',
            'min_rating' => '4.0',
            'search' => 'demo',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Demo Movie', $result['data']['items'][0]['title']);
        $this->assertSame(4, $result['data']['meta']['total']);
        $this->assertSame('coming_soon', $result['data']['filters']['status']);
        $this->assertSame('rating', $result['data']['filters']['sort']);
        $this->assertSame(2, $result['data']['filters']['category_id']);
        $this->assertSame(4.0, $result['data']['filters']['min_rating']);
        $this->assertCount(2, $result['data']['categories']);
    }

    public function testGetMovieDetailReturnsMappedDetailData(): void
    {
        $movieRepo = new UnitFakeCatalogMovieRepository();
        $movieRepo->publicDetail = [
            'id' => 10,
            'primary_category_id' => 2,
            'primary_category_name' => 'Drama',
            'category_names_csv' => 'Drama,Thriller',
            'slug' => 'detail-movie',
            'title' => 'Detail Movie',
            'summary' => 'Longer plot summary',
            'duration_minutes' => 132,
            'release_date' => '2026-03-14',
            'poster_url' => 'https://example.com/poster.jpg',
            'banner_url' => 'https://example.com/banner.jpg',
            'trailer_url' => 'https://example.com/trailer',
            'age_rating' => 'T16',
            'language' => 'English',
            'director' => 'Director Name',
            'writer' => 'Writer Name',
            'cast_text' => 'Actor A, Actor B',
            'studio' => 'Studio X',
            'average_rating' => 4.7,
            'review_count' => 18,
            'status' => 'now_showing',
        ];
        $movieRepo->publicRelatedMovies = [
            [
                'id' => 11,
                'primary_category_id' => 2,
                'primary_category_name' => 'Drama',
                'slug' => 'related-movie',
                'title' => 'Related Movie',
                'duration_minutes' => 120,
                'release_date' => '2026-03-20',
                'poster_url' => 'https://example.com/related.jpg',
                'average_rating' => 4.2,
                'review_count' => 6,
                'status' => 'now_showing',
            ],
        ];

        $images = new UnitFakeCatalogImageRepository();
        $images->activeAssets = [
            [
                'id' => 50,
                'asset_type' => 'gallery',
                'image_url' => 'https://example.com/gallery.jpg',
                'alt_text' => 'Gallery image',
                'sort_order' => 1,
                'is_primary' => 0,
            ],
        ];

        $reviews = new UnitFakeCatalogReviewRepository();
        $reviews->approvedReviews = [
            [
                'id' => 70,
                'user_name' => 'Jane Doe',
                'rating' => 5,
                'comment' => 'Excellent film',
                'created_at' => '2026-03-15 10:00:00',
            ],
        ];

        $showtimes = new UnitFakeCatalogShowtimeRepository();
        $showtimes->upcomingGroups = [
            [
                'date' => '2026-03-16',
                'venues' => [
                    [
                        'cinema_name' => 'CinemaX Downtown',
                        'room_name' => 'Hall 1',
                        'times' => [
                            ['id' => 90, 'start_time' => '18:30:00', 'price' => 12.5],
                        ],
                    ],
                ],
            ],
        ];

        $service = new MovieCatalogService(
            $movieRepo,
            new UnitFakeCatalogCategoryRepository(),
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger(),
            $images,
            $reviews,
            $showtimes
        );

        $result = $service->getMovieDetail('detail-movie');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Detail Movie', $result['data']['movie']['title']);
        $this->assertSame(['Drama', 'Thriller'], $result['data']['movie']['category_names']);
        $this->assertSame('https://example.com/gallery.jpg', $result['data']['gallery'][0]['image_url']);
        $this->assertSame('CinemaX Downtown', $result['data']['showtime_groups'][0]['venues'][0]['cinema_name']);
        $this->assertSame('Jane Doe', $result['data']['reviews'][0]['user_name']);
        $this->assertSame('related-movie', $result['data']['related_movies'][0]['slug']);
    }
}

class UnitFakeCatalogMovieRepository extends MovieRepository
{
    public array $paginatedItems = [];
    public int $paginatedTotal = 0;
    public ?array $publicDetail = null;
    public array $publicRelatedMovies = [];

    public function __construct()
    {
    }

    public function paginatePublicCatalog(array $filters): array
    {
        return [
            'items' => $this->paginatedItems,
            'total' => $this->paginatedTotal,
            'page' => (int) ($filters['page'] ?? 1),
            'per_page' => (int) ($filters['per_page'] ?? 12),
        ];
    }

    public function findPublicDetailBySlug(string $slug): ?array
    {
        return $this->publicDetail;
    }

    public function listPublicRelatedMovies(int $movieId, ?int $categoryId = null, int $limit = 4): array
    {
        return $this->publicRelatedMovies;
    }
}

class UnitFakeCatalogCategoryRepository extends MovieCategoryRepository
{
    public array $publicOptions = [];

    public function __construct()
    {
    }

    public function listPublicOptions(): array
    {
        return $this->publicOptions;
    }
}

class UnitFakeCatalogLogger extends Logger
{
    public function __construct()
    {
    }

    public function info(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}

class UnitFakeCatalogImageRepository extends MovieImageRepository
{
    public array $activeAssets = [];

    public function __construct()
    {
    }

    public function listActiveAssetsForMovie(int $movieId, ?string $assetType = null): array
    {
        return $this->activeAssets;
    }
}

class UnitFakeCatalogReviewRepository extends MovieReviewRepository
{
    public array $approvedReviews = [];

    public function __construct()
    {
    }

    public function listApprovedVisibleForMovie(int $movieId, int $limit = 5): array
    {
        return $this->approvedReviews;
    }
}

class UnitFakeCatalogShowtimeRepository extends ShowtimeRepository
{
    public array $upcomingGroups = [];

    public function __construct()
    {
    }

    public function listUpcomingByMovie(int $movieId, int $limitDays = 6): array
    {
        return $this->upcomingGroups;
    }
}

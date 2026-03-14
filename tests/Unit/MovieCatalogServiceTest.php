<?php

namespace Tests\Unit;

use App\Clients\OphimClient;
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
    public function testListMoviesReturnsMappedCatalogDataFromOphim(): void
    {
        $client = new UnitFakeOphimClient();
        $client->listPayload = [
            'data' => [
                'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                'items' => [
                    [
                        'slug' => 'my-boo-2',
                        'name' => 'My Boo 2',
                        'origin_name' => 'Original Boo',
                        'poster_url' => 'my-boo-2-poster.jpg',
                        'thumb_url' => 'my-boo-2-thumb.jpg',
                        'time' => '117 Phút',
                        'quality' => 'HD',
                        'lang' => 'Vietsub',
                        'year' => 2025,
                        'tmdb' => ['vote_average' => 8.6, 'vote_count' => 100],
                        'imdb' => ['vote_average' => 0, 'vote_count' => 10],
                        'category' => [
                            ['slug' => 'kinh-di', 'name' => 'Kinh Dị'],
                            ['slug' => 'hai-huoc', 'name' => 'Hài Hước'],
                        ],
                        'episode_current' => 'Full',
                    ],
                ],
                'params' => [
                    'pagination' => [
                        'totalItems' => 25,
                        'totalItemsPerPage' => 12,
                        'currentPage' => 1,
                    ],
                ],
            ],
        ];

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger(),
            new UnitFakePublicMovieRepository(),
            new UnitFakePublicCategoryRepository(),
            new UnitFakePublicImageRepository(),
            new UnitFakePublicReviewRepository(),
            new UnitFakeShowtimeRepository()
        );

        $result = $service->listMovies([
            'page' => 1,
            'per_page' => 12,
            'status' => 'now_showing',
            'sort' => 'rating',
            'category_id' => 'kinh-di',
            'min_rating' => '4.0',
            'search' => 'boo',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('My Boo 2', $result['data']['items'][0]['title']);
        $this->assertSame('kinh-di', $result['data']['filters']['category_id']);
        $this->assertSame(4.3, $result['data']['items'][0]['average_rating']);
        $this->assertSame('https://img.ophim.live/uploads/movies/my-boo-2-thumb.jpg', $result['data']['items'][0]['poster_url']);
        $this->assertCount(2, $result['data']['categories']);
        $this->assertSame('ophim', $result['data']['source']['provider']);
    }

    public function testListMoviesUsesOphimSearchEndpointWhenKeywordProvided(): void
    {
        $client = new UnitFakeOphimClient();
        $client->searchPayload = [
            'data' => [
                'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                'items' => [
                    [
                        'slug' => 'avengers-doomsday',
                        'name' => 'Avengers: Doomsday',
                        'origin_name' => 'Avengers: Doomsday',
                        'poster_url' => 'avengers-doomsday.jpg',
                        'thumb_url' => 'avengers-doomsday-thumb.jpg',
                        'time' => '120 min',
                        'quality' => 'HD',
                        'lang' => 'Vietsub',
                        'year' => 2026,
                        'tmdb' => ['vote_average' => 8.8, 'vote_count' => 300],
                        'imdb' => ['vote_average' => 0, 'vote_count' => 0],
                        'category' => [
                            ['slug' => 'hanh-dong', 'name' => 'Action'],
                        ],
                        'episode_current' => 'Full',
                    ],
                    [
                        'slug' => 'avengers-trailer',
                        'name' => 'Avengers Trailer',
                        'origin_name' => 'Avengers Trailer',
                        'poster_url' => 'avengers-trailer.jpg',
                        'thumb_url' => 'avengers-trailer-thumb.jpg',
                        'time' => '2 min',
                        'quality' => 'HD',
                        'lang' => 'Vietsub',
                        'year' => 2026,
                        'tmdb' => ['vote_average' => 7.2, 'vote_count' => 50],
                        'imdb' => ['vote_average' => 0, 'vote_count' => 0],
                        'category' => [
                            ['slug' => 'hanh-dong', 'name' => 'Action'],
                        ],
                        'episode_current' => 'Trailer',
                    ],
                ],
                'params' => [
                    'pagination' => [
                        'totalItems' => 15,
                        'totalItemsPerPage' => 12,
                        'currentPage' => 2,
                        'totalPages' => 2,
                    ],
                ],
            ],
        ];

        $movies = new UnitFakePublicMovieRepository();
        $movies->publicCatalogCount = 5;

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger(),
            $movies,
            new UnitFakePublicCategoryRepository(),
            new UnitFakePublicImageRepository(),
            new UnitFakePublicReviewRepository(),
            new UnitFakeShowtimeRepository()
        );

        $result = $service->listMovies([
            'page' => 2,
            'per_page' => 12,
            'status' => 'now_showing',
            'search' => 'avengers',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('avengers', $client->recordedSearchCalls[0]['keyword']);
        $this->assertSame(2, $client->recordedSearchCalls[0]['query']['page']);
        $this->assertSame('search', $result['data']['source']['mode']);
        $this->assertSame(15, $result['data']['meta']['total']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Avengers: Doomsday', $result['data']['items'][0]['title']);
    }

    public function testGetMovieDetailReturnsMappedTrailerAndGalleryData(): void
    {
        $client = new UnitFakeOphimClient();
        $client->detailPayload = [
            'data' => [
                'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                'item' => [
                    'slug' => 'toi-pham-101',
                    'name' => 'Tội Phạm 101',
                    'origin_name' => 'Crime 101',
                    'content' => '<p>Movie summary</p>',
                    'poster_url' => 'toi-pham-101-poster.jpg',
                    'thumb_url' => 'toi-pham-101-thumb.jpg',
                    'trailer_url' => 'https://youtube.com/watch?v=xyz',
                    'time' => '140 Phút',
                    'quality' => 'HD',
                    'lang' => 'Vietsub',
                    'year' => 2026,
                    'episode_current' => 'Full',
                    'tmdb' => ['vote_average' => 9.0, 'vote_count' => 2],
                    'imdb' => ['vote_average' => 0, 'vote_count' => 0],
                    'actor' => ['Chris Hemsworth', 'Halle Berry'],
                    'director' => ['Bart Layton'],
                    'category' => [
                        ['slug' => 'hinh-su', 'name' => 'Hình Sự'],
                    ],
                    'country' => [
                        ['slug' => 'anh', 'name' => 'Anh'],
                    ],
                ],
            ],
        ];
        $client->imagesPayload = [
            'data' => [
                'image_sizes' => [
                    'backdrop' => ['w1280' => 'https://image.tmdb.org/t/p/w1280'],
                    'poster' => ['w780' => 'https://image.tmdb.org/t/p/w780'],
                ],
                'images' => [
                    ['type' => 'backdrop', 'file_path' => '/abc.jpg'],
                    ['type' => 'poster', 'file_path' => '/poster.jpg'],
                ],
            ],
        ];
        $client->relatedPayload = [
            'data' => [
                'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                'items' => [
                    [
                        'slug' => 'ke-an-dat',
                        'name' => 'Kẻ Ẩn Dật',
                        'origin_name' => 'Shelter',
                        'poster_url' => 'ke-an-dat-poster.jpg',
                        'thumb_url' => 'ke-an-dat-thumb.jpg',
                        'time' => '107 Phút',
                        'quality' => 'HD',
                        'lang' => 'Vietsub',
                        'year' => 2026,
                        'tmdb' => ['vote_average' => 7.6, 'vote_count' => 13],
                        'imdb' => ['vote_average' => 0, 'vote_count' => 0],
                        'category' => [
                            ['slug' => 'hinh-su', 'name' => 'Hình Sự'],
                        ],
                        'episode_current' => 'Full',
                    ],
                ],
            ],
        ];

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger(),
            new UnitFakePublicMovieRepository(),
            new UnitFakePublicCategoryRepository(),
            new UnitFakePublicImageRepository(),
            new UnitFakePublicReviewRepository(),
            new UnitFakeShowtimeRepository()
        );

        $result = $service->getMovieDetail('toi-pham-101');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Tội Phạm 101', $result['data']['movie']['title']);
        $this->assertSame(['Hình Sự'], $result['data']['movie']['category_names']);
        $this->assertSame('https://img.ophim.live/uploads/movies/toi-pham-101-thumb.jpg', $result['data']['movie']['poster_url']);
        $this->assertSame('https://image.tmdb.org/t/p/w1280/abc.jpg', $result['data']['movie']['banner_url']);
        $this->assertSame('https://image.tmdb.org/t/p/w1280/abc.jpg', $result['data']['gallery'][0]['image_url']);
        $this->assertSame('https://youtube.com/watch?v=xyz', $result['data']['movie']['trailer_url']);
        $this->assertSame([], $result['data']['showtimes']);
        $this->assertArrayNotHasKey('playback_groups', $result['data']);
        $this->assertSame('ke-an-dat', $result['data']['related_movies'][0]['slug']);
    }

    public function testGetMovieDetailPrefersLocalMovieDataWhenAdminMovieExists(): void
    {
        $client = new UnitFakeOphimClient();
        $client->detailPayload = [
            'data' => [
                'item' => [
                    'slug' => 'toi-pham-101',
                    'name' => 'Old OPhim Title',
                ],
            ],
        ];

        $movies = new UnitFakePublicMovieRepository();
        $movies->publicCatalogCount = 1;
        $movies->publicDetailRow = [
            'id' => 77,
            'primary_category_id' => 3,
            'primary_category_name' => 'Hinh Su',
            'category_names_csv' => 'Hinh Su,Action',
            'slug' => 'toi-pham-101',
            'title' => 'Edited Local Title',
            'summary' => 'Edited summary',
            'duration_minutes' => 140,
            'release_date' => '2026-01-01',
            'poster_url' => 'https://local.example.com/poster.jpg',
            'banner_url' => 'https://local.example.com/banner.jpg',
            'trailer_url' => 'https://youtube.com/watch?v=local',
            'age_rating' => 'T18',
            'language' => 'Vietsub',
            'director' => 'Local Director',
            'writer' => 'Local Writer',
            'cast_text' => 'Local Cast',
            'studio' => 'Vietnam',
            'average_rating' => 4.8,
            'review_count' => 6,
            'status' => 'now_showing',
        ];
        $movies->relatedRows = [
            [
                'id' => 78,
                'primary_category_id' => 3,
                'primary_category_name' => 'Hinh Su',
                'slug' => 'related-local',
                'title' => 'Related Local',
                'duration_minutes' => 110,
                'release_date' => '2026-02-01',
                'poster_url' => 'https://local.example.com/related.jpg',
                'average_rating' => 4.1,
                'review_count' => 2,
                'status' => 'now_showing',
            ],
        ];

        $images = new UnitFakePublicImageRepository();
        $images->assets = [
            [
                'id' => 11,
                'asset_type' => 'banner',
                'image_url' => 'https://local.example.com/banner.jpg',
                'alt_text' => 'Banner',
                'sort_order' => 1,
                'is_primary' => 1,
            ],
            [
                'id' => 12,
                'asset_type' => 'gallery',
                'image_url' => 'https://local.example.com/gallery.jpg',
                'alt_text' => 'Gallery',
                'sort_order' => 2,
                'is_primary' => 0,
            ],
        ];

        $reviews = new UnitFakePublicReviewRepository();
        $reviews->items = [
            [
                'id' => 5,
                'movie_id' => 77,
                'user_id' => 1,
                'rating' => 5,
                'comment' => 'Great',
                'created_at' => '2026-03-14 00:00:00',
                'user_name' => 'Reviewer',
            ],
        ];

        $showtimes = new UnitFakeShowtimeRepository();
        $showtimes->items = [
            [
                'date' => '2026-03-14',
                'venues' => [
                    [
                        'cinema_name' => 'CinemaX Landmark',
                        'room_name' => 'Hall 1 - IMAX',
                        'times' => [
                            ['id' => 101, 'start_time' => '13:30:00', 'price' => 18.00],
                            ['id' => 102, 'start_time' => '16:45:00', 'price' => 18.00],
                        ],
                    ],
                ],
            ],
        ];

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger(),
            $movies,
            new UnitFakePublicCategoryRepository(),
            $images,
            $reviews,
            $showtimes
        );

        $result = $service->getMovieDetail('toi-pham-101');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Edited Local Title', $result['data']['movie']['title']);
        $this->assertSame('local', $result['data']['source']['provider']);
        $this->assertArrayNotHasKey('playback_groups', $result['data']);
        $this->assertSame('2026-03-14', $result['data']['showtimes'][0]['date']);
        $this->assertSame('1:30 PM', $result['data']['showtimes'][0]['venues'][0]['times'][0]['start_time_label']);
        $this->assertSame('Related Local', $result['data']['related_movies'][0]['title']);
    }
}

class UnitFakeOphimClient extends OphimClient
{
    public array $listPayload = [];
    public array $searchPayload = [];
    public array $detailPayload = [];
    public array $imagesPayload = [];
    public array $relatedPayload = [];
    public array $recordedListCalls = [];
    public array $recordedSearchCalls = [];

    public function __construct()
    {
    }

    public function listBySlug(string $slug, array $query = []): array
    {
        $this->recordedListCalls[] = ['slug' => $slug, 'query' => $query];

        if (count($this->recordedListCalls) > 1 && !empty($this->relatedPayload)) {
            return $this->relatedPayload;
        }

        return $this->listPayload;
    }

    public function searchMovies(string $keyword, array $query = []): array
    {
        $this->recordedSearchCalls[] = ['keyword' => $keyword, 'query' => $query];

        return $this->searchPayload;
    }

    public function getMovieDetail(string $slug): array
    {
        return $this->detailPayload;
    }

    public function getMovieImages(string $slug): array
    {
        return $this->imagesPayload;
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

class UnitFakePublicMovieRepository extends MovieRepository
{
    public int $publicCatalogCount = 0;
    public array $publicCatalogPage = [
        'items' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 12,
    ];
    public ?array $publicDetailRow = null;
    public array $relatedRows = [];

    public function __construct()
    {
    }

    public function countPublicCatalog(): int
    {
        return $this->publicCatalogCount;
    }

    public function paginatePublicCatalog(array $filters): array
    {
        return $this->publicCatalogPage;
    }

    public function findPublicDetailBySlug(string $slug): ?array
    {
        return $this->publicDetailRow;
    }

    public function listPublicRelatedMovies(int $movieId, ?int $categoryId = null, int $limit = 4): array
    {
        return $this->relatedRows;
    }
}

class UnitFakePublicCategoryRepository extends MovieCategoryRepository
{
    public array $items = [];

    public function __construct()
    {
    }

    public function listPublicOptions(): array
    {
        return $this->items;
    }
}

class UnitFakePublicImageRepository extends MovieImageRepository
{
    public array $assets = [];

    public function __construct()
    {
    }

    public function listActiveAssetsForMovie(int $movieId, ?string $assetType = null): array
    {
        return $this->assets;
    }
}

class UnitFakePublicReviewRepository extends MovieReviewRepository
{
    public array $items = [];

    public function __construct()
    {
    }

    public function listApprovedVisibleForMovie(int $movieId, int $limit = 5): array
    {
        return $this->items;
    }
}

class UnitFakeShowtimeRepository extends ShowtimeRepository
{
    public array $items = [];

    public function __construct()
    {
    }

    public function listUpcomingByMovie(int $movieId, int $limitDays = 6): array
    {
        return $this->items;
    }
}

<?php

namespace Tests\Unit;

use App\Clients\OphimClient;
use App\Core\Logger;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
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
            new UnitFakePublicReviewRepository()
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
        $this->assertSame('https://img.ophim.live/uploads/movies/my-boo-2-poster.jpg', $result['data']['items'][0]['poster_url']);
        $this->assertCount(2, $result['data']['categories']);
        $this->assertSame('ophim', $result['data']['source']['provider']);
    }

    public function testGetMovieDetailReturnsMappedPlaybackAndGalleryData(): void
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
                    'episodes' => [
                        [
                            'server_name' => 'Vietsub #1',
                            'is_ai' => false,
                            'server_data' => [
                                [
                                    'name' => 'Full',
                                    'slug' => 'full',
                                    'filename' => 'crime-101-full',
                                    'link_embed' => 'https://player.example.com/embed/xyz',
                                    'link_m3u8' => 'https://example.com/video.m3u8',
                                ],
                            ],
                        ],
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
            new UnitFakePublicReviewRepository()
        );

        $result = $service->getMovieDetail('toi-pham-101');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Tội Phạm 101', $result['data']['movie']['title']);
        $this->assertSame(['Hình Sự'], $result['data']['movie']['category_names']);
        $this->assertSame('https://image.tmdb.org/t/p/w1280/abc.jpg', $result['data']['gallery'][0]['image_url']);
        $this->assertSame('Vietsub #1', $result['data']['playback_groups'][0]['server_name']);
        $this->assertSame('Full', $result['data']['playback_groups'][0]['items'][0]['label']);
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
                    'episodes' => [
                        [
                            'server_name' => 'Server 1',
                            'server_data' => [
                                [
                                    'name' => 'Full',
                                    'slug' => 'full',
                                    'link_embed' => 'https://player.example.com/embed/local',
                                    'link_m3u8' => 'https://example.com/local.m3u8',
                                ],
                            ],
                        ],
                    ],
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

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new UnitFakeCatalogLogger(),
            $movies,
            new UnitFakePublicCategoryRepository(),
            $images,
            $reviews
        );

        $result = $service->getMovieDetail('toi-pham-101');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Edited Local Title', $result['data']['movie']['title']);
        $this->assertSame('local', $result['data']['source']['provider']);
        $this->assertSame('Server 1', $result['data']['playback_groups'][0]['server_name']);
        $this->assertSame('Related Local', $result['data']['related_movies'][0]['title']);
    }
}

class UnitFakeOphimClient extends OphimClient
{
    public array $listPayload = [];
    public array $detailPayload = [];
    public array $imagesPayload = [];
    public array $relatedPayload = [];
    public array $recordedListCalls = [];

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

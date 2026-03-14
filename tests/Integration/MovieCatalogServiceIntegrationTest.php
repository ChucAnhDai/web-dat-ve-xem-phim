<?php

namespace Tests\Integration;

use App\Clients\OphimClient;
use App\Core\Logger;
use App\Services\MovieCatalogService;
use App\Validators\MovieCatalogValidator;
use PHPUnit\Framework\TestCase;

class MovieCatalogServiceIntegrationTest extends TestCase
{
    public function testListMoviesSupportsComingSoonSlugAndLocalRatingFilter(): void
    {
        $client = new IntegrationFakeOphimClient();
        $client->listPayload = [
            'data' => [
                'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                'items' => [
                    [
                        'slug' => 'ngay-hoc-cuoi-cung',
                        'name' => 'Ngày Học Cuối Cùng',
                        'origin_name' => 'This Is Not a Test',
                        'poster_url' => 'ngay-hoc-cuoi-cung-poster.jpg',
                        'thumb_url' => 'ngay-hoc-cuoi-cung-thumb.jpg',
                        'time' => '102 Phút',
                        'quality' => 'HD',
                        'lang' => 'Vietsub',
                        'year' => 2026,
                        'tmdb' => ['vote_average' => 8.0, 'vote_count' => 7],
                        'imdb' => ['vote_average' => 0, 'vote_count' => 194],
                        'category' => [
                            ['slug' => 'kinh-di', 'name' => 'Kinh Dị'],
                        ],
                        'episode_current' => 'Trailer',
                    ],
                    [
                        'slug' => 'the-hermit',
                        'name' => 'The Hermit',
                        'origin_name' => 'The Hermit',
                        'poster_url' => 'the-hermit-poster.jpg',
                        'thumb_url' => 'the-hermit-thumb.jpg',
                        'time' => '86 Phút',
                        'quality' => 'HD',
                        'lang' => 'Vietsub',
                        'year' => 2025,
                        'tmdb' => ['vote_average' => 6.0, 'vote_count' => 6],
                        'imdb' => ['vote_average' => 0, 'vote_count' => 0],
                        'category' => [
                            ['slug' => 'kinh-di', 'name' => 'Kinh Dị'],
                        ],
                        'episode_current' => 'Trailer',
                    ],
                ],
                'params' => [
                    'pagination' => [
                        'totalItems' => 487,
                        'totalItemsPerPage' => 24,
                        'currentPage' => 1,
                    ],
                ],
            ],
        ];

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new IntegrationFakeCatalogLogger()
        );

        $result = $service->listMovies([
            'status' => 'coming_soon',
            'min_rating' => 4.0,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('phim-sap-chieu', $client->listCalls[0]['slug']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('coming_soon', $result['data']['items'][0]['status']);
        $this->assertSame('kinh-di', $result['data']['categories'][0]['slug']);
    }

    public function testGetMovieDetailReturnsNotFoundWhenOphimItemMissing(): void
    {
        $client = new IntegrationFakeOphimClient();
        $client->detailPayload = ['data' => []];

        $service = new MovieCatalogService(
            $client,
            new MovieCatalogValidator(),
            new IntegrationFakeCatalogLogger()
        );

        $result = $service->getMovieDetail('missing-slug');

        $this->assertSame(404, $result['status']);
        $this->assertSame(['Movie not found.'], $result['errors']['movie']);
    }
}

class IntegrationFakeOphimClient extends OphimClient
{
    public array $listPayload = [];
    public array $detailPayload = [];
    public array $imagesPayload = [];
    public array $listCalls = [];

    public function __construct()
    {
    }

    public function listBySlug(string $slug, array $query = []): array
    {
        $this->listCalls[] = ['slug' => $slug, 'query' => $query];

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

class IntegrationFakeCatalogLogger extends Logger
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

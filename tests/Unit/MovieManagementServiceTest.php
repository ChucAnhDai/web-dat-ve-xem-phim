<?php

namespace Tests\Unit;

use App\Core\Logger;
use App\Repositories\MovieCategoryAssignmentRepository;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
use App\Services\MovieManagementService;
use App\Services\MovieOphimSyncService;
use App\Validators\MovieManagementValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class MovieManagementServiceTest extends TestCase
{
    public function testListMoviesReturnsPaginationAndStatusSummary(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $movieRepo = new UnitFakeMovieRepository();
        $movieRepo->paginatedItems = [
            [
                'id' => 15,
                'primary_category_id' => 2,
                'primary_category_name' => 'Drama',
                'category_ids_csv' => '2,3',
                'slug' => 'demo-movie',
                'title' => 'Demo Movie',
                'summary' => 'Summary',
                'duration_minutes' => 130,
                'release_date' => '2026-03-14',
                'poster_url' => 'https://example.com/poster.jpg',
                'trailer_url' => null,
                'age_rating' => 'PG-13',
                'language' => 'English',
                'director' => 'Director',
                'writer' => 'Writer',
                'cast_text' => 'Cast',
                'studio' => 'Studio',
                'average_rating' => 4.4,
                'review_count' => 12,
                'status' => 'now_showing',
                'created_at' => null,
                'updated_at' => null,
            ],
        ];
        $movieRepo->paginatedTotal = 9;
        $movieRepo->statusCounts = [
            'now_showing' => 5,
            'coming_soon' => 3,
            'draft' => 1,
        ];

        $service = new MovieManagementService(
            $pdo,
            $movieRepo,
            new UnitFakeMovieCategoryRepository(),
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->listMovies(['page' => 1, 'per_page' => 10]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(9, $result['data']['summary']['total']);
        $this->assertSame(5, $result['data']['summary']['now_showing']);
        $this->assertSame(3, $result['data']['summary']['coming_soon']);
        $this->assertSame(1, $result['data']['summary']['draft']);
        $this->assertSame(0, $result['data']['summary']['ended']);
        $this->assertSame('Demo Movie', $result['data']['items'][0]['title']);
    }

    public function testListCategoriesReturnsPaginationAndCategorySummary(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $categoryRepo = new UnitFakeMovieCategoryRepository();
        $categoryRepo->paginatedItems = [
            [
                'id' => 2,
                'name' => 'Drama',
                'slug' => 'drama',
                'description' => 'Drama titles',
                'display_order' => 2,
                'is_active' => 1,
                'movie_count' => 7,
                'created_at' => null,
                'updated_at' => null,
            ],
        ];
        $categoryRepo->paginatedTotal = 6;
        $categoryRepo->summary = [
            'total' => 6,
            'active' => 5,
            'inactive' => 1,
            'tagged_movies' => 28,
        ];

        $service = new MovieManagementService(
            $pdo,
            new UnitFakeMovieRepository(),
            $categoryRepo,
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->listCategories(['page' => 1, 'per_page' => 10]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(6, $result['data']['summary']['total']);
        $this->assertSame(5, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['inactive']);
        $this->assertSame(28, $result['data']['summary']['tagged_movies']);
        $this->assertSame('Drama', $result['data']['items'][0]['name']);
    }

    public function testListAssetsReturnsPaginationAndAssetSummary(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $imageRepo = new UnitFakeMovieImageRepository();
        $imageRepo->paginatedItems = [
            [
                'id' => 9,
                'movie_id' => 5,
                'movie_title' => 'Demo Movie',
                'movie_slug' => 'demo-movie',
                'asset_type' => 'poster',
                'image_url' => 'https://example.com/poster.jpg',
                'alt_text' => 'Main poster',
                'sort_order' => 1,
                'is_primary' => 1,
                'status' => 'active',
                'created_at' => null,
                'updated_at' => null,
            ],
        ];
        $imageRepo->paginatedTotal = 4;
        $imageRepo->summary = [
            'total' => 4,
            'poster' => 2,
            'banner' => 1,
            'gallery' => 1,
            'draft' => 1,
            'active' => 2,
            'archived' => 1,
        ];

        $service = new MovieManagementService(
            $pdo,
            new UnitFakeMovieRepository(),
            new UnitFakeMovieCategoryRepository(),
            new UnitFakeMovieCategoryAssignmentRepository(),
            $imageRepo,
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->listAssets(['page' => 1, 'per_page' => 10]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(4, $result['data']['summary']['total']);
        $this->assertSame(2, $result['data']['summary']['poster']);
        $this->assertSame(1, $result['data']['summary']['banner']);
        $this->assertSame(1, $result['data']['summary']['gallery']);
        $this->assertSame(1, $result['data']['summary']['draft']);
        $this->assertSame(2, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['archived']);
        $this->assertSame('Demo Movie', $result['data']['items'][0]['movie_title']);
    }

    public function testCreateMovieReturnsConflictWhenSlugExists(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $movieRepo = new UnitFakeMovieRepository();
        $movieRepo->movieBySlug = ['id' => 5, 'slug' => 'demo-movie'];
        $categoryRepo = new UnitFakeMovieCategoryRepository();

        $service = new MovieManagementService(
            $pdo,
            $movieRepo,
            $categoryRepo,
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->createMovie([
            'title' => 'Demo Movie',
            'primary_category_id' => 1,
            'duration_minutes' => 120,
            'status' => 'draft',
            'category_ids' => [1],
        ], 1);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Movie slug already exists.'], $result['errors']['slug']);
    }

    public function testCreateMovieCreatesMovieAndAssignments(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $movieRepo = new UnitFakeMovieRepository();
        $categoryRepo = new UnitFakeMovieCategoryRepository();
        $assignmentRepo = new UnitFakeMovieCategoryAssignmentRepository();

        $service = new MovieManagementService(
            $pdo,
            $movieRepo,
            $categoryRepo,
            $assignmentRepo,
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->createMovie([
            'title' => 'Demo Movie',
            'primary_category_id' => 2,
            'duration_minutes' => 120,
            'status' => 'now_showing',
            'category_ids' => [2, 3],
            'average_rating' => 4.5,
        ], 9);

        $this->assertSame(201, $result['status']);
        $this->assertSame('demo-movie', $movieRepo->createdData['slug']);
        $this->assertSame([2, 3], $assignmentRepo->replacedCategoryIds);
        $this->assertSame(77, $result['data']['id']);
    }

    public function testArchiveMovieBlocksFuturePublishedShowtimes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $movieRepo = new UnitFakeMovieRepository();
        $movieRepo->movieRowsById[77] = [
            'id' => 77,
            'slug' => 'demo-movie',
            'title' => 'Demo Movie',
            'status' => 'now_showing',
        ];
        $movieRepo->hasFuturePublishedShowtimes = true;

        $service = new MovieManagementService(
            $pdo,
            $movieRepo,
            new UnitFakeMovieCategoryRepository(),
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->archiveMovie(77, 10);

        $this->assertSame(409, $result['status']);
        $this->assertSame(['Cannot archive movie while published future showtimes exist.'], $result['errors']['movie']);
        $this->assertFalse($movieRepo->archived);
    }

    public function testModerateReviewUpdatesMovieSummary(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $movieRepo = new UnitFakeMovieRepository();
        $reviewRepo = new UnitFakeMovieReviewRepository();
        $reviewRepo->reviewById = [
            'id' => 11,
            'movie_id' => 7,
            'user_id' => 1,
            'user_name' => 'Reviewer',
            'movie_title' => 'Demo Movie',
            'movie_slug' => 'demo-movie',
            'rating' => 4,
            'comment' => 'Comment',
            'status' => 'pending',
            'is_visible' => 0,
            'moderation_note' => null,
            'created_at' => '2026-03-14 00:00:00',
            'updated_at' => '2026-03-14 00:00:00',
        ];
        $reviewRepo->stats = ['average_rating' => 4.25, 'review_count' => 4];

        $service = new MovieManagementService(
            $pdo,
            $movieRepo,
            new UnitFakeMovieCategoryRepository(),
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            $reviewRepo,
            new MovieManagementValidator(),
            new UnitFakeMovieLogger()
        );

        $result = $service->moderateReview(11, [
            'status' => 'approved',
            'is_visible' => 1,
            'moderation_note' => 'Approved',
        ], 99);

        $this->assertSame(200, $result['status']);
        $this->assertSame('approved', $reviewRepo->moderationData['status']);
        $this->assertSame(1, $reviewRepo->moderationData['is_visible']);
        $this->assertSame([7, 4.25, 4], $movieRepo->reviewSummaryUpdate);
    }

    public function testImportMovieFromOphimReturnsMappedMovieAndSyncMetadata(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $movieRepo = new UnitFakeMovieRepository();
        $movieRepo->movieRowsById[88] = [
            'id' => 88,
            'primary_category_id' => 2,
            'primary_category_name' => 'Drama',
            'category_ids_csv' => '2,5',
            'slug' => 'tro-choi-con-muc',
            'title' => 'Squid Game',
            'summary' => 'Imported from OPhim',
            'duration_minutes' => 181,
            'release_date' => '2026-01-01',
            'poster_url' => 'https://img.example.com/poster.jpg',
            'trailer_url' => 'https://youtube.com/watch?v=demo',
            'age_rating' => null,
            'language' => 'Vietsub',
            'director' => 'Director',
            'writer' => null,
            'cast_text' => 'Actor',
            'studio' => 'Korea',
            'average_rating' => 4.2,
            'review_count' => 1200,
            'status' => 'coming_soon',
            'created_at' => null,
            'updated_at' => null,
        ];

        $ophimSync = new UnitFakeMovieOphimSyncService();
        $ophimSync->result = [
            'status' => 201,
            'data' => [
                'movie_id' => 88,
                'created' => true,
                'category_count' => 2,
                'asset_count' => 4,
                'source_slug' => 'tro-choi-con-muc',
            ],
        ];

        $service = new MovieManagementService(
            $pdo,
            $movieRepo,
            new UnitFakeMovieCategoryRepository(),
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger(),
            $ophimSync
        );

        $result = $service->importMovieFromOphim([
            'slug' => 'tro-choi-con-muc',
            'sync_images' => 1,
            'overwrite_existing' => 1,
            'status_override' => 'coming_soon',
        ], 15);

        $this->assertSame(201, $result['status']);
        $this->assertSame('Squid Game', $result['data']['movie']['title']);
        $this->assertSame('tro-choi-con-muc', $result['data']['sync']['source_slug']);
        $this->assertSame(4, $result['data']['sync']['asset_count']);
    }

    public function testImportMovieListFromOphimReturnsBatchSummary(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $ophimSync = new UnitFakeMovieOphimSyncService();
        $ophimSync->batchResult = [
            'status' => 200,
            'data' => [
                'list_slug' => 'phim-chieu-rap',
                'processed_count' => 12,
                'created_count' => 10,
                'updated_count' => 1,
                'skipped_count' => 1,
                'failed_count' => 0,
                'items' => [],
            ],
        ];

        $service = new MovieManagementService(
            $pdo,
            new UnitFakeMovieRepository(),
            new UnitFakeMovieCategoryRepository(),
            new UnitFakeMovieCategoryAssignmentRepository(),
            new UnitFakeMovieImageRepository(),
            new UnitFakeMovieReviewRepository(),
            new MovieManagementValidator(),
            new UnitFakeMovieLogger(),
            $ophimSync
        );

        $result = $service->importMovieListFromOphim([
            'list_slug' => 'phim-chieu-rap',
            'page' => 1,
            'limit' => 12,
            'sync_images' => 0,
            'overwrite_existing' => 1,
        ], 15);

        $this->assertSame(200, $result['status']);
        $this->assertSame('phim-chieu-rap', $result['data']['list_slug']);
        $this->assertSame(12, $result['data']['processed_count']);
        $this->assertSame(10, $result['data']['created_count']);
    }
}

class UnitFakeMovieRepository extends MovieRepository
{
    public ?array $movieBySlug = null;
    public array $movieRowsById = [];
    public array $createdData = [];
    public array $paginatedItems = [];
    public int $paginatedTotal = 0;
    public array $reviewSummaryUpdate = [];
    public array $statusCounts = [];
    public bool $hasFuturePublishedShowtimes = false;
    public bool $archived = false;

    public function __construct()
    {
    }

    public function paginate(array $filters): array
    {
        return [
            'items' => $this->paginatedItems,
            'total' => $this->paginatedTotal,
            'page' => (int) ($filters['page'] ?? 1),
            'per_page' => (int) ($filters['per_page'] ?? 20),
        ];
    }

    public function countByStatus(array $filters): array
    {
        return $this->statusCounts;
    }

    public function findById(int $id): ?array
    {
        if (isset($this->movieRowsById[$id])) {
            return $this->movieRowsById[$id];
        }

        if (!empty($this->createdData) && $id === 77) {
            return [
                'id' => 77,
                'primary_category_id' => $this->createdData['primary_category_id'],
                'primary_category_name' => 'Action',
                'category_ids_csv' => '2,3',
                'slug' => $this->createdData['slug'],
                'title' => $this->createdData['title'],
                'summary' => $this->createdData['summary'] ?? null,
                'duration_minutes' => $this->createdData['duration_minutes'],
                'release_date' => $this->createdData['release_date'] ?? null,
                'poster_url' => $this->createdData['poster_url'] ?? null,
                'trailer_url' => $this->createdData['trailer_url'] ?? null,
                'age_rating' => $this->createdData['age_rating'] ?? null,
                'language' => $this->createdData['language'] ?? null,
                'director' => $this->createdData['director'] ?? null,
                'writer' => $this->createdData['writer'] ?? null,
                'cast_text' => $this->createdData['cast_text'] ?? null,
                'studio' => $this->createdData['studio'] ?? null,
                'average_rating' => $this->createdData['average_rating'] ?? 0,
                'review_count' => 0,
                'status' => $this->createdData['status'],
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        return $this->movieBySlug;
    }

    public function create(array $data): int
    {
        $this->createdData = $data;

        return 77;
    }

    public function archive(int $id): bool
    {
        $this->archived = true;

        return true;
    }

    public function hasFuturePublishedShowtimes(int $movieId): bool
    {
        return $this->hasFuturePublishedShowtimes;
    }

    public function updateReviewSummary(int $id, float $averageRating, int $reviewCount): bool
    {
        $this->reviewSummaryUpdate = [$id, $averageRating, $reviewCount];

        return true;
    }
}

class UnitFakeMovieCategoryRepository extends MovieCategoryRepository
{
    public array $paginatedItems = [];
    public int $paginatedTotal = 0;
    public array $summary = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'tagged_movies' => 0,
    ];

    public function __construct()
    {
    }

    public function paginate(array $filters): array
    {
        return [
            'items' => $this->paginatedItems,
            'total' => $this->paginatedTotal,
            'page' => (int) ($filters['page'] ?? 1),
            'per_page' => (int) ($filters['per_page'] ?? 20),
        ];
    }

    public function summarize(array $filters): array
    {
        return $this->summary;
    }

    public function countByIds(array $ids): int
    {
        return count($ids);
    }

    public function findById(int $id): ?array
    {
        return ['id' => $id, 'slug' => 'category'];
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        return null;
    }
}

class UnitFakeMovieCategoryAssignmentRepository extends MovieCategoryAssignmentRepository
{
    public array $replacedCategoryIds = [];

    public function __construct()
    {
    }

    public function replaceForMovie(int $movieId, array $categoryIds): void
    {
        $this->replacedCategoryIds = $categoryIds;
    }
}

class UnitFakeMovieImageRepository extends MovieImageRepository
{
    public array $paginatedItems = [];
    public int $paginatedTotal = 0;
    public array $summary = [
        'total' => 0,
        'poster' => 0,
        'banner' => 0,
        'gallery' => 0,
        'draft' => 0,
        'active' => 0,
        'archived' => 0,
    ];

    public function __construct()
    {
    }

    public function paginate(array $filters): array
    {
        return [
            'items' => $this->paginatedItems,
            'total' => $this->paginatedTotal,
            'page' => (int) ($filters['page'] ?? 1),
            'per_page' => (int) ($filters['per_page'] ?? 20),
        ];
    }

    public function summarize(array $filters): array
    {
        return $this->summary;
    }
}

class UnitFakeMovieReviewRepository extends MovieReviewRepository
{
    public ?array $reviewById = null;
    public array $moderationData = [];
    public array $stats = ['average_rating' => 0.0, 'review_count' => 0];

    public function __construct()
    {
    }

    public function findById(int $id): ?array
    {
        return $this->reviewById;
    }

    public function updateModeration(int $id, array $data): bool
    {
        $this->moderationData = $data;
        if ($this->reviewById !== null) {
            $this->reviewById = array_merge($this->reviewById, $data);
        }

        return true;
    }

    public function getApprovedVisibleStatsForMovie(int $movieId): array
    {
        return $this->stats;
    }
}

class UnitFakeMovieLogger extends Logger
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

class UnitFakeMovieOphimSyncService extends MovieOphimSyncService
{
    public array $result = [
        'status' => 200,
        'data' => [],
    ];
    public array $batchResult = [
        'status' => 200,
        'data' => [],
    ];

    public function __construct()
    {
    }

    public function importBySlug(array $payload, ?int $actorId = null): array
    {
        return $this->result;
    }

    public function importList(array $payload, ?int $actorId = null): array
    {
        return $this->batchResult;
    }
}

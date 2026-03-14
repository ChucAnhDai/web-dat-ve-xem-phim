<?php

namespace Tests\Integration;

use App\Clients\OphimClient;
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
use RuntimeException;

class MovieManagementServiceIntegrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
        $this->seedBaseData();
    }

    public function testCreateMoviePersistsMovieAndAssignments(): void
    {
        $service = $this->makeService();

        $result = $service->createMovie([
            'title' => 'Integration Movie',
            'primary_category_id' => 1,
            'duration_minutes' => 140,
            'status' => 'coming_soon',
            'category_ids' => [1, 2],
            'release_date' => '2026-03-14',
        ], 10);

        $this->assertSame(201, $result['status']);

        $movieCount = (int) $this->db->query('SELECT COUNT(*) FROM movies')->fetchColumn();
        $assignmentCount = (int) $this->db->query('SELECT COUNT(*) FROM movie_category_assignments')->fetchColumn();

        $this->assertSame(1, $movieCount);
        $this->assertSame(2, $assignmentCount);
    }

    public function testListMoviesReturnsSummaryCountsAcrossFilteredResult(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
                age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES
                (11, 1, 'alpha-movie', 'Alpha Movie', NULL, 120, '2026-03-14', NULL, NULL, NULL, NULL, 'A Director', NULL, NULL, NULL, 4.5, 3, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 1, 'beta-movie', 'Beta Movie', NULL, 100, '2026-03-15', NULL, NULL, NULL, NULL, 'B Director', NULL, NULL, NULL, 0, 0, 'coming_soon', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (13, 2, 'draft-movie', 'Draft Movie', NULL, 110, '2026-03-16', NULL, NULL, NULL, NULL, 'C Director', NULL, NULL, NULL, 0, 0, 'draft', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_category_assignments (movie_id, category_id) VALUES
                (11, 1),
                (12, 1),
                (13, 2)
        ");

        $service = $this->makeService();
        $result = $service->listMovies([
            'page' => 1,
            'per_page' => 10,
            'search' => 'movie',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(3, $result['data']['summary']['total']);
        $this->assertSame(1, $result['data']['summary']['now_showing']);
        $this->assertSame(1, $result['data']['summary']['coming_soon']);
        $this->assertSame(1, $result['data']['summary']['draft']);
        $this->assertSame(0, $result['data']['summary']['ended']);
        $this->assertCount(3, $result['data']['items']);
    }

    public function testListCategoriesReturnsSummaryCounts(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
                age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES
                (21, 1, 'alpha-movie', 'Alpha Movie', NULL, 120, '2026-03-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (22, 2, 'drama-night', 'Drama Night', NULL, 100, '2026-03-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'coming_soon', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_category_assignments (movie_id, category_id) VALUES
                (21, 1),
                (22, 2)
        ");
        $this->db->exec("
            INSERT INTO movie_categories (id, name, slug, description, display_order, is_active, created_at, updated_at)
            VALUES (3, 'Comedy', 'comedy', 'Comedy', 3, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listCategories([
            'page' => 1,
            'per_page' => 10,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(3, $result['data']['summary']['total']);
        $this->assertSame(2, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['inactive']);
        $this->assertSame(2, $result['data']['summary']['tagged_movies']);
        $this->assertCount(3, $result['data']['items']);
    }

    public function testListAssetsReturnsSummaryCounts(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
                age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES
                (31, 1, 'alpha-assets', 'Alpha Assets', NULL, 120, '2026-03-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (32, 2, 'beta-assets', 'Beta Assets', NULL, 105, '2026-03-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'coming_soon', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_images (id, movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at) VALUES
                (41, 31, 'poster', 'https://example.com/poster.jpg', 'Poster', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (42, 31, 'banner', 'https://example.com/banner.jpg', 'Banner', 2, 0, 'draft', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (43, 32, 'gallery', 'https://example.com/gallery.jpg', 'Gallery', 3, 0, 'archived', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listAssets([
            'page' => 1,
            'per_page' => 10,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(3, $result['data']['summary']['total']);
        $this->assertSame(1, $result['data']['summary']['poster']);
        $this->assertSame(1, $result['data']['summary']['banner']);
        $this->assertSame(1, $result['data']['summary']['gallery']);
        $this->assertSame(1, $result['data']['summary']['draft']);
        $this->assertSame(1, $result['data']['summary']['active']);
        $this->assertSame(1, $result['data']['summary']['archived']);
        $this->assertCount(3, $result['data']['items']);
    }

    public function testCreateAssetPromotesSinglePrimaryPerMovieAndType(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
                age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES (
                33, 1, 'asset-target', 'Asset Target', NULL, 118, '2026-03-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            INSERT INTO movie_images (id, movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at)
            VALUES (51, 33, 'poster', 'https://example.com/poster-old.jpg', 'Old poster', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->createAsset([
            'movie_id' => 33,
            'asset_type' => 'poster',
            'image_url' => 'https://example.com/poster-new.jpg',
            'alt_text' => 'New poster',
            'sort_order' => 2,
            'is_primary' => 1,
            'status' => 'active',
        ], 10);

        $this->assertSame(201, $result['status']);

        $primaryCount = (int) $this->db->query("SELECT COUNT(*) FROM movie_images WHERE movie_id = 33 AND asset_type = 'poster' AND is_primary = 1")->fetchColumn();
        $oldAssetPrimary = (int) $this->db->query("SELECT is_primary FROM movie_images WHERE id = 51")->fetchColumn();
        $newAssetRow = $this->db->query("SELECT image_url, is_primary, status FROM movie_images WHERE id <> 51 ORDER BY id DESC LIMIT 1")->fetch();

        $this->assertSame(1, $primaryCount);
        $this->assertSame(0, $oldAssetPrimary);
        $this->assertSame('https://example.com/poster-new.jpg', $newAssetRow['image_url']);
        $this->assertSame(1, (int) $newAssetRow['is_primary']);
        $this->assertSame('active', $newAssetRow['status']);
    }

    public function testCreateMovieRollsBackWhenAssignmentWriteFails(): void
    {
        $service = new MovieManagementService(
            $this->db,
            new MovieRepository($this->db),
            new MovieCategoryRepository($this->db),
            new FailingAssignmentRepository($this->db),
            new MovieImageRepository($this->db),
            new MovieReviewRepository($this->db),
            new MovieManagementValidator(),
            new IntegrationFakeLogger()
        );

        $result = $service->createMovie([
            'title' => 'Rollback Movie',
            'primary_category_id' => 1,
            'duration_minutes' => 130,
            'status' => 'draft',
            'category_ids' => [1, 2],
        ], 10);

        $this->assertSame(500, $result['status']);
        $movieCount = (int) $this->db->query('SELECT COUNT(*) FROM movies')->fetchColumn();
        $assignmentCount = (int) $this->db->query('SELECT COUNT(*) FROM movie_category_assignments')->fetchColumn();

        $this->assertSame(0, $movieCount);
        $this->assertSame(0, $assignmentCount);
    }

    public function testModerateReviewUpdatesReviewAndMovieSummary(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
                age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES (
                5, 1, 'rated-movie', 'Rated Movie', NULL, 120, '2026-03-14', NULL, NULL,
                NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("INSERT INTO movie_category_assignments (movie_id, category_id) VALUES (5, 1)");
        $this->db->exec("INSERT INTO users (id, name, email, password, phone, role, created_at) VALUES (1, 'Reviewer', 'reviewer@example.com', 'secret', '0900000000', 'user', CURRENT_TIMESTAMP)");
        $this->db->exec("INSERT INTO movie_reviews (id, movie_id, user_id, rating, comment, status, is_visible, moderation_note, created_at, updated_at) VALUES (8, 5, 1, 5, 'Excellent', 'pending', 0, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $service = $this->makeService();
        $result = $service->moderateReview(8, [
            'status' => 'approved',
            'is_visible' => 1,
            'moderation_note' => 'Approved by moderator',
        ], 10);

        $this->assertSame(200, $result['status']);

        $reviewRow = $this->db->query("SELECT status, is_visible, moderation_note FROM movie_reviews WHERE id = 8")->fetch();
        $movieRow = $this->db->query("SELECT average_rating, review_count FROM movies WHERE id = 5")->fetch();

        $this->assertSame('approved', $reviewRow['status']);
        $this->assertSame(1, (int) $reviewRow['is_visible']);
        $this->assertSame('Approved by moderator', $reviewRow['moderation_note']);
        $this->assertSame(5.0, (float) $movieRow['average_rating']);
        $this->assertSame(1, (int) $movieRow['review_count']);
    }

    public function testOphimImportUpdatesExistingMovieAndArchivesPriorAssets(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
                age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES (
                70, 1, 'tro-choi-con-muc', 'Old Title', 'Old summary', 120, '2025-01-01', 'https://example.com/old-poster.jpg',
                NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'draft', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("INSERT INTO movie_category_assignments (movie_id, category_id) VALUES (70, 1)");
        $this->db->exec("
            INSERT INTO movie_images (id, movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at)
            VALUES (71, 70, 'poster', 'https://example.com/old-poster.jpg', 'Old poster', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $syncService = new MovieOphimSyncService(
            $this->db,
            new MovieRepository($this->db),
            new MovieCategoryRepository($this->db),
            new MovieCategoryAssignmentRepository($this->db),
            new MovieImageRepository($this->db),
            new IntegrationFakeOphimClient(
                [
                    'status' => 'success',
                    'data' => [
                        'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                        'item' => [
                            '_id' => 'ophim-demo',
                            'name' => 'Imported Movie',
                            'slug' => 'tro-choi-con-muc',
                            'origin_name' => 'Imported Movie Original',
                            'content' => '<p>Imported from OPhim.</p>',
                            'poster_url' => 'imported-poster.jpg',
                            'trailer_url' => 'https://youtube.com/watch?v=demo',
                            'time' => '181 phut',
                            'episode_current' => 'Full',
                            'lang' => 'Vietsub',
                            'year' => 2026,
                            'actor' => ['Actor A', 'Actor B'],
                            'director' => ['Director A'],
                            'category' => [
                                ['slug' => 'action', 'name' => 'Action'],
                                ['slug' => 'bi-an', 'name' => 'Mystery'],
                            ],
                            'country' => [
                                ['slug' => 'han-quoc', 'name' => 'Korea'],
                            ],
                            'tmdb' => ['vote_average' => 8.4, 'vote_count' => 1200],
                            'imdb' => ['vote_average' => 8.1, 'vote_count' => 900],
                        ],
                    ],
                ],
                [
                    'success' => true,
                    'data' => [
                        'image_sizes' => [
                            'backdrop' => [
                                'w1280' => 'https://image.tmdb.org/t/p/w1280',
                                'original' => 'https://image.tmdb.org/t/p/original',
                            ],
                        ],
                        'images' => [
                            ['type' => 'backdrop', 'file_path' => '/backdrop-a.jpg'],
                            ['type' => 'backdrop', 'file_path' => '/backdrop-b.jpg'],
                        ],
                    ],
                ]
            ),
            new IntegrationFakeLogger()
        );

        $result = $syncService->importBySlug([
            'slug' => 'tro-choi-con-muc',
            'sync_images' => 1,
            'overwrite_existing' => 1,
            'status_override' => 'coming_soon',
        ], 12);

        $this->assertSame(200, $result['status']);
        $this->assertSame(70, $result['data']['movie_id']);
        $this->assertFalse((bool) $result['data']['created']);

        $movieRow = $this->db->query("SELECT title, primary_category_id, duration_minutes, status, poster_url, average_rating FROM movies WHERE id = 70")->fetch();
        $assignmentRows = $this->db->query("SELECT category_id FROM movie_category_assignments WHERE movie_id = 70 ORDER BY category_id ASC")->fetchAll();
        $mysteryCategory = $this->db->query("SELECT id FROM movie_categories WHERE slug = 'bi-an' LIMIT 1")->fetch();
        $archivedAsset = $this->db->query("SELECT status, is_primary FROM movie_images WHERE id = 71")->fetch();
        $activeAssetCount = (int) $this->db->query("SELECT COUNT(*) FROM movie_images WHERE movie_id = 70 AND status = 'active'")->fetchColumn();

        $this->assertSame('Imported Movie', $movieRow['title']);
        $this->assertSame('coming_soon', $movieRow['status']);
        $this->assertSame(181, (int) $movieRow['duration_minutes']);
        $this->assertSame('https://img.ophim.live/uploads/movies/imported-poster.jpg', $movieRow['poster_url']);
        $this->assertSame(4.2, (float) $movieRow['average_rating']);
        $this->assertNotFalse($mysteryCategory);
        $this->assertCount(2, $assignmentRows);
        $this->assertSame('archived', $archivedAsset['status']);
        $this->assertSame(0, (int) $archivedAsset['is_primary']);
        $this->assertSame(4, $activeAssetCount);
    }

    public function testOphimBatchImportCreatesMultipleMoviesFromList(): void
    {
        $syncService = new MovieOphimSyncService(
            $this->db,
            new MovieRepository($this->db),
            new MovieCategoryRepository($this->db),
            new MovieCategoryAssignmentRepository($this->db),
            new MovieImageRepository($this->db),
            new IntegrationFakeOphimClient(
                [
                    'alpha-heist' => [
                        'status' => 'success',
                        'data' => [
                            'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                            'item' => [
                                '_id' => 'ophim-alpha',
                                'name' => 'Alpha Heist',
                                'slug' => 'alpha-heist',
                                'content' => '<p>Alpha summary</p>',
                                'poster_url' => 'alpha-heist.jpg',
                                'trailer_url' => 'https://youtube.com/watch?v=alpha',
                                'time' => '120 phut',
                                'episode_current' => 'Full',
                                'lang' => 'Vietsub',
                                'year' => 2026,
                                'actor' => ['Actor Alpha'],
                                'director' => ['Director Alpha'],
                                'category' => [
                                    ['slug' => 'action', 'name' => 'Action'],
                                ],
                                'country' => [
                                    ['slug' => 'my', 'name' => 'USA'],
                                ],
                                'tmdb' => ['vote_average' => 8.0, 'vote_count' => 500],
                                'imdb' => ['vote_average' => 7.8, 'vote_count' => 300],
                            ],
                        ],
                    ],
                    'beta-circuit' => [
                        'status' => 'success',
                        'data' => [
                            'APP_DOMAIN_CDN_IMAGE' => 'https://img.ophim.live',
                            'item' => [
                                '_id' => 'ophim-beta',
                                'name' => 'Beta Circuit',
                                'slug' => 'beta-circuit',
                                'content' => '<p>Beta summary</p>',
                                'poster_url' => 'beta-circuit.jpg',
                                'trailer_url' => 'https://youtube.com/watch?v=beta',
                                'time' => '95 phut',
                                'episode_current' => 'Full',
                                'lang' => 'Vietsub',
                                'year' => 2025,
                                'actor' => ['Actor Beta'],
                                'director' => ['Director Beta'],
                                'category' => [
                                    ['slug' => 'drama', 'name' => 'Drama'],
                                ],
                                'country' => [
                                    ['slug' => 'kr', 'name' => 'Korea'],
                                ],
                                'tmdb' => ['vote_average' => 7.4, 'vote_count' => 210],
                                'imdb' => ['vote_average' => 7.0, 'vote_count' => 150],
                            ],
                        ],
                    ],
                ],
                [],
                [
                    'status' => 'success',
                    'data' => [
                        'items' => [
                            ['slug' => 'alpha-heist'],
                            ['slug' => 'beta-circuit'],
                        ],
                    ],
                ]
            ),
            new IntegrationFakeLogger()
        );

        $result = $syncService->importList([
            'list_slug' => 'phim-chieu-rap',
            'page' => 1,
            'limit' => 12,
            'sync_images' => 0,
            'overwrite_existing' => 1,
            'status_override' => 'now_showing',
        ], 14);

        $this->assertSame(200, $result['status']);
        $this->assertSame(2, $result['data']['processed_count']);
        $this->assertSame(2, $result['data']['created_count']);
        $this->assertSame(0, $result['data']['failed_count']);

        $movieCount = (int) $this->db->query('SELECT COUNT(*) FROM movies')->fetchColumn();
        $actionMovie = $this->db->query("SELECT title, status FROM movies WHERE slug = 'alpha-heist'")->fetch();
        $dramaMovie = $this->db->query("SELECT title, status FROM movies WHERE slug = 'beta-circuit'")->fetch();
        $assignmentCount = (int) $this->db->query('SELECT COUNT(*) FROM movie_category_assignments')->fetchColumn();

        $this->assertSame(2, $movieCount);
        $this->assertSame('Alpha Heist', $actionMovie['title']);
        $this->assertSame('now_showing', $actionMovie['status']);
        $this->assertSame('Beta Circuit', $dramaMovie['title']);
        $this->assertSame('now_showing', $dramaMovie['status']);
        $this->assertSame(2, $assignmentCount);
    }

    private function makeService(): MovieManagementService
    {
        return new MovieManagementService(
            $this->db,
            new MovieRepository($this->db),
            new MovieCategoryRepository($this->db),
            new MovieCategoryAssignmentRepository($this->db),
            new MovieImageRepository($this->db),
            new MovieReviewRepository($this->db),
            new MovieManagementValidator(),
            new IntegrationFakeLogger()
        );
    }

    private function createSchema(): void
    {
        $this->db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                email TEXT UNIQUE,
                password TEXT,
                phone TEXT,
                role TEXT DEFAULT "user",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE movie_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT,
                display_order INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE movies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                primary_category_id INTEGER,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                summary TEXT,
                duration_minutes INTEGER NOT NULL,
                release_date TEXT,
                poster_url TEXT,
                trailer_url TEXT,
                age_rating TEXT,
                language TEXT,
                director TEXT,
                writer TEXT,
                cast_text TEXT,
                studio TEXT,
                average_rating REAL DEFAULT 0,
                review_count INTEGER DEFAULT 0,
                status TEXT DEFAULT "draft",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (primary_category_id) REFERENCES movie_categories(id)
            )
        ');

        $this->db->exec('
            CREATE TABLE movie_category_assignments (
                movie_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                PRIMARY KEY (movie_id, category_id),
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES movie_categories(id) ON DELETE CASCADE
            )
        ');

        $this->db->exec('
            CREATE TABLE movie_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_id INTEGER NOT NULL,
                asset_type TEXT DEFAULT "gallery",
                image_url TEXT NOT NULL,
                alt_text TEXT,
                sort_order INTEGER DEFAULT 0,
                is_primary INTEGER DEFAULT 0,
                status TEXT DEFAULT "draft",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
            )
        ');

        $this->db->exec('
            CREATE TABLE movie_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                rating INTEGER NOT NULL,
                comment TEXT,
                status TEXT DEFAULT "pending",
                is_visible INTEGER DEFAULT 0,
                moderation_note TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (movie_id) REFERENCES movies(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("INSERT INTO movie_categories (id, name, slug, description, display_order, is_active, created_at, updated_at) VALUES (1, 'Action', 'action', 'Action', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $this->db->exec("INSERT INTO movie_categories (id, name, slug, description, display_order, is_active, created_at, updated_at) VALUES (2, 'Drama', 'drama', 'Drama', 2, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    }
}

class FailingAssignmentRepository extends MovieCategoryAssignmentRepository
{
    public function replaceForMovie(int $movieId, array $categoryIds): void
    {
        throw new RuntimeException('Assignment persistence failed.');
    }
}

class IntegrationFakeLogger extends Logger
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

class IntegrationFakeOphimClient extends OphimClient
{
    private array $detailPayload;
    private array $imagesPayload;
    private array $listPayload;

    public function __construct(array $detailPayload, array $imagesPayload, array $listPayload = [])
    {
        $this->detailPayload = $detailPayload;
        $this->imagesPayload = $imagesPayload;
        $this->listPayload = $listPayload;
    }

    public function getMovieDetail(string $slug): array
    {
        if (isset($this->detailPayload[$slug]) && is_array($this->detailPayload[$slug])) {
            return $this->detailPayload[$slug];
        }

        return $this->detailPayload;
    }

    public function getMovieImages(string $slug): array
    {
        if (isset($this->imagesPayload[$slug]) && is_array($this->imagesPayload[$slug])) {
            return $this->imagesPayload[$slug];
        }

        return $this->imagesPayload;
    }

    public function listBySlug(string $slug, array $query = []): array
    {
        if (isset($this->listPayload[$slug]) && is_array($this->listPayload[$slug])) {
            return $this->listPayload[$slug];
        }

        return $this->listPayload;
    }
}

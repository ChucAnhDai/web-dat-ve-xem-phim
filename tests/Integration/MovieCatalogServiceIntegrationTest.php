<?php

namespace Tests\Integration;

use App\Core\Logger;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
use App\Repositories\ShowtimeRepository;
use App\Services\MovieCatalogService;
use App\Validators\MovieCatalogValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class MovieCatalogServiceIntegrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->seedBaseData();
    }

    public function testListMoviesReturnsOnlyPublicStatusesAndPosterFallback(): void
    {
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url,
                trailer_url, age_rating, language, director, writer, cast_text, studio, average_rating,
                review_count, status, created_at, updated_at
            ) VALUES
                (11, 1, 'alpha-public', 'Alpha Public', 'Summary', 120, '2026-03-14', NULL, NULL, 'T13', 'English', NULL, NULL, NULL, NULL, 4.6, 10, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 1, 'beta-soon', 'Beta Soon', 'Summary', 118, '2026-03-20', 'https://example.com/poster-beta.jpg', NULL, 'T13', 'English', NULL, NULL, NULL, NULL, 4.2, 5, 'coming_soon', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (13, 1, 'gamma-draft', 'Gamma Draft', 'Summary', 105, '2026-03-21', 'https://example.com/poster-gamma.jpg', NULL, 'T13', 'English', NULL, NULL, NULL, NULL, 4.8, 2, 'draft', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_images (id, movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at) VALUES
                (101, 11, 'poster', 'https://example.com/poster-alpha.jpg', 'Poster', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listMovies([
            'status' => 'now_showing',
            'sort' => 'popular',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('alpha-public', $result['data']['items'][0]['slug']);
        $this->assertSame('https://example.com/poster-alpha.jpg', $result['data']['items'][0]['poster_url']);
        $this->assertCount(1, $result['data']['categories']);
        $this->assertSame('Action', $result['data']['categories'][0]['name']);
    }

    public function testListMoviesAppliesCategoryAndRatingFilters(): void
    {
        $this->db->exec("
            INSERT INTO movie_categories (id, name, slug, description, display_order, is_active, created_at, updated_at)
            VALUES (2, 'Drama', 'drama', 'Drama', 2, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url,
                trailer_url, age_rating, language, director, writer, cast_text, studio, average_rating,
                review_count, status, created_at, updated_at
            ) VALUES
                (21, 1, 'action-low', 'Action Low', 'Summary', 120, '2026-03-14', 'https://example.com/action.jpg', NULL, 'T13', 'English', NULL, NULL, NULL, NULL, 3.9, 10, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (22, 2, 'drama-high', 'Drama High', 'Summary', 122, '2026-03-15', 'https://example.com/drama.jpg', NULL, 'T13', 'English', NULL, NULL, NULL, NULL, 4.7, 14, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->listMovies([
            'status' => 'now_showing',
            'category_id' => 2,
            'min_rating' => 4.5,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('drama-high', $result['data']['items'][0]['slug']);
    }

    public function testGetMovieDetailReturnsGalleryShowtimesReviewsAndRelatedMovies(): void
    {
        $this->db->exec("
            INSERT INTO movie_categories (id, name, slug, description, display_order, is_active, created_at, updated_at)
            VALUES (2, 'Drama', 'drama', 'Drama', 2, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO users (id, name, email, password_hash, role, status, created_at, updated_at)
            VALUES (1, 'Jane Reviewer', 'jane@example.com', 'hash', 'user', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO cinemas (id, name, address, city, state, postal_code, phone, email, description, status, created_at, updated_at)
            VALUES (1, 'CinemaX Downtown', '123 Main', 'HCM', 'HCM', '700000', '000', 'cinema@example.com', 'Downtown venue', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO rooms (id, cinema_id, room_name, screen_type, capacity, status, created_at, updated_at)
            VALUES (1, 1, 'Hall 1', 'IMAX', 120, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movies (
                id, primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url,
                trailer_url, age_rating, language, director, writer, cast_text, studio, average_rating,
                review_count, status, created_at, updated_at
            ) VALUES
                (31, 1, 'detail-public', 'Detail Public', 'Detail Summary', 130, '2026-03-14', NULL, 'https://example.com/trailer-detail', 'T16', 'English', 'Director One', 'Writer One', 'Actor A, Actor B', 'Studio One', 4.8, 12, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (32, 1, 'related-public', 'Related Public', 'Related Summary', 118, '2026-03-20', 'https://example.com/related.jpg', NULL, 'T13', 'English', NULL, NULL, NULL, NULL, 4.2, 7, 'now_showing', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_category_assignments (id, movie_id, category_id, created_at, updated_at)
            VALUES
                (1, 31, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 31, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_images (id, movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status, created_at, updated_at) VALUES
                (201, 31, 'poster', 'https://example.com/poster-detail.jpg', 'Poster', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (202, 31, 'banner', 'https://example.com/banner-detail.jpg', 'Banner', 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (203, 31, 'gallery', 'https://example.com/gallery-detail.jpg', 'Gallery', 1, 0, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO movie_reviews (id, movie_id, user_id, rating, comment, status, is_visible, moderation_note, created_at, updated_at) VALUES
                (301, 31, 1, 5, 'Outstanding movie', 'approved', 1, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $this->db->exec("
            INSERT INTO showtimes (id, movie_id, room_id, show_date, start_time, end_time, price, available_seats, status, created_at, updated_at) VALUES
                (401, 31, 1, '2099-03-20', '18:30:00', '20:40:00', 12.50, 100, 'scheduled', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $service = $this->makeService();
        $result = $service->getMovieDetail('detail-public');

        $this->assertSame(200, $result['status']);
        $this->assertSame('Detail Public', $result['data']['movie']['title']);
        $this->assertSame(['Action', 'Drama'], $result['data']['movie']['category_names']);
        $this->assertSame('https://example.com/gallery-detail.jpg', $result['data']['gallery'][0]['image_url']);
        $this->assertSame('CinemaX Downtown', $result['data']['showtime_groups'][0]['venues'][0]['cinema_name']);
        $this->assertSame('Jane Reviewer', $result['data']['reviews'][0]['user_name']);
        $this->assertSame('related-public', $result['data']['related_movies'][0]['slug']);
    }

    private function makeService(): MovieCatalogService
    {
        return new MovieCatalogService(
            new MovieRepository($this->db),
            new MovieCategoryRepository($this->db),
            new MovieCatalogValidator(),
            new IntegrationFakeCatalogLogger(),
            new MovieImageRepository($this->db),
            new MovieReviewRepository($this->db),
            new ShowtimeRepository($this->db)
        );
    }

    private function createSchema(): void
    {
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
            CREATE TABLE movie_category_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES movie_categories(id) ON DELETE CASCADE
            )
        ');

        $this->db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT DEFAULT "user",
                status TEXT DEFAULT "active",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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
                is_visible INTEGER DEFAULT 1,
                moderation_note TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ');

        $this->db->exec('
            CREATE TABLE cinemas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                address TEXT,
                city TEXT,
                state TEXT,
                postal_code TEXT,
                phone TEXT,
                email TEXT,
                description TEXT,
                status TEXT DEFAULT "active",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->db->exec('
            CREATE TABLE rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cinema_id INTEGER NOT NULL,
                room_name TEXT NOT NULL,
                screen_type TEXT,
                capacity INTEGER DEFAULT 0,
                status TEXT DEFAULT "active",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cinema_id) REFERENCES cinemas(id) ON DELETE CASCADE
            )
        ');

        $this->db->exec('
            CREATE TABLE showtimes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_id INTEGER NOT NULL,
                room_id INTEGER NOT NULL,
                show_date TEXT NOT NULL,
                start_time TEXT NOT NULL,
                end_time TEXT,
                price REAL DEFAULT 0,
                available_seats INTEGER DEFAULT 0,
                status TEXT DEFAULT "scheduled",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
            )
        ');
    }

    private function seedBaseData(): void
    {
        $this->db->exec("INSERT INTO movie_categories (id, name, slug, description, display_order, is_active, created_at, updated_at) VALUES (1, 'Action', 'action', 'Action', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
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

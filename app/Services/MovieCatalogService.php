<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
use App\Repositories\ShowtimeRepository;
use App\Validators\MovieCatalogValidator;
use Throwable;

class MovieCatalogService
{
    private MovieRepository $movies;
    private MovieCategoryRepository $categories;
    private MovieCatalogValidator $validator;
    private Logger $logger;
    private MovieImageRepository $images;
    private MovieReviewRepository $reviews;
    private ShowtimeRepository $showtimes;

    public function __construct(
        ?MovieRepository $movies = null,
        ?MovieCategoryRepository $categories = null,
        ?MovieCatalogValidator $validator = null,
        ?Logger $logger = null,
        ?MovieImageRepository $images = null,
        ?MovieReviewRepository $reviews = null,
        ?ShowtimeRepository $showtimes = null
    ) {
        $this->movies = $movies ?? new MovieRepository();
        $this->categories = $categories ?? new MovieCategoryRepository();
        $this->validator = $validator ?? new MovieCatalogValidator();
        $this->logger = $logger ?? new Logger();
        $this->images = $images ?? new MovieImageRepository();
        $this->reviews = $reviews ?? new MovieReviewRepository();
        $this->showtimes = $showtimes ?? new ShowtimeRepository();
    }

    public function listMovies(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeListFilters($filters);

        try {
            $page = $this->movies->paginatePublicCatalog($normalizedFilters);
            $categories = $this->categories->listPublicOptions();
        } catch (Throwable $exception) {
            $this->logger->error('Public movie catalog load failed', [
                'error' => $exception->getMessage(),
                'filters' => $normalizedFilters,
            ]);

            return $this->error(['server' => ['Failed to load movie catalog.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'mapMovie'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'categories' => array_map([$this, 'mapCategory'], $categories),
            'filters' => [
                'status' => $normalizedFilters['status'],
                'sort' => $normalizedFilters['sort'],
                'category_id' => $normalizedFilters['category_id'],
                'min_rating' => $normalizedFilters['min_rating'],
                'search' => $normalizedFilters['search'],
            ],
        ]);
    }

    public function getMovieDetail(string $slug): array
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return $this->error(['movie' => ['Movie not found.']], 404);
        }

        try {
            $movie = $this->movies->findPublicDetailBySlug($normalizedSlug);
            if (!$movie) {
                return $this->error(['movie' => ['Movie not found.']], 404);
            }

            $movieId = (int) ($movie['id'] ?? 0);
            $gallery = $this->images->listActiveAssetsForMovie($movieId, 'gallery');
            $showtimeGroups = $this->showtimes->listUpcomingByMovie($movieId);
            $reviews = $this->reviews->listApprovedVisibleForMovie($movieId, 5);
            $relatedMovies = $this->movies->listPublicRelatedMovies(
                $movieId,
                (int) ($movie['primary_category_id'] ?? 0),
                4
            );
        } catch (Throwable $exception) {
            $this->logger->error('Public movie detail load failed', [
                'slug' => $normalizedSlug,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load movie details.']], 500);
        }

        return $this->success([
            'movie' => $this->mapMovieDetail($movie),
            'gallery' => array_map([$this, 'mapImageAsset'], $gallery),
            'showtime_groups' => array_map([$this, 'mapShowtimeGroup'], $showtimeGroups),
            'reviews' => array_map([$this, 'mapReview'], $reviews),
            'related_movies' => array_map([$this, 'mapMovie'], $relatedMovies),
        ]);
    }

    private function mapMovie(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'primary_category_id' => (int) ($row['primary_category_id'] ?? 0),
            'primary_category_name' => $row['primary_category_name'] ?? null,
            'slug' => $row['slug'] ?? null,
            'title' => $row['title'] ?? null,
            'summary' => $row['summary'] ?? null,
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'release_date' => $row['release_date'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'age_rating' => $row['age_rating'] ?? null,
            'language' => $row['language'] ?? null,
            'average_rating' => round((float) ($row['average_rating'] ?? 0), 2),
            'review_count' => (int) ($row['review_count'] ?? 0),
            'status' => $row['status'] ?? null,
        ];
    }

    private function mapCategory(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $row['name'] ?? null,
            'slug' => $row['slug'] ?? null,
        ];
    }

    private function mapMovieDetail(array $row): array
    {
        $movie = $this->mapMovie($row);
        $movie['banner_url'] = $row['banner_url'] ?? null;
        $movie['trailer_url'] = $row['trailer_url'] ?? null;
        $movie['director'] = $row['director'] ?? null;
        $movie['writer'] = $row['writer'] ?? null;
        $movie['cast_text'] = $row['cast_text'] ?? null;
        $movie['studio'] = $row['studio'] ?? null;
        $movie['category_names'] = $this->csvToStrings($row['category_names_csv'] ?? null);

        if (empty($movie['category_names']) && !empty($movie['primary_category_name'])) {
            $movie['category_names'] = [$movie['primary_category_name']];
        }

        return $movie;
    }

    private function mapImageAsset(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'asset_type' => $row['asset_type'] ?? null,
            'image_url' => $row['image_url'] ?? null,
            'alt_text' => $row['alt_text'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_primary' => (int) ($row['is_primary'] ?? 0),
        ];
    }

    private function mapShowtimeGroup(array $group): array
    {
        return [
            'date' => $group['date'] ?? null,
            'venues' => array_map(static function (array $venue): array {
                return [
                    'cinema_name' => $venue['cinema_name'] ?? null,
                    'room_name' => $venue['room_name'] ?? null,
                    'times' => array_map(static function (array $time): array {
                        return [
                            'id' => (int) ($time['id'] ?? 0),
                            'start_time' => $time['start_time'] ?? null,
                            'price' => isset($time['price']) ? round((float) $time['price'], 2) : null,
                        ];
                    }, $venue['times'] ?? []),
                ];
            }, $group['venues'] ?? []),
        ];
    }

    private function mapReview(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_name' => $row['user_name'] ?? null,
            'rating' => (int) ($row['rating'] ?? 0),
            'comment' => $row['comment'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function paginationMeta(array $page): array
    {
        $totalPages = (int) ceil(($page['total'] ?: 0) / max(1, $page['per_page']));

        return [
            'total' => (int) $page['total'],
            'page' => (int) $page['page'],
            'per_page' => (int) $page['per_page'],
            'total_pages' => max(1, $totalPages),
        ];
    }

    private function success($data, int $status = 200): array
    {
        return [
            'status' => $status,
            'data' => $data,
        ];
    }

    private function error(array $errors, int $status): array
    {
        return [
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function csvToStrings(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $csv)), static function ($value): bool {
            return $value !== '';
        })));
    }
}

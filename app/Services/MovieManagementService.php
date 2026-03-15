<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\MovieCategoryAssignmentRepository;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Repositories\MovieReviewRepository;
use App\Validators\MovieManagementValidator;
use PDO;
use Throwable;

class MovieManagementService
{
    private PDO $db;
    private MovieRepository $movies;
    private MovieCategoryRepository $categories;
    private MovieCategoryAssignmentRepository $assignments;
    private MovieImageRepository $images;
    private MovieReviewRepository $reviews;
    private MovieManagementValidator $validator;
    private Logger $logger;
    private MovieOphimSyncService $ophimSync;

    public function __construct(
        ?PDO $db = null,
        ?MovieRepository $movies = null,
        ?MovieCategoryRepository $categories = null,
        ?MovieCategoryAssignmentRepository $assignments = null,
        ?MovieImageRepository $images = null,
        ?MovieReviewRepository $reviews = null,
        ?MovieManagementValidator $validator = null,
        ?Logger $logger = null,
        ?MovieOphimSyncService $ophimSync = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->movies = $movies ?? new MovieRepository($this->db);
        $this->categories = $categories ?? new MovieCategoryRepository($this->db);
        $this->assignments = $assignments ?? new MovieCategoryAssignmentRepository($this->db);
        $this->images = $images ?? new MovieImageRepository($this->db);
        $this->reviews = $reviews ?? new MovieReviewRepository($this->db);
        $this->validator = $validator ?? new MovieManagementValidator();
        $this->logger = $logger ?? new Logger();
        $this->ophimSync = $ophimSync ?? new MovieOphimSyncService(
            $this->db,
            $this->movies,
            $this->categories,
            $this->assignments,
            $this->images,
            null,
            $this->logger
        );
    }

    public function listMovies(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeMovieFilters($filters);
        $page = $this->movies->paginate($normalizedFilters);
        $summary = $this->buildMovieSummary(
            (int) $page['total'],
            $this->movies->countByStatus($normalizedFilters)
        );

        return $this->success([
            'items' => array_map([$this, 'mapMovie'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $summary,
        ]);
    }

    public function getMovie(int $id): array
    {
        $movie = $this->movies->findById($id);
        if (!$movie) {
            return $this->error(['movie' => ['Movie not found.']], 404);
        }

        return $this->success($this->mapMovie($movie));
    }

    public function createMovie(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $validation = $this->validator->validateMoviePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->movies->findBySlug($data['slug'])) {
            return $this->error(['slug' => ['Movie slug already exists.']], 409);
        }

        $categoryError = $this->validateCategoryReferences($data['category_ids']);
        if ($categoryError !== null) {
            return $categoryError;
        }

        try {
            $movieId = $this->transactional(function () use ($data) {
                $movieId = $this->movies->create($data);
                $this->assignments->replaceForMovie($movieId, $data['category_ids']);

                return $movieId;
            });
        } catch (Throwable $exception) {
            $this->logger->error('Movie creation failed', [
                'actor_id' => $actorId,
                'slug' => $data['slug'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create movie.']], 500);
        }

        $movie = $this->movies->findById($movieId);
        $this->logger->info('Movie created', [
            'actor_id' => $actorId,
            'movie_id' => $movieId,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapMovie($movie ?: []), 201);
    }

    public function updateMovie(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $existing = $this->movies->findById($id);
        if (!$existing) {
            return $this->error(['movie' => ['Movie not found.']], 404);
        }

        $validation = $this->validator->validateMoviePayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->movies->findBySlug($data['slug'], $id)) {
            return $this->error(['slug' => ['Movie slug already exists.']], 409);
        }

        $categoryError = $this->validateCategoryReferences($data['category_ids']);
        if ($categoryError !== null) {
            return $categoryError;
        }

        try {
            $this->transactional(function () use ($id, $data) {
                $this->movies->update($id, $data);
                $this->assignments->replaceForMovie($id, $data['category_ids']);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Movie update failed', [
                'actor_id' => $actorId,
                'movie_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update movie.']], 500);
        }

        $movie = $this->movies->findById($id);
        $this->logger->info('Movie updated', [
            'actor_id' => $actorId,
            'movie_id' => $id,
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapMovie($movie ?: []));
    }

    public function archiveMovie(int $id, ?int $actorId = null): array
    {
        $movie = $this->movies->findById($id);
        if (!$movie) {
            return $this->error(['movie' => ['Movie not found.']], 404);
        }

        if ($this->movies->hasFuturePublishedShowtimes($id)) {
            $this->logger->info('Movie archive blocked by future published showtimes', [
                'actor_id' => $actorId,
                'movie_id' => $id,
            ]);

            return $this->error(['movie' => ['Cannot archive movie while published future showtimes exist.']], 409);
        }

        try {
            $this->movies->archive($id);
        } catch (Throwable $exception) {
            $this->logger->error('Movie archive failed', [
                'actor_id' => $actorId,
                'movie_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to archive movie.']], 500);
        }

        $this->logger->info('Movie archived', [
            'actor_id' => $actorId,
            'movie_id' => $id,
        ]);

        return $this->success(['id' => $id, 'status' => 'archived']);
    }

    public function importMovieFromOphim(array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validateOphimImportPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $result = $this->ophimSync->importBySlug($validation['data'], $actorId);
        if (isset($result['errors'])) {
            return $result;
        }

        $movieId = (int) (($result['data']['movie_id'] ?? 0));
        $movie = $movieId > 0 ? $this->movies->findById($movieId) : null;

        return $this->success([
            'movie' => $this->mapMovie($movie ?: []),
            'sync' => $result['data'],
        ], (int) ($result['status'] ?? 200));
    }

    public function importMovieListFromOphim(array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validateOphimBatchImportPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        return $this->ophimSync->importList($validation['data'], $actorId);
    }

    public function listCategories(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeCategoryFilters($filters);
        $page = $this->categories->paginate($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapCategory'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $this->categories->summarize($normalizedFilters),
        ]);
    }

    public function getCategory(int $id): array
    {
        $category = $this->categories->findById($id);
        if (!$category) {
            return $this->error(['category' => ['Category not found.']], 404);
        }

        return $this->success($this->mapCategory($category));
    }

    public function createCategory(array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validateCategoryPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->categories->findBySlug($data['slug'])) {
            return $this->error(['slug' => ['Category slug already exists.']], 409);
        }

        try {
            $categoryId = $this->categories->create($data);
        } catch (Throwable $exception) {
            $this->logger->error('Category creation failed', [
                'actor_id' => $actorId,
                'slug' => $data['slug'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create category.']], 500);
        }

        $category = $this->categories->findById($categoryId);
        $this->logger->info('Category created', [
            'actor_id' => $actorId,
            'category_id' => $categoryId,
        ]);

        return $this->success($this->mapCategory($category ?: []), 201);
    }

    public function updateCategory(int $id, array $payload, ?int $actorId = null): array
    {
        $existing = $this->categories->findById($id);
        if (!$existing) {
            return $this->error(['category' => ['Category not found.']], 404);
        }

        $validation = $this->validator->validateCategoryPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if ($this->categories->findBySlug($data['slug'], $id)) {
            return $this->error(['slug' => ['Category slug already exists.']], 409);
        }

        try {
            $this->categories->update($id, $data);
        } catch (Throwable $exception) {
            $this->logger->error('Category update failed', [
                'actor_id' => $actorId,
                'category_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update category.']], 500);
        }

        $category = $this->categories->findById($id);
        $this->logger->info('Category updated', [
            'actor_id' => $actorId,
            'category_id' => $id,
        ]);

        return $this->success($this->mapCategory($category ?: []));
    }

    public function deactivateCategory(int $id, ?int $actorId = null): array
    {
        $existing = $this->categories->findById($id);
        if (!$existing) {
            return $this->error(['category' => ['Category not found.']], 404);
        }

        try {
            $this->categories->deactivate($id);
        } catch (Throwable $exception) {
            $this->logger->error('Category deactivation failed', [
                'actor_id' => $actorId,
                'category_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to deactivate category.']], 500);
        }

        $this->logger->info('Category deactivated', [
            'actor_id' => $actorId,
            'category_id' => $id,
        ]);

        return $this->success(['id' => $id, 'is_active' => 0]);
    }

    public function listAssets(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeAssetFilters($filters);
        $page = $this->images->paginate($normalizedFilters);

        return $this->success([
            'items' => array_map([$this, 'mapAsset'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'summary' => $this->buildAssetSummary($this->images->summarize($normalizedFilters)),
        ]);
    }

    public function getAsset(int $id): array
    {
        $asset = $this->images->findById($id);
        if (!$asset) {
            return $this->error(['asset' => ['Movie asset not found.']], 404);
        }

        return $this->success($this->mapAsset($asset));
    }

    public function createAsset(array $payload, ?int $actorId = null): array
    {
        $validation = $this->validator->validateAssetPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if (!$this->movies->findById((int) $data['movie_id'])) {
            return $this->error(['movie_id' => ['Movie not found.']], 404);
        }

        try {
            $assetId = $this->transactional(function () use ($data) {
                if ((int) $data['is_primary'] === 1) {
                    $this->images->clearPrimaryFlagForMovie((int) $data['movie_id'], $data['asset_type']);
                }

                return $this->images->create($data);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Movie asset creation failed', [
                'actor_id' => $actorId,
                'movie_id' => $data['movie_id'],
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to create movie asset.']], 500);
        }

        $asset = $this->images->findById($assetId);
        $this->logger->info('Movie asset created', [
            'actor_id' => $actorId,
            'asset_id' => $assetId,
        ]);

        return $this->success($this->mapAsset($asset ?: []), 201);
    }

    public function updateAsset(int $id, array $payload, ?int $actorId = null): array
    {
        $existing = $this->images->findById($id);
        if (!$existing) {
            return $this->error(['asset' => ['Movie asset not found.']], 404);
        }

        $validation = $this->validator->validateAssetPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        if (!$this->movies->findById((int) $data['movie_id'])) {
            return $this->error(['movie_id' => ['Movie not found.']], 404);
        }

        try {
            $this->transactional(function () use ($id, $data) {
                if ((int) $data['is_primary'] === 1) {
                    $this->images->clearPrimaryFlagForMovie((int) $data['movie_id'], $data['asset_type'], $id);
                }

                $this->images->update($id, $data);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Movie asset update failed', [
                'actor_id' => $actorId,
                'asset_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to update movie asset.']], 500);
        }

        $asset = $this->images->findById($id);
        $this->logger->info('Movie asset updated', [
            'actor_id' => $actorId,
            'asset_id' => $id,
        ]);

        return $this->success($this->mapAsset($asset ?: []));
    }

    public function archiveAsset(int $id, ?int $actorId = null): array
    {
        $existing = $this->images->findById($id);
        if (!$existing) {
            return $this->error(['asset' => ['Movie asset not found.']], 404);
        }

        try {
            $this->images->archive($id);
        } catch (Throwable $exception) {
            $this->logger->error('Movie asset archive failed', [
                'actor_id' => $actorId,
                'asset_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to archive movie asset.']], 500);
        }

        $this->logger->info('Movie asset archived', [
            'actor_id' => $actorId,
            'asset_id' => $id,
        ]);

        return $this->success(['id' => $id, 'status' => 'archived']);
    }

    public function listReviews(array $filters): array
    {
        $page = $this->reviews->paginate($this->validator->normalizeReviewFilters($filters));

        return $this->success([
            'items' => array_map([$this, 'mapReview'], $page['items']),
            'meta' => $this->paginationMeta($page),
        ]);
    }

    public function getReview(int $id): array
    {
        $review = $this->reviews->findById($id);
        if (!$review) {
            return $this->error(['review' => ['Movie review not found.']], 404);
        }

        return $this->success($this->mapReview($review));
    }

    public function moderateReview(int $id, array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $review = $this->reviews->findById($id);
        if (!$review) {
            return $this->error(['review' => ['Movie review not found.']], 404);
        }

        $validation = $this->validator->validateReviewModerationPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error($validation['errors'], 422);
        }

        $data = $validation['data'];
        $movieId = (int) ($review['movie_id'] ?? 0);

        try {
            $this->transactional(function () use ($id, $movieId, $data) {
                $this->reviews->updateModeration($id, $data);
                $stats = $this->reviews->getApprovedVisibleStatsForMovie($movieId);
                $this->movies->updateReviewSummary($movieId, $stats['average_rating'], $stats['review_count']);
            });
        } catch (Throwable $exception) {
            $this->logger->error('Movie review moderation failed', [
                'actor_id' => $actorId,
                'review_id' => $id,
                'movie_id' => $movieId,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to moderate movie review.']], 500);
        }

        $updatedReview = $this->reviews->findById($id);
        $this->logger->info('Movie review moderated', [
            'actor_id' => $actorId,
            'review_id' => $id,
            'movie_id' => $movieId,
            'status' => $data['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return $this->success($this->mapReview($updatedReview ?: []));
    }

    private function validateCategoryReferences(array $categoryIds): ?array
    {
        $existingCount = $this->categories->countByIds($categoryIds);
        if ($existingCount !== count($categoryIds)) {
            return $this->error(['category_ids' => ['One or more categories do not exist.']], 422);
        }

        return null;
    }

    private function transactional(callable $callback)
    {
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function mapMovie(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'primary_category_id' => (int) ($row['primary_category_id'] ?? 0),
            'primary_category_name' => $row['primary_category_name'] ?? null,
            'category_ids' => $this->csvToInts($row['category_ids_csv'] ?? ''),
            'slug' => $row['slug'] ?? null,
            'title' => $row['title'] ?? null,
            'summary' => $row['summary'] ?? null,
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'release_date' => $row['release_date'] ?? null,
            'poster_url' => $row['poster_url'] ?? null,
            'trailer_url' => $row['trailer_url'] ?? null,
            'age_rating' => $row['age_rating'] ?? null,
            'language' => $row['language'] ?? null,
            'director' => $row['director'] ?? null,
            'writer' => $row['writer'] ?? null,
            'cast_text' => $row['cast_text'] ?? null,
            'studio' => $row['studio'] ?? null,
            'average_rating' => round((float) ($row['average_rating'] ?? 0), 2),
            'review_count' => (int) ($row['review_count'] ?? 0),
            'status' => $row['status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapCategory(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $row['name'] ?? null,
            'slug' => $row['slug'] ?? null,
            'description' => $row['description'] ?? null,
            'display_order' => (int) ($row['display_order'] ?? 0),
            'is_active' => (int) ($row['is_active'] ?? 0),
            'movie_count' => (int) ($row['movie_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapAsset(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_title' => $row['movie_title'] ?? null,
            'movie_slug' => $row['movie_slug'] ?? null,
            'asset_type' => $row['asset_type'] ?? null,
            'image_url' => $row['image_url'] ?? null,
            'alt_text' => $row['alt_text'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_primary' => (int) ($row['is_primary'] ?? 0),
            'status' => $row['status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function mapReview(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'movie_title' => $row['movie_title'] ?? null,
            'movie_slug' => $row['movie_slug'] ?? null,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'user_name' => $row['user_name'] ?? null,
            'rating' => (int) ($row['rating'] ?? 0),
            'comment' => $row['comment'] ?? null,
            'status' => $row['status'] ?? null,
            'is_visible' => (int) ($row['is_visible'] ?? 0),
            'moderation_note' => $row['moderation_note'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function csvToInts(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter(explode(',', $csv), static function ($value) {
            return trim((string) $value) !== '';
        }))));
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

    private function buildMovieSummary(int $total, array $statusCounts): array
    {
        $summary = [
            'total' => $total,
        ];

        foreach (MovieManagementValidator::MOVIE_STATUSES as $status) {
            $summary[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        return $summary;
    }

    private function buildAssetSummary(array $summary): array
    {
        return [
            'total' => (int) ($summary['total'] ?? 0),
            'poster' => (int) ($summary['poster'] ?? 0),
            'banner' => (int) ($summary['banner'] ?? 0),
            'gallery' => (int) ($summary['gallery'] ?? 0),
            'draft' => (int) ($summary['draft'] ?? 0),
            'active' => (int) ($summary['active'] ?? 0),
            'archived' => (int) ($summary['archived'] ?? 0),
        ];
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
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
}

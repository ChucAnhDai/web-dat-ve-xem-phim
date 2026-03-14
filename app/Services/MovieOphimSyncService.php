<?php

namespace App\Services;

use App\Clients\OphimClient;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\MovieCategoryAssignmentRepository;
use App\Repositories\MovieCategoryRepository;
use App\Repositories\MovieImageRepository;
use App\Repositories\MovieRepository;
use App\Validators\MovieManagementValidator;
use PDO;
use Throwable;

class MovieOphimSyncService
{
    private PDO $db;
    private MovieRepository $movies;
    private MovieCategoryRepository $categories;
    private MovieCategoryAssignmentRepository $assignments;
    private MovieImageRepository $images;
    private OphimClient $client;
    private Logger $logger;

    public function __construct(
        ?PDO $db = null,
        ?MovieRepository $movies = null,
        ?MovieCategoryRepository $categories = null,
        ?MovieCategoryAssignmentRepository $assignments = null,
        ?MovieImageRepository $images = null,
        ?OphimClient $client = null,
        ?Logger $logger = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->movies = $movies ?? new MovieRepository($this->db);
        $this->categories = $categories ?? new MovieCategoryRepository($this->db);
        $this->assignments = $assignments ?? new MovieCategoryAssignmentRepository($this->db);
        $this->images = $images ?? new MovieImageRepository($this->db);
        $this->logger = $logger ?? new Logger();
        $this->client = $client ?? new OphimClient(null, $this->logger);
    }

    public function importBySlug(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $sourceSlug = trim((string) ($payload['slug'] ?? ''));

        try {
            $detailPayload = $this->client->getMovieDetail($sourceSlug);
            $detailData = is_array($detailPayload['data'] ?? null) ? $detailPayload['data'] : [];
            $item = is_array($detailData['item'] ?? null) ? $detailData['item'] : null;

            if ($item === null || trim((string) ($item['slug'] ?? '')) === '') {
                return $this->error(['slug' => ['OPhim movie not found.']], 404);
            }

            $imagesPayload = [];
            if ((int) ($payload['sync_images'] ?? 1) === 1) {
                try {
                    $imagesPayload = $this->client->getMovieImages((string) $item['slug']);
                } catch (Throwable $exception) {
                    $this->logger->info('OPhim image sync skipped after image request failure', [
                        'slug' => $item['slug'] ?? $sourceSlug,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $existingMovie = $this->movies->findBySlug((string) ($item['slug'] ?? ''));
            if ($existingMovie && (int) ($payload['overwrite_existing'] ?? 1) !== 1) {
                return $this->error(['slug' => ['A local movie with this OPhim slug already exists.']], 409);
            }

            $prepared = $this->prepareImportData($item, $detailData, $imagesPayload, $payload);

            $result = $this->transactional(function () use ($prepared, $existingMovie) {
                $categoryIds = $this->ensureCategoryIds($prepared['categories']);
                $movieData = $prepared['movie'];
                $movieData['primary_category_id'] = (int) $categoryIds[0];

                if ($existingMovie) {
                    $movieId = (int) $existingMovie['id'];
                    $this->movies->update($movieId, $movieData);
                    $created = false;
                } else {
                    $movieId = $this->movies->create($movieData);
                    $created = true;
                }

                $this->assignments->replaceForMovie($movieId, $categoryIds);

                $assetCount = 0;
                if ((int) ($prepared['sync_images'] ?? 1) === 1) {
                    $this->images->archiveByMovieAndTypes($movieId, ['poster', 'banner', 'gallery']);
                    foreach ($prepared['assets'] as $asset) {
                        $asset['movie_id'] = $movieId;
                        $this->images->create($asset);
                        $assetCount += 1;
                    }
                }

                return [
                    'movie_id' => $movieId,
                    'created' => $created,
                    'category_count' => count($categoryIds),
                    'asset_count' => $assetCount,
                ];
            });
        } catch (Throwable $exception) {
            $this->logger->error('OPhim movie import failed', [
                'actor_id' => $actorId,
                'slug' => $sourceSlug,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to import movie from OPhim.']], 500);
        }

        $this->logger->info('OPhim movie imported', [
            'actor_id' => $actorId,
            'source_slug' => $sourceSlug,
            'movie_id' => $result['movie_id'],
            'created' => $result['created'],
            'category_count' => $result['category_count'],
            'asset_count' => $result['asset_count'],
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $this->success([
            'movie_id' => $result['movie_id'],
            'created' => $result['created'],
            'category_count' => $result['category_count'],
            'asset_count' => $result['asset_count'],
            'source_slug' => $sourceSlug,
        ], $result['created'] ? 201 : 200);
    }

    public function importList(array $payload, ?int $actorId = null): array
    {
        $startedAt = microtime(true);
        $listSlug = trim((string) ($payload['list_slug'] ?? ''));
        $page = max(1, (int) ($payload['page'] ?? 1));
        $limit = max(1, min(24, (int) ($payload['limit'] ?? 12)));

        try {
            $listPayload = $this->client->listBySlug($listSlug, [
                'page' => $page,
                'limit' => $limit,
                'sort_field' => 'modified.time',
                'sort_type' => 'desc',
            ]);
            $data = is_array($listPayload['data'] ?? null) ? $listPayload['data'] : [];
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            $slugs = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $slug = trim((string) ($item['slug'] ?? ''));
                if ($slug !== '') {
                    $slugs[$slug] = $slug;
                }
            }

            if (empty($slugs)) {
                return $this->error(['list_slug' => ['No OPhim movies were found for the selected list and page.']], 404);
            }

            $statusOverride = $payload['status_override'] ?? $this->defaultStatusOverrideForList($listSlug);
            $results = [];
            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $failedCount = 0;

            foreach (array_values($slugs) as $slug) {
                $itemResult = $this->importBySlug([
                    'slug' => $slug,
                    'sync_images' => (int) ($payload['sync_images'] ?? 0),
                    'overwrite_existing' => (int) ($payload['overwrite_existing'] ?? 1),
                    'status_override' => $statusOverride,
                ], $actorId);

                if (isset($itemResult['errors'])) {
                    $status = (int) ($itemResult['status'] ?? 500);
                    $message = $this->firstErrorMessage($itemResult['errors'], 'Failed to import movie.');

                    if ($status === 409) {
                        $skippedCount += 1;
                        $results[] = [
                            'slug' => $slug,
                            'result' => 'skipped',
                            'status' => $status,
                            'message' => $message,
                        ];
                        continue;
                    }

                    $failedCount += 1;
                    $results[] = [
                        'slug' => $slug,
                        'result' => 'failed',
                        'status' => $status,
                        'message' => $message,
                    ];
                    continue;
                }

                $created = (bool) ($itemResult['data']['created'] ?? false);
                if ($created) {
                    $createdCount += 1;
                } else {
                    $updatedCount += 1;
                }

                $results[] = [
                    'slug' => $slug,
                    'result' => $created ? 'created' : 'updated',
                    'status' => (int) ($itemResult['status'] ?? 200),
                    'movie_id' => (int) ($itemResult['data']['movie_id'] ?? 0),
                    'category_count' => (int) ($itemResult['data']['category_count'] ?? 0),
                    'asset_count' => (int) ($itemResult['data']['asset_count'] ?? 0),
                ];
            }
        } catch (Throwable $exception) {
            $this->logger->error('OPhim movie list import failed', [
                'actor_id' => $actorId,
                'list_slug' => $listSlug,
                'page' => $page,
                'limit' => $limit,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to import movie list from OPhim.']], 500);
        }

        $this->logger->info('OPhim movie list imported', [
            'actor_id' => $actorId,
            'list_slug' => $listSlug,
            'page' => $page,
            'limit' => $limit,
            'processed_count' => count($results),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $this->success([
            'list_slug' => $listSlug,
            'page' => $page,
            'limit' => $limit,
            'processed_count' => count($results),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'sync_images' => (int) ($payload['sync_images'] ?? 0),
            'overwrite_existing' => (int) ($payload['overwrite_existing'] ?? 1),
            'status_override' => $statusOverride,
            'items' => $results,
        ]);
    }

    private function prepareImportData(array $item, array $detailData, array $imagesPayload, array $payload): array
    {
        $cdnBase = $this->extractCdnBase($detailData);
        $categoryPayloads = $this->extractCategories($item);
        $movieData = [
            'primary_category_id' => 0,
            'slug' => trim((string) ($item['slug'] ?? '')),
            'title' => $this->normalizeTitle($item),
            'summary' => $this->normalizeSummary($item['content'] ?? null),
            'duration_minutes' => $this->parseDurationMinutes($item['time'] ?? null),
            'release_date' => $this->releaseDateFromYear($item['year'] ?? null),
            'poster_url' => $this->buildMovieAssetUrl($cdnBase, $item['poster_url'] ?? null),
            'trailer_url' => $this->nullableString($item['trailer_url'] ?? null),
            'age_rating' => null,
            'language' => $this->nullableString($item['lang'] ?? null),
            'director' => $this->joinStrings($item['director'] ?? []),
            'writer' => null,
            'cast_text' => $this->joinStrings($item['actor'] ?? []),
            'studio' => $this->joinStrings($this->extractCountryNames($item)),
            'average_rating' => $this->normalizeRating($item),
            'review_count' => $this->normalizeVoteCount($item),
            'status' => $this->resolveImportStatus($payload['status_override'] ?? null, $item),
        ];

        $assets = $this->buildAssets($movieData['title'], $movieData['poster_url'], $imagesPayload);

        return [
            'movie' => $movieData,
            'categories' => $categoryPayloads,
            'assets' => $assets,
            'sync_images' => (int) ($payload['sync_images'] ?? 1),
        ];
    }

    private function ensureCategoryIds(array $categories): array
    {
        $categoryIds = [];

        foreach ($categories as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            $name = trim((string) ($category['name'] ?? ''));

            if ($slug === '' || $name === '') {
                continue;
            }

            $existing = $this->categories->findBySlug($slug);
            if ($existing) {
                $categoryIds[] = (int) $existing['id'];
                continue;
            }

            $categoryIds[] = $this->categories->create([
                'name' => $name,
                'slug' => $slug,
                'description' => 'Imported automatically from OPhim.',
                'display_order' => 0,
                'is_active' => 1,
            ]);
        }

        if (!empty($categoryIds)) {
            return array_values(array_unique($categoryIds));
        }

        $fallback = $this->categories->findBySlug('ophim-imported');
        if ($fallback) {
            return [(int) $fallback['id']];
        }

        $fallbackId = $this->categories->create([
            'name' => 'OPhim Imported',
            'slug' => 'ophim-imported',
            'description' => 'Fallback category for OPhim imports without category data.',
            'display_order' => 0,
            'is_active' => 1,
        ]);

        return [$fallbackId];
    }

    private function buildAssets(string $title, ?string $posterUrl, array $imagesPayload): array
    {
        $assets = [];

        if ($posterUrl !== null) {
            $assets[] = [
                'asset_type' => 'poster',
                'image_url' => $posterUrl,
                'alt_text' => trim($title . ' poster'),
                'sort_order' => 1,
                'is_primary' => 1,
                'status' => 'active',
            ];
        }

        $data = is_array($imagesPayload['data'] ?? null) ? $imagesPayload['data'] : [];
        $sizes = is_array($data['image_sizes'] ?? null) ? $data['image_sizes'] : [];
        $images = is_array($data['images'] ?? null) ? $data['images'] : [];

        $backdrops = [];
        foreach ($images as $image) {
            if (!is_array($image) || ($image['type'] ?? null) !== 'backdrop') {
                continue;
            }

            $url = $this->buildTmdbImageUrl($sizes['backdrop'] ?? [], $image['file_path'] ?? null, 'w1280');
            if ($url === null) {
                continue;
            }

            $backdrops[] = $url;
        }

        if (!empty($backdrops)) {
            $assets[] = [
                'asset_type' => 'banner',
                'image_url' => $backdrops[0],
                'alt_text' => trim($title . ' banner'),
                'sort_order' => 1,
                'is_primary' => 1,
                'status' => 'active',
            ];

            foreach (array_slice($backdrops, 0, 8) as $index => $url) {
                $assets[] = [
                    'asset_type' => 'gallery',
                    'image_url' => $url,
                    'alt_text' => trim($title . ' gallery ' . ($index + 1)),
                    'sort_order' => $index + 1,
                    'is_primary' => 0,
                    'status' => 'active',
                ];
            }
        }

        return $assets;
    }

    private function extractCategories(array $item): array
    {
        if (!is_array($item['category'] ?? null)) {
            return [];
        }

        $categories = [];
        foreach ($item['category'] as $category) {
            if (!is_array($category)) {
                continue;
            }

            $slug = trim((string) ($category['slug'] ?? ''));
            $name = trim((string) ($category['name'] ?? ''));
            if ($slug === '' || $name === '') {
                continue;
            }

            $categories[$slug] = [
                'slug' => $slug,
                'name' => $name,
            ];
        }

        return array_values($categories);
    }

    private function extractCountryNames(array $item): array
    {
        if (!is_array($item['country'] ?? null)) {
            return [];
        }

        $countries = [];
        foreach ($item['country'] as $country) {
            if (!is_array($country)) {
                continue;
            }

            $name = trim((string) ($country['name'] ?? ''));
            if ($name !== '') {
                $countries[] = $name;
            }
        }

        return array_values(array_unique($countries));
    }

    private function resolveImportStatus(?string $statusOverride, array $item): string
    {
        $normalized = strtolower(trim((string) ($statusOverride ?? '')));
        if (in_array($normalized, MovieManagementValidator::MOVIE_STATUSES, true)) {
            return $normalized;
        }

        $episodeCurrent = strtolower(trim((string) ($item['episode_current'] ?? '')));
        if (str_contains($episodeCurrent, 'trailer')) {
            return 'coming_soon';
        }

        return 'now_showing';
    }

    private function normalizeTitle(array $item): string
    {
        $title = trim((string) ($item['name'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return trim((string) ($item['origin_name'] ?? 'Imported OPhim Movie'));
    }

    private function normalizeSummary($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $plainText = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/\s+/', ' ', $plainText) ?? '';
        $plainText = trim($plainText);

        return $plainText === '' ? null : $plainText;
    }

    private function parseDurationMinutes($value): int
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return 90;
        }

        if (preg_match('/(\d+)/', $text, $matches) !== 1) {
            return 90;
        }

        return max(1, min(500, (int) $matches[1]));
    }

    private function releaseDateFromYear($value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $year = (int) $value;
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        return sprintf('%04d-01-01', $year);
    }

    private function normalizeRating(array $item): float
    {
        $tmdb = isset($item['tmdb']['vote_average']) ? (float) $item['tmdb']['vote_average'] : 0.0;
        $imdb = isset($item['imdb']['vote_average']) ? (float) $item['imdb']['vote_average'] : 0.0;
        $sourceRating = max($tmdb, $imdb);

        return round(max(0.0, min(5.0, $sourceRating / 2)), 2);
    }

    private function normalizeVoteCount(array $item): int
    {
        $tmdb = isset($item['tmdb']['vote_count']) ? (int) $item['tmdb']['vote_count'] : 0;
        $imdb = isset($item['imdb']['vote_count']) ? (int) $item['imdb']['vote_count'] : 0;

        return max($tmdb, $imdb);
    }

    private function extractCdnBase(array $detailData): ?string
    {
        $cdnBase = trim((string) ($detailData['APP_DOMAIN_CDN_IMAGE'] ?? ''));

        return $cdnBase === '' ? null : rtrim($cdnBase, '/');
    }

    private function buildMovieAssetUrl(?string $cdnBase, ?string $path): ?string
    {
        $cleanPath = trim((string) ($path ?? ''));
        if ($cleanPath === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $cleanPath) === 1) {
            return $cleanPath;
        }

        if ($cdnBase === null) {
            return $cleanPath;
        }

        $normalizedPath = ltrim($cleanPath, '/');
        if (!str_contains($normalizedPath, '/')) {
            $normalizedPath = 'uploads/movies/' . $normalizedPath;
        }

        return $cdnBase . '/' . $normalizedPath;
    }

    private function buildTmdbImageUrl(array $sizeMap, ?string $filePath, string $preferredSize): ?string
    {
        $path = trim((string) ($filePath ?? ''));
        if ($path === '') {
            return null;
        }

        $baseUrl = trim((string) ($sizeMap[$preferredSize] ?? $sizeMap['original'] ?? ''));
        if ($baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function joinStrings($values): ?string
    {
        if (is_string($values)) {
            return $this->nullableString($values);
        }

        if (!is_array($values)) {
            return null;
        }

        $normalized = array_values(array_filter(array_map(function ($value) {
            return $this->nullableString($value) ?? '';
        }, $values), static function ($value): bool {
            return $value !== '';
        }));

        return empty($normalized) ? null : implode(', ', array_unique($normalized));
    }

    private function nullableString($value): ?string
    {
        $cleaned = trim((string) ($value ?? ''));

        return $cleaned === '' ? null : $cleaned;
    }

    private function defaultStatusOverrideForList(string $listSlug): ?string
    {
        $normalized = strtolower(trim($listSlug));

        if (in_array($normalized, ['phim-chieu-rap', 'phim-bo-dang-chieu'], true)) {
            return 'now_showing';
        }

        if ($normalized === 'phim-sap-chieu') {
            return 'coming_soon';
        }

        if ($normalized === 'phim-bo-hoan-thanh') {
            return 'ended';
        }

        return null;
    }

    private function firstErrorMessage(array $errors, string $fallback): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && !empty($messages)) {
                return (string) $messages[0];
            }
        }

        return $fallback;
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

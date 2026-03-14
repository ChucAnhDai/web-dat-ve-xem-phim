<?php

namespace App\Services;

use App\Clients\OphimClient;
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
    private const LIST_SLUGS = [
        'now_showing' => 'phim-chieu-rap',
        'coming_soon' => 'phim-sap-chieu',
    ];

    private OphimClient $client;
    private MovieCatalogValidator $validator;
    private Logger $logger;
    private MovieRepository $movies;
    private MovieCategoryRepository $categories;
    private MovieImageRepository $images;
    private MovieReviewRepository $reviews;
    private ShowtimeRepository $showtimes;

    public function __construct(
        ?OphimClient $client = null,
        ?MovieCatalogValidator $validator = null,
        ?Logger $logger = null,
        ?MovieRepository $movies = null,
        ?MovieCategoryRepository $categories = null,
        ?MovieImageRepository $images = null,
        ?MovieReviewRepository $reviews = null,
        ?ShowtimeRepository $showtimes = null
    ) {
        $this->logger = $logger ?? new Logger();
        $this->client = $client ?? new OphimClient(null, $this->logger);
        $this->validator = $validator ?? new MovieCatalogValidator();
        $this->movies = $movies ?? new MovieRepository();
        $this->categories = $categories ?? new MovieCategoryRepository();
        $this->images = $images ?? new MovieImageRepository();
        $this->reviews = $reviews ?? new MovieReviewRepository();
        $this->showtimes = $showtimes ?? new ShowtimeRepository();
    }

    public function listMovies(array $filters): array
    {
        $normalizedFilters = $this->validator->normalizeListFilters($filters);

        if ($this->hasSearchQuery($normalizedFilters)) {
            return $this->searchOphimMovies($normalizedFilters);
        }

        try {
            if ($this->hasLocalPublicCatalog()) {
                return $this->listLocalMovies($normalizedFilters);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Local-first movie catalog detection failed', [
                'error' => $exception->getMessage(),
                'filters' => $normalizedFilters,
            ]);
        }

        return $this->listOphimMovies($normalizedFilters);
    }

    public function getMovieDetail(string $slug): array
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return $this->error(['movie' => ['Movie not found.']], 404);
        }

        try {
            $localMovie = $this->movies->findPublicDetailBySlug($normalizedSlug);
            if ($localMovie) {
                return $this->buildLocalMovieDetail($localMovie);
            }
        } catch (Throwable $exception) {
            $this->logger->error('Local movie detail lookup failed', [
                'slug' => $normalizedSlug,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $detailPayload = $this->client->getMovieDetail($normalizedSlug);
            $detailData = is_array($detailPayload['data'] ?? null) ? $detailPayload['data'] : [];
            $item = is_array($detailData['item'] ?? null) ? $detailData['item'] : null;

            if (!$item || empty($item['slug'])) {
                return $this->error(['movie' => ['Movie not found.']], 404);
            }

            $detailCdnBase = $this->extractCdnBase($detailData);
            $imagesPayload = $this->safeLoadMovieImages($normalizedSlug);
            $gallery = $this->mapGallery($imagesPayload);
            $movie = $this->mapMovieDetail($item, $detailCdnBase, $gallery);
            $relatedMovies = $this->loadRelatedMovies($item, $movie['status']);
        } catch (Throwable $exception) {
            $this->logger->error('Public movie detail load failed from OPhim', [
                'slug' => $normalizedSlug,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load movie details.']], 500);
        }

        return $this->success([
            'movie' => $movie,
            'showtimes' => [],
            'gallery' => $gallery,
            'reviews' => [],
            'related_movies' => $relatedMovies,
            'source' => [
                'provider' => 'ophim',
                'detail_slug' => $normalizedSlug,
            ],
        ]);
    }

    private function listLocalMovies(array $filters): array
    {
        try {
            $page = $this->movies->paginatePublicCatalog($filters);
            $categories = $this->categories->listPublicOptions();
        } catch (Throwable $exception) {
            $this->logger->error('Public movie catalog load failed from local database', [
                'error' => $exception->getMessage(),
                'filters' => $filters,
            ]);

            return $this->error(['server' => ['Failed to load movie catalog.']], 500);
        }

        return $this->success([
            'items' => array_map([$this, 'mapLocalCatalogMovie'], $page['items']),
            'meta' => $this->paginationMeta($page),
            'categories' => array_map(static function (array $category): array {
                return [
                    'id' => $category['slug'] ?? null,
                    'name' => $category['name'] ?? null,
                    'slug' => $category['slug'] ?? null,
                ];
            }, $categories),
            'filters' => [
                'status' => $filters['status'],
                'sort' => $filters['sort'],
                'category_id' => $filters['category_id'],
                'min_rating' => $filters['min_rating'],
                'search' => $filters['search'],
            ],
            'source' => [
                'provider' => 'local',
                'mode' => 'admin-managed',
            ],
        ]);
    }

    private function listOphimMovies(array $filters): array
    {
        $listSlug = $this->resolveListSlug($filters['status']);

        try {
            $payload = $this->client->listBySlug($listSlug, $this->buildListQuery($filters));
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
            $cdnBase = $this->extractCdnBase($data);

            $items = array_map(function (array $item) use ($cdnBase, $filters): array {
                return $this->mapCatalogMovie($item, $cdnBase, $filters['status']);
            }, $rawItems);

            $items = $this->filterCatalogItems($items, $filters);
            $items = $this->sortCatalogMovies($items, $filters['sort']);
            $categories = $this->collectCategories($rawItems);
            $meta = $this->buildCatalogMeta($data['params']['pagination'] ?? [], $items, $filters);
        } catch (Throwable $exception) {
            $this->logger->error('Public movie catalog load failed from OPhim', [
                'error' => $exception->getMessage(),
                'filters' => $filters,
            ]);

            return $this->error(['server' => ['Failed to load movie catalog.']], 500);
        }

        return $this->success([
            'items' => $items,
            'meta' => $meta,
            'categories' => $categories,
            'filters' => [
                'status' => $filters['status'],
                'sort' => $filters['sort'],
                'category_id' => $filters['category_id'],
                'min_rating' => $filters['min_rating'],
                'search' => $filters['search'],
            ],
            'source' => [
                'provider' => 'ophim',
                'list_slug' => $listSlug,
            ],
        ]);
    }

    private function searchOphimMovies(array $filters): array
    {
        $keyword = (string) ($filters['search'] ?? '');

        try {
            $payload = $this->client->searchMovies($keyword, [
                'page' => $filters['page'],
                'limit' => $filters['per_page'],
            ]);
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
            $cdnBase = $this->extractCdnBase($data);

            $items = array_map(function (array $item) use ($cdnBase): array {
                return $this->mapCatalogMovie($item, $cdnBase, null);
            }, $rawItems);

            $items = $this->filterCatalogItems($items, $filters, false);
            $items = $this->sortCatalogMovies($items, $filters['sort']);
            $categories = $this->collectCategories($rawItems);
            $meta = $this->searchPaginationMeta($data['params']['pagination'] ?? [], $filters);
        } catch (Throwable $exception) {
            $this->logger->error('Public movie search failed from OPhim', [
                'error' => $exception->getMessage(),
                'filters' => $filters,
            ]);

            return $this->error(['server' => ['Failed to search movie catalog.']], 500);
        }

        return $this->success([
            'items' => $items,
            'meta' => $meta,
            'categories' => $categories,
            'filters' => [
                'status' => $filters['status'],
                'sort' => $filters['sort'],
                'category_id' => $filters['category_id'],
                'min_rating' => $filters['min_rating'],
                'search' => $filters['search'],
            ],
            'source' => [
                'provider' => 'ophim',
                'mode' => 'search',
                'keyword' => $keyword,
            ],
        ]);
    }

    private function buildLocalMovieDetail(array $row): array
    {
        $movieId = (int) ($row['id'] ?? 0);
        $movie = $this->mapLocalMovieDetail($row);

        try {
            $assets = $this->images->listActiveAssetsForMovie($movieId);
            $reviews = $this->reviews->listApprovedVisibleForMovie($movieId, 5);
            $related = $this->movies->listPublicRelatedMovies($movieId, (int) ($row['primary_category_id'] ?? 0), 4);
            $showtimes = $this->showtimes->listUpcomingByMovie($movieId);
        } catch (Throwable $exception) {
            $this->logger->error('Local movie detail dependencies failed to load', [
                'movie_id' => $movieId,
                'slug' => $row['slug'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return $this->error(['server' => ['Failed to load movie details.']], 500);
        }

        $gallery = $this->mapLocalGallery($movie, $assets);
        if (empty($gallery)) {
            $gallery = $this->loadOphimGalleryForSlug((string) ($row['slug'] ?? ''));
        }

        if (($movie['banner_url'] ?? null) === null && !empty($gallery[0]['image_url'])) {
            $movie['banner_url'] = $gallery[0]['image_url'];
        }

        return $this->success([
            'movie' => $movie,
            'showtimes' => $this->mapLocalShowtimes($showtimes),
            'gallery' => $gallery,
            'reviews' => array_map([$this, 'mapLocalReview'], $reviews),
            'related_movies' => array_map([$this, 'mapLocalCatalogMovie'], $related),
            'source' => [
                'provider' => 'local',
                'mode' => 'admin-managed',
            ],
        ]);
    }

    private function buildListQuery(array $filters): array
    {
        return [
            'page' => $filters['page'],
            'limit' => $filters['per_page'],
            'sort_field' => $filters['sort'] === 'newest' ? 'year' : 'modified.time',
            'sort_type' => 'desc',
            'category' => $filters['category_id'],
        ];
    }

    private function resolveListSlug(string $status): string
    {
        return self::LIST_SLUGS[$status] ?? self::LIST_SLUGS['now_showing'];
    }

    private function hasLocalPublicCatalog(): bool
    {
        return $this->movies->countPublicCatalog() > 0;
    }

    private function hasSearchQuery(array $filters): bool
    {
        return trim((string) ($filters['search'] ?? '')) !== '';
    }

    private function mapCatalogMovie(array $item, ?string $cdnBase, ?string $requestedStatus = null): array
    {
        $primaryCategory = $this->extractPrimaryCategory($item);
        $status = $this->inferStatus($item, $requestedStatus);

        return [
            'id' => $this->stableId($item['slug'] ?? $item['_id'] ?? ''),
            'primary_category_id' => $primaryCategory['slug'] ?? null,
            'primary_category_name' => $primaryCategory['name'] ?? null,
            'slug' => $item['slug'] ?? null,
            'title' => $item['name'] ?? null,
            'summary' => $this->buildCatalogSummary($item),
            'duration_minutes' => $this->parseDurationMinutes($item['time'] ?? null),
            'release_date' => $this->releaseDateFromYear($item['year'] ?? null),
            'poster_url' => $this->buildPreferredPosterUrl($cdnBase, $item),
            'age_rating' => $item['quality'] ?? null,
            'language' => $item['lang'] ?? null,
            'average_rating' => $this->normalizeRating($item),
            'review_count' => $this->normalizeVoteCount($item),
            'status' => $status,
        ];
    }

    private function mapMovieDetail(array $item, ?string $cdnBase, array $gallery): array
    {
        $movie = $this->mapCatalogMovie($item, $cdnBase, $this->inferStatus($item));
        $movie['banner_url'] = $this->extractBannerUrl($gallery) ?: $this->buildPreferredBannerUrl($cdnBase, $item);
        $movie['trailer_url'] = $item['trailer_url'] ?? null;
        $movie['director'] = $this->joinStrings($item['director'] ?? []);
        $movie['writer'] = null;
        $movie['cast_text'] = $this->joinStrings($item['actor'] ?? []);
        $movie['studio'] = $this->joinStrings($this->extractCountryNames($item));
        $movie['category_names'] = $this->extractCategoryNames($item);

        return $movie;
    }

    private function mapLocalCatalogMovie(array $row): array
    {
        $posterUrl = $this->normalizePublicPosterUrl($row['poster_url'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'primary_category_id' => (int) ($row['primary_category_id'] ?? 0),
            'primary_category_name' => $row['primary_category_name'] ?? null,
            'slug' => $row['slug'] ?? null,
            'title' => $row['title'] ?? null,
            'summary' => $row['summary'] ?? null,
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'release_date' => $row['release_date'] ?? null,
            'poster_url' => $posterUrl,
            'age_rating' => $row['age_rating'] ?? null,
            'language' => $row['language'] ?? null,
            'average_rating' => round((float) ($row['average_rating'] ?? 0), 2),
            'review_count' => (int) ($row['review_count'] ?? 0),
            'status' => $row['status'] ?? null,
        ];
    }

    private function mapLocalMovieDetail(array $row): array
    {
        $rawPosterUrl = $row['poster_url'] ?? null;
        $posterUrl = $this->normalizePublicPosterUrl($rawPosterUrl);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'primary_category_id' => (int) ($row['primary_category_id'] ?? 0),
            'primary_category_name' => $row['primary_category_name'] ?? null,
            'slug' => $row['slug'] ?? null,
            'title' => $row['title'] ?? null,
            'summary' => $row['summary'] ?? null,
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'release_date' => $row['release_date'] ?? null,
            'poster_url' => $posterUrl,
            'banner_url' => $this->normalizePublicBannerUrl($row['banner_url'] ?? null, $rawPosterUrl),
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
            'category_names' => $this->csvToArray($row['category_names_csv'] ?? ''),
        ];
    }

    private function mapLocalGallery(array $movie, array $assets): array
    {
        $gallery = [];
        $seenUrls = [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $assetType = (string) ($asset['asset_type'] ?? '');
            $imageUrl = trim((string) ($asset['image_url'] ?? ''));
            if (!in_array($assetType, ['banner', 'gallery'], true) || $imageUrl === '' || isset($seenUrls[$imageUrl])) {
                continue;
            }

            $gallery[] = [
                'id' => (int) ($asset['id'] ?? count($gallery) + 1),
                'asset_type' => $assetType,
                'image_url' => $imageUrl,
                'alt_text' => $asset['alt_text'] ?? ($movie['title'] ?? 'Movie image'),
                'sort_order' => (int) ($asset['sort_order'] ?? count($gallery) + 1),
                'is_primary' => (int) ($asset['is_primary'] ?? 0),
            ];
            $seenUrls[$imageUrl] = true;
        }

        if (empty($gallery) && !empty($movie['banner_url'])) {
            $gallery[] = [
                'id' => 1,
                'asset_type' => 'banner',
                'image_url' => $movie['banner_url'],
                'alt_text' => ($movie['title'] ?? 'Movie') . ' banner',
                'sort_order' => 1,
                'is_primary' => 1,
            ];
        }

        return $gallery;
    }

    private function mapLocalReview(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'movie_id' => (int) ($row['movie_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'user_name' => $row['user_name'] ?? null,
            'rating' => (int) ($row['rating'] ?? 0),
            'comment' => $row['comment'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function mapLocalShowtimes(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $venues = [];
            foreach (($row['venues'] ?? []) as $venue) {
                if (!is_array($venue)) {
                    continue;
                }

                $times = [];
                foreach (($venue['times'] ?? []) as $time) {
                    if (!is_array($time)) {
                        continue;
                    }

                    $times[] = [
                        'id' => (int) ($time['id'] ?? 0),
                        'start_time' => $time['start_time'] ?? null,
                        'start_time_label' => $this->formatTimeLabel($time['start_time'] ?? null),
                        'price' => isset($time['price']) ? (float) $time['price'] : null,
                    ];
                }

                if (empty($times)) {
                    continue;
                }

                $venues[] = [
                    'cinema_name' => $venue['cinema_name'] ?? null,
                    'room_name' => $venue['room_name'] ?? null,
                    'times' => $times,
                ];
            }

            if (empty($venues)) {
                continue;
            }

            $groups[] = [
                'date' => $row['date'] ?? null,
                'is_today' => ($row['date'] ?? null) === date('Y-m-d'),
                'venues' => $venues,
            ];
        }

        return $groups;
    }

    private function mapGallery(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $sizes = is_array($data['image_sizes'] ?? null) ? $data['image_sizes'] : [];
        $images = is_array($data['images'] ?? null) ? $data['images'] : [];
        $gallery = [];

        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                continue;
            }

            $type = (string) ($image['type'] ?? 'gallery');
            $sizeMap = is_array($sizes[$type] ?? null) ? $sizes[$type] : [];
            $baseUrl = $sizeMap[$type === 'backdrop' ? 'w1280' : 'w780'] ?? $sizeMap['original'] ?? null;
            $filePath = trim((string) ($image['file_path'] ?? ''));

            if (!$baseUrl || $filePath === '') {
                continue;
            }

            $gallery[] = [
                'id' => $index + 1,
                'asset_type' => $type === 'poster' ? 'poster' : 'gallery',
                'image_url' => rtrim($baseUrl, '/') . '/' . ltrim($filePath, '/'),
                'alt_text' => sprintf('%s image %d', ucfirst($type), $index + 1),
                'sort_order' => $index + 1,
                'is_primary' => $index === 0 ? 1 : 0,
            ];

            if (count($gallery) >= 10) {
                break;
            }
        }

        return $gallery;
    }

    private function loadOphimGalleryForSlug(string $slug): array
    {
        if (trim($slug) === '') {
            return [];
        }

        $payload = $this->safeLoadMovieImages($slug);

        return $this->mapGallery($payload);
    }

    private function loadRelatedMovies(array $item, string $status): array
    {
        $listSlug = $this->resolveListSlug($status);
        $categorySlug = $this->extractPrimaryCategory($item)['slug'] ?? null;
        $currentSlug = (string) ($item['slug'] ?? '');

        try {
            $query = [
                'page' => 1,
                'limit' => 12,
                'sort_field' => 'modified.time',
                'sort_type' => 'desc',
                'category' => $categorySlug,
            ];
            $payload = $this->client->listBySlug($listSlug, $query);
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
            $cdnBase = $this->extractCdnBase($data);

            $mapped = [];
            foreach ($rawItems as $relatedItem) {
                if (!is_array($relatedItem) || ($relatedItem['slug'] ?? '') === $currentSlug) {
                    continue;
                }

                $mapped[] = $this->mapCatalogMovie($relatedItem, $cdnBase, $status);
                if (count($mapped) >= 4) {
                    break;
                }
            }

            return $mapped;
        } catch (Throwable $exception) {
            $this->logger->info('Related OPhim movies could not be loaded', [
                'slug' => $currentSlug,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function filterCatalogItems(array $items, array $filters, bool $applySearch = true): array
    {
        $search = $applySearch ? strtolower((string) ($filters['search'] ?? '')) : '';
        $minRating = $filters['min_rating'];
        $categoryId = strtolower(trim((string) ($filters['category_id'] ?? '')));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));

        return array_values(array_filter($items, static function (array $item) use ($search, $minRating, $categoryId, $status): bool {
            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    $item['title'] ?? '',
                    $item['summary'] ?? '',
                    $item['primary_category_name'] ?? '',
                    $item['language'] ?? '',
                ])));

                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            if ($categoryId !== '' && strtolower((string) ($item['primary_category_id'] ?? '')) !== $categoryId) {
                return false;
            }

            if ($minRating !== null && (float) ($item['average_rating'] ?? 0) < (float) $minRating) {
                return false;
            }

            if ($status !== '' && ($item['status'] ?? '') !== $status) {
                return false;
            }

            return true;
        }));
    }

    private function sortCatalogMovies(array $items, string $sort): array
    {
        usort($items, static function (array $left, array $right) use ($sort): int {
            if ($sort === 'rating') {
                return [$right['average_rating'] ?? 0, $right['review_count'] ?? 0] <=> [$left['average_rating'] ?? 0, $left['review_count'] ?? 0];
            }

            if ($sort === 'newest') {
                return strcmp((string) ($right['release_date'] ?? ''), (string) ($left['release_date'] ?? ''));
            }

            return [$right['review_count'] ?? 0, $right['average_rating'] ?? 0] <=> [$left['review_count'] ?? 0, $left['average_rating'] ?? 0];
        });

        return $items;
    }

    private function buildCatalogMeta($pagination, array $items, array $filters): array
    {
        $localFiltered = ($filters['search'] ?? null) !== null || ($filters['min_rating'] ?? null) !== null;

        if ($localFiltered) {
            $total = count($items);

            return [
                'total' => $total,
                'page' => 1,
                'per_page' => max(1, count($items)),
                'total_pages' => 1,
            ];
        }

        $total = (int) ($pagination['totalItems'] ?? count($items));
        $perPage = (int) ($pagination['totalItemsPerPage'] ?? $filters['per_page']);
        $page = (int) ($pagination['currentPage'] ?? $filters['page']);

        return [
            'total' => $total,
            'page' => max(1, $page),
            'per_page' => max(1, $perPage),
            'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
    }

    private function searchPaginationMeta($pagination, array $filters): array
    {
        $total = (int) ($pagination['totalItems'] ?? 0);
        $perPage = (int) ($pagination['totalItemsPerPage'] ?? $filters['per_page']);
        $page = (int) ($pagination['currentPage'] ?? $filters['page']);
        $totalPages = (int) ($pagination['totalPages'] ?? 0);

        return [
            'total' => max(0, $total),
            'page' => max(1, $page),
            'per_page' => max(1, $perPage),
            'total_pages' => max(1, $totalPages ?: (int) ceil(max(0, $total) / max(1, $perPage))),
        ];
    }

    private function paginationMeta(array $page): array
    {
        $totalPages = (int) ceil(($page['total'] ?: 0) / max(1, $page['per_page']));

        return [
            'total' => (int) ($page['total'] ?? 0),
            'page' => (int) ($page['page'] ?? 1),
            'per_page' => (int) ($page['per_page'] ?? 12),
            'total_pages' => max(1, $totalPages),
        ];
    }

    private function collectCategories(array $rawItems): array
    {
        $categories = [];

        foreach ($rawItems as $item) {
            if (!is_array($item) || !is_array($item['category'] ?? null)) {
                continue;
            }

            foreach ($item['category'] as $category) {
                if (!is_array($category)) {
                    continue;
                }

                $slug = strtolower(trim((string) ($category['slug'] ?? '')));
                $name = trim((string) ($category['name'] ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }

                $categories[$slug] = [
                    'id' => $slug,
                    'name' => $name,
                    'slug' => $slug,
                ];
            }
        }

        uasort($categories, static function (array $left, array $right): int {
            return strcmp($left['name'], $right['name']);
        });

        return array_values($categories);
    }

    private function safeLoadMovieImages(string $slug): array
    {
        try {
            return $this->client->getMovieImages($slug);
        } catch (Throwable $exception) {
            $this->logger->info('Movie images could not be loaded from OPhim', [
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function extractCdnBase(array $data): ?string
    {
        $value = trim((string) ($data['APP_DOMAIN_CDN_IMAGE'] ?? ''));

        return $value === '' ? null : rtrim($value, '/');
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

        $normalized = ltrim($cleanPath, '/');
        if (!str_contains($normalized, '/')) {
            $normalized = 'uploads/movies/' . $normalized;
        }

        return $cdnBase . '/' . ltrim($normalized, '/');
    }

    private function buildPreferredPosterUrl(?string $cdnBase, array $item): ?string
    {
        return $this->buildMovieAssetUrl($cdnBase, $item['thumb_url'] ?? $item['poster_url'] ?? null);
    }

    private function buildPreferredBannerUrl(?string $cdnBase, array $item): ?string
    {
        return $this->buildMovieAssetUrl($cdnBase, $item['poster_url'] ?? $item['thumb_url'] ?? null);
    }

    private function extractBannerUrl(array $gallery): ?string
    {
        foreach ($gallery as $asset) {
            if (($asset['asset_type'] ?? '') === 'gallery' && !empty($asset['image_url'])) {
                return $asset['image_url'];
            }
        }

        return null;
    }

    private function normalizePublicPosterUrl(?string $posterUrl): ?string
    {
        $cleanedUrl = trim((string) ($posterUrl ?? ''));
        if ($cleanedUrl === '') {
            return null;
        }

        if (!$this->isLikelyOphimMovieAsset($cleanedUrl)) {
            return $cleanedUrl;
        }

        return (string) preg_replace('/-poster(\.[a-z0-9]+)$/i', '-thumb$1', $cleanedUrl, 1);
    }

    private function normalizePublicBannerUrl(?string $bannerUrl, ?string $fallbackPosterUrl = null): ?string
    {
        $cleanedBannerUrl = trim((string) ($bannerUrl ?? ''));
        if ($cleanedBannerUrl !== '') {
            return $cleanedBannerUrl;
        }

        $cleanedPosterUrl = trim((string) ($fallbackPosterUrl ?? ''));
        if ($cleanedPosterUrl === '' || !$this->isLikelyOphimMovieAsset($cleanedPosterUrl)) {
            return $cleanedPosterUrl !== '' ? $cleanedPosterUrl : null;
        }

        return $cleanedPosterUrl;
    }

    private function isLikelyOphimMovieAsset(string $url): bool
    {
        return str_contains($url, '/uploads/movies/') && preg_match('/-poster\.[a-z0-9]+$/i', $url) === 1;
    }

    private function extractPrimaryCategory(array $item): array
    {
        if (!is_array($item['category'] ?? null) || empty($item['category'][0]) || !is_array($item['category'][0])) {
            return ['slug' => null, 'name' => null];
        }

        return [
            'slug' => $item['category'][0]['slug'] ?? null,
            'name' => $item['category'][0]['name'] ?? null,
        ];
    }

    private function extractCategoryNames(array $item): array
    {
        if (!is_array($item['category'] ?? null)) {
            return [];
        }

        $names = [];
        foreach ($item['category'] as $category) {
            if (!is_array($category)) {
                continue;
            }

            $name = trim((string) ($category['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function extractCountryNames(array $item): array
    {
        if (!is_array($item['country'] ?? null)) {
            return [];
        }

        $names = [];
        foreach ($item['country'] as $country) {
            if (!is_array($country)) {
                continue;
            }

            $name = trim((string) ($country['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function buildCatalogSummary(array $item): ?string
    {
        $originName = trim((string) ($item['origin_name'] ?? ''));
        $time = trim((string) ($item['time'] ?? ''));

        if ($originName !== '' && $time !== '') {
            return $originName . ' · ' . $time;
        }

        return $originName !== '' ? $originName : null;
    }

    private function parseDurationMinutes($value): int
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return 0;
        }

        if (preg_match('/(\d+)/', $text, $matches) !== 1) {
            return 0;
        }

        return max(0, (int) $matches[1]);
    }

    private function formatTimeLabel($value): ?string
    {
        $time = trim((string) ($value ?? ''));
        if ($time === '') {
            return null;
        }

        $parsed = strtotime($time);
        if ($parsed === false) {
            return $time;
        }

        return date('g:i A', $parsed);
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

    private function inferStatus(array $item, ?string $fallback = null): string
    {
        if ($fallback !== null) {
            return $fallback;
        }

        $episodeCurrent = strtolower(trim((string) ($item['episode_current'] ?? '')));
        if (str_contains($episodeCurrent, 'trailer')) {
            return 'coming_soon';
        }

        return 'now_showing';
    }

    private function joinStrings($values): ?string
    {
        if (is_string($values)) {
            $cleaned = trim($values);
            return $cleaned === '' ? null : $cleaned;
        }

        if (!is_array($values)) {
            return null;
        }

        $filtered = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $values), static function ($value): bool {
            return $value !== '';
        }));

        return empty($filtered) ? null : implode(', ', $filtered);
    }

    private function csvToArray(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, explode(',', $csv)), static function ($value): bool {
            return $value !== '';
        })));
    }

    private function stableId(string $value): int
    {
        return (int) sprintf('%u', crc32($value));
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

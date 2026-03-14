<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class MovieRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function paginate(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);
        $selectSql = "
            SELECT
                m.*,
                c.name AS primary_category_name,
                COALESCE(GROUP_CONCAT(a.category_id), '') AS category_ids_csv
            FROM movies m
            LEFT JOIN movie_categories c ON c.id = m.primary_category_id
            LEFT JOIN movie_category_assignments a ON a.movie_id = m.id
            {$where}
            GROUP BY
                m.id,
                m.primary_category_id,
                m.slug,
                m.title,
                m.summary,
                m.duration_minutes,
                m.release_date,
                m.poster_url,
                m.trailer_url,
                m.age_rating,
                m.language,
                m.director,
                m.writer,
                m.cast_text,
                m.studio,
                m.average_rating,
                m.review_count,
                m.status,
                m.created_at,
                m.updated_at,
                c.name
            ORDER BY m.updated_at DESC, m.id DESC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM movies m
            LEFT JOIN movie_categories c ON c.id = m.primary_category_id
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function countByStatus(array $filters): array
    {
        ['where' => $where, 'params' => $params] = $this->buildFilterParts($filters);

        $stmt = $this->db->prepare("
            SELECT m.status, COUNT(*) AS total
            FROM movies m
            LEFT JOIN movie_categories c ON c.id = m.primary_category_id
            {$where}
            GROUP BY m.status
        ");
        $this->bindValues($stmt, $params);
        $stmt->execute();

        $counts = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === '') {
                continue;
            }

            $counts[$status] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                m.*,
                c.name AS primary_category_name,
                COALESCE(GROUP_CONCAT(a.category_id), '') AS category_ids_csv
            FROM movies m
            LEFT JOIN movie_categories c ON c.id = m.primary_category_id
            LEFT JOIN movie_category_assignments a ON a.movie_id = m.id
            WHERE m.id = :id
            GROUP BY
                m.id,
                m.primary_category_id,
                m.slug,
                m.title,
                m.summary,
                m.duration_minutes,
                m.release_date,
                m.poster_url,
                m.trailer_url,
                m.age_rating,
                m.language,
                m.director,
                m.writer,
                m.cast_text,
                m.studio,
                m.average_rating,
                m.review_count,
                m.status,
                m.created_at,
                m.updated_at,
                c.name
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM movies WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO movies (
                primary_category_id, slug, title, summary, duration_minutes, release_date,
                poster_url, trailer_url, age_rating, language, director, writer,
                cast_text, studio, average_rating, review_count, status, created_at, updated_at
            ) VALUES (
                :primary_category_id, :slug, :title, :summary, :duration_minutes, :release_date,
                :poster_url, :trailer_url, :age_rating, :language, :director, :writer,
                :cast_text, :studio, :average_rating, :review_count, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )'
        );

        $stmt->execute([
            'primary_category_id' => $data['primary_category_id'],
            'slug' => $data['slug'],
            'title' => $data['title'],
            'summary' => $data['summary'],
            'duration_minutes' => $data['duration_minutes'],
            'release_date' => $data['release_date'],
            'poster_url' => $data['poster_url'],
            'trailer_url' => $data['trailer_url'],
            'age_rating' => $data['age_rating'],
            'language' => $data['language'],
            'director' => $data['director'],
            'writer' => $data['writer'],
            'cast_text' => $data['cast_text'],
            'studio' => $data['studio'],
            'average_rating' => $data['average_rating'],
            'review_count' => $data['review_count'] ?? 0,
            'status' => $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movies
             SET primary_category_id = :primary_category_id,
                 slug = :slug,
                 title = :title,
                 summary = :summary,
                 duration_minutes = :duration_minutes,
                 release_date = :release_date,
                 poster_url = :poster_url,
                 trailer_url = :trailer_url,
                 age_rating = :age_rating,
                 language = :language,
                 director = :director,
                 writer = :writer,
                 cast_text = :cast_text,
                 studio = :studio,
                 average_rating = :average_rating,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'primary_category_id' => $data['primary_category_id'],
            'slug' => $data['slug'],
            'title' => $data['title'],
            'summary' => $data['summary'],
            'duration_minutes' => $data['duration_minutes'],
            'release_date' => $data['release_date'],
            'poster_url' => $data['poster_url'],
            'trailer_url' => $data['trailer_url'],
            'age_rating' => $data['age_rating'],
            'language' => $data['language'],
            'director' => $data['director'],
            'writer' => $data['writer'],
            'cast_text' => $data['cast_text'],
            'studio' => $data['studio'],
            'average_rating' => $data['average_rating'],
            'status' => $data['status'],
        ]);
    }

    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movies
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => 'archived',
        ]);
    }

    public function updateReviewSummary(int $id, float $averageRating, int $reviewCount): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movies
             SET average_rating = :average_rating,
                 review_count = :review_count,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'average_rating' => round($averageRating, 2),
            'review_count' => $reviewCount,
        ]);
    }

    private function buildFilterParts(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(m.title LIKE :search OR m.slug LIKE :search OR m.director LIKE :search OR c.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'm.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['primary_category_id'])) {
            $conditions[] = 'm.primary_category_id = :primary_category_id';
            $params['primary_category_id'] = (int) $filters['primary_category_id'];
        }

        return [
            'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
            'params' => $params,
        ];
    }
}

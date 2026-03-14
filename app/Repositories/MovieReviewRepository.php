<?php

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Concerns\PaginatesQueries;
use PDO;

class MovieReviewRepository
{
    use PaginatesQueries;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function paginate(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(u.name LIKE :search OR m.title LIKE :search OR r.comment LIKE :search OR r.moderation_note LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['movie_id'])) {
            $conditions[] = 'r.movie_id = :movie_id';
            $params['movie_id'] = (int) $filters['movie_id'];
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }
        if ($filters['is_visible'] !== null) {
            $conditions[] = 'r.is_visible = :is_visible';
            $params['is_visible'] = (int) $filters['is_visible'];
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $selectSql = "
            SELECT
                r.*,
                u.name AS user_name,
                m.title AS movie_title,
                m.slug AS movie_slug
            FROM movie_reviews r
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN movies m ON m.id = r.movie_id
            {$where}
            ORDER BY r.updated_at DESC, r.id DESC
        ";
        $countSql = "
            SELECT COUNT(*)
            FROM movie_reviews r
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN movies m ON m.id = r.movie_id
            {$where}
        ";

        return $this->paginateQuery($this->db, $selectSql, $countSql, $params, $filters['page'], $filters['per_page']);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name AS user_name, m.title AS movie_title, m.slug AS movie_slug
             FROM movie_reviews r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN movies m ON m.id = r.movie_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listApprovedVisibleForMovie(int $movieId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        $stmt = $this->db->prepare("
            SELECT
                r.id,
                r.movie_id,
                r.user_id,
                r.rating,
                r.comment,
                r.created_at,
                u.name AS user_name
            FROM movie_reviews r
            INNER JOIN users u ON u.id = r.user_id
            WHERE r.movie_id = :movie_id
              AND r.status = 'approved'
              AND r.is_visible = 1
            ORDER BY r.updated_at DESC, r.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['movie_id' => $movieId]);

        return $stmt->fetchAll() ?: [];
    }

    public function updateModeration(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE movie_reviews
             SET status = :status,
                 is_visible = :is_visible,
                 moderation_note = :moderation_note,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => $data['status'],
            'is_visible' => $data['is_visible'],
            'moderation_note' => $data['moderation_note'],
        ]);
    }

    public function getApprovedVisibleStatsForMovie(int $movieId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COALESCE(AVG(rating), 0) AS average_rating,
                COUNT(*) AS review_count
             FROM movie_reviews
             WHERE movie_id = :movie_id
               AND status = :status
               AND is_visible = :is_visible'
        );
        $stmt->execute([
            'movie_id' => $movieId,
            'status' => 'approved',
            'is_visible' => 1,
        ]);
        $row = $stmt->fetch();

        return [
            'average_rating' => round((float) ($row['average_rating'] ?? 0), 2),
            'review_count' => (int) ($row['review_count'] ?? 0),
        ];
    }
}

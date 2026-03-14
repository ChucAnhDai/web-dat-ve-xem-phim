<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class MovieCategoryAssignmentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function replaceForMovie(int $movieId, array $categoryIds): void
    {
        $deleteStmt = $this->db->prepare('DELETE FROM movie_category_assignments WHERE movie_id = :movie_id');
        $deleteStmt->execute(['movie_id' => $movieId]);

        if (empty($categoryIds)) {
            return;
        }

        $insertStmt = $this->db->prepare(
            'INSERT INTO movie_category_assignments (movie_id, category_id)
             VALUES (:movie_id, :category_id)'
        );

        foreach ($categoryIds as $categoryId) {
            $insertStmt->execute([
                'movie_id' => $movieId,
                'category_id' => $categoryId,
            ]);
        }
    }

    public function getCategoryIdsByMovieId(int $movieId): array
    {
        $stmt = $this->db->prepare(
            'SELECT category_id
             FROM movie_category_assignments
             WHERE movie_id = :movie_id
             ORDER BY category_id ASC'
        );
        $stmt->execute(['movie_id' => $movieId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

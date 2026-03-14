<?php

namespace App\Repositories\Concerns;

use PDO;

trait PaginatesQueries
{
    protected function paginateQuery(PDO $db, string $selectSql, string $countSql, array $params, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countStmt = $db->prepare($countSql);
        $this->bindValues($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $db->prepare($selectSql . ' LIMIT :limit OFFSET :offset');
        $this->bindValues($dataStmt, $params);
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'items' => $dataStmt->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    protected function bindValues($statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $paramName = str_starts_with((string) $key, ':') ? (string) $key : ':' . $key;
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            }

            $statement->bindValue($paramName, $value, $type);
        }
    }
}

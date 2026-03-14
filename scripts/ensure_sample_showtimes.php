<?php

require __DIR__ . '/../config/autoloader.php';

use App\Core\Database;

$pdo = Database::getInstance();
$pdo->beginTransaction();

try {
    $cinemaCount = (int) $pdo->query('SELECT COUNT(*) FROM cinemas')->fetchColumn();
    if ($cinemaCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO cinemas (name, address) VALUES (:name, :address)');
        foreach ([
            ['name' => 'CinemaX Landmark', 'address' => 'Vinhomes Central Park, Binh Thanh'],
            ['name' => 'CinemaX Nguyen Du', 'address' => '116 Nguyen Du, District 1'],
            ['name' => 'CinemaX Phu Nhuan', 'address' => '214 Phan Xich Long, Phu Nhuan'],
        ] as $cinema) {
            $stmt->execute($cinema);
        }
    }

    $roomCount = (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn();
    if ($roomCount === 0) {
        $cinemas = $pdo->query('SELECT id, name FROM cinemas ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $roomStmt = $pdo->prepare('INSERT INTO rooms (cinema_id, room_name, total_seats) VALUES (:cinema_id, :room_name, :total_seats)');

        $templates = [
            ['room_name' => 'Hall 1 - IMAX', 'total_seats' => 120],
            ['room_name' => 'Hall 3 - Standard', 'total_seats' => 120],
            ['room_name' => 'Hall 5 - 4DX', 'total_seats' => 120],
        ];

        foreach ($cinemas as $index => $cinema) {
            $template = $templates[$index % count($templates)];
            $roomStmt->execute([
                'cinema_id' => (int) $cinema['id'],
                'room_name' => $template['room_name'],
                'total_seats' => $template['total_seats'],
            ]);
        }
    }

    $seatCount = (int) $pdo->query('SELECT COUNT(*) FROM seats')->fetchColumn();
    if ($seatCount === 0) {
        $rooms = $pdo->query('SELECT id FROM rooms ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $seatStmt = $pdo->prepare('
            INSERT INTO seats (room_id, seat_row, seat_number, seat_type)
            VALUES (:room_id, :seat_row, :seat_number, :seat_type)
        ');

        foreach ($rooms as $room) {
            foreach (range('A', 'J') as $row) {
                for ($number = 1; $number <= 12; $number += 1) {
                    $seatType = 'normal';
                    if (in_array($row, ['H', 'I'], true)) {
                        $seatType = 'vip';
                    }
                    if ($row === 'J' && $number >= 9) {
                        $seatType = 'couple';
                    }

                    $seatStmt->execute([
                        'room_id' => (int) $room['id'],
                        'seat_row' => $row,
                        'seat_number' => $number,
                        'seat_type' => $seatType,
                    ]);
                }
            }
        }
    }

    $showtimeCount = (int) $pdo->query('SELECT COUNT(*) FROM showtimes')->fetchColumn();
    if ($showtimeCount >= 0) {
        $preferredSlugs = ['toi-pham-101', 'be-ghe-ma-cung', 'co-dau'];
        $slugPlaceholders = implode(',', array_fill(0, count($preferredSlugs), '?'));
        $preferredStmt = $pdo->prepare("
            SELECT id
            FROM movies
            WHERE slug IN ($slugPlaceholders)
            ORDER BY FIELD(slug, " . implode(',', array_fill(0, count($preferredSlugs), '?')) . ")
        ");
        $preferredStmt->execute(array_merge($preferredSlugs, $preferredSlugs));
        $preferredMovies = array_map(static function (array $row): int {
            return (int) $row['id'];
        }, $preferredStmt->fetchAll(PDO::FETCH_ASSOC));

        $fallbackMovies = array_map(static function (array $row): int {
            return (int) $row['id'];
        }, $pdo->query("
            SELECT id
            FROM movies
            WHERE status = 'now_showing'
            ORDER BY id ASC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC));

        $movieIds = array_values(array_unique(array_merge($preferredMovies, $fallbackMovies)));
        $rooms = $pdo->query('SELECT id FROM rooms ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        $showtimeStmt = $pdo->prepare('
            INSERT INTO showtimes (movie_id, room_id, show_date, start_time, price)
            VALUES (:movie_id, :room_id, :show_date, :start_time, :price)
        ');
        $existingShowtimeStmt = $pdo->prepare('SELECT COUNT(*) FROM showtimes WHERE movie_id = :movie_id');

        $timeTemplates = [
            ['13:30:00', 18.00],
            ['16:45:00', 18.00],
            ['20:00:00', 20.00],
        ];

        foreach ($movieIds as $movieIndex => $movieId) {
            $existingShowtimeStmt->execute(['movie_id' => $movieId]);
            if ((int) $existingShowtimeStmt->fetchColumn() > 0) {
                continue;
            }

            $roomId = (int) $rooms[$movieIndex % count($rooms)]['id'];

            for ($dayOffset = 0; $dayOffset < 6; $dayOffset += 1) {
                $showDate = date('Y-m-d', strtotime(sprintf('+%d day', $dayOffset)));
                foreach ($timeTemplates as $timeIndex => [$startTime, $price]) {
                    if (($movieIndex + $timeIndex + $dayOffset) % 3 === 2) {
                        continue;
                    }

                    $showtimeStmt->execute([
                        'movie_id' => $movieId,
                        'room_id' => $roomId,
                        'show_date' => $showDate,
                        'start_time' => $startTime,
                        'price' => $price,
                    ]);
                }
            }
        }
    }

    $pdo->commit();

    echo "Sample showtime data is ready." . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Failed to seed showtime data: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

<?php

require __DIR__ . '/../config/autoloader.php';

use App\Core\Database;
use App\Services\DemoDatasetMaintenanceService;
use App\Support\Slugger;

$pdo = Database::getInstance();
$pdo->beginTransaction();

try {
    $summary = [
        'categories_created' => 0,
        'movies_created' => 0,
        'movie_assets_created' => 0,
        'cinemas_created' => 0,
        'cinemas_updated' => 0,
        'rooms_created' => 0,
        'rooms_updated' => 0,
        'seats_created' => 0,
        'showtimes_created' => 0,
        'orders_created' => 0,
        'ticket_details_created' => 0,
        'payments_created' => 0,
        'legacy_cinemas_archived' => 0,
        'legacy_rooms_archived' => 0,
        'legacy_seats_archived' => 0,
        'legacy_showtimes_archived' => 0,
    ];

    $cleanupSummary = (new DemoDatasetMaintenanceService($pdo))->cleanupLegacyCinemaFixtures();
    $summary['legacy_cinemas_archived'] = (int) ($cleanupSummary['archived_cinemas'] ?? 0);
    $summary['legacy_rooms_archived'] = (int) ($cleanupSummary['archived_rooms'] ?? 0);
    $summary['legacy_seats_archived'] = (int) ($cleanupSummary['archived_seats'] ?? 0);
    $summary['legacy_showtimes_archived'] = (int) ($cleanupSummary['archived_showtimes'] ?? 0);

    $categoryIds = ensureCategories($pdo, $summary);
    $movieIds = ensureMovies($pdo, $categoryIds, $summary);
    $cinemaIds = ensureCinemas($pdo, $summary);
    $rooms = ensureRooms($pdo, $cinemaIds, $summary);
    ensureRoomSeats($pdo, $rooms, $summary);
    ensureShowtimes($pdo, $movieIds, $rooms, $summary);
    ensureDemoOrders($pdo, $summary);

    $pdo->commit();

    echo "Cinema demo data is ready." . PHP_EOL;
    foreach ($summary as $key => $value) {
        echo '- ' . $key . ': ' . $value . PHP_EOL;
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Failed to seed cinema demo data: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function ensureCategories(PDO $pdo, array &$summary): array
{
    $definitions = [
        ['name' => 'Action', 'slug' => 'action', 'description' => 'Action blockbusters', 'display_order' => 1],
        ['name' => 'Drama', 'slug' => 'drama', 'description' => 'Character-driven dramas', 'display_order' => 2],
        ['name' => 'Horror', 'slug' => 'horror', 'description' => 'Horror and thriller stories', 'display_order' => 3],
        ['name' => 'Animation', 'slug' => 'animation', 'description' => 'Family and animated adventures', 'display_order' => 4],
        ['name' => 'Sci-Fi', 'slug' => 'sci-fi', 'description' => 'Science fiction adventures', 'display_order' => 5],
    ];

    $findStmt = $pdo->prepare('SELECT id FROM movie_categories WHERE slug = :slug LIMIT 1');
    $insertStmt = $pdo->prepare('
        INSERT INTO movie_categories (name, slug, description, display_order, is_active)
        VALUES (:name, :slug, :description, :display_order, 1)
    ');

    $ids = [];

    foreach ($definitions as $definition) {
        $findStmt->execute(['slug' => $definition['slug']]);
        $existingId = $findStmt->fetchColumn();
        if ($existingId !== false) {
            $ids[$definition['slug']] = (int) $existingId;
            continue;
        }

        $insertStmt->execute($definition);
        $ids[$definition['slug']] = (int) $pdo->lastInsertId();
        $summary['categories_created'] += 1;
    }

    return $ids;
}

function ensureMovies(PDO $pdo, array $categoryIds, array &$summary): array
{
    $definitions = [
        [
            'slug' => 'midnight-pursuit',
            'title' => 'Midnight Pursuit',
            'summary' => 'An ex-detective chases a vanished witness through the sleepless heart of Saigon.',
            'duration_minutes' => 118,
            'release_date' => '2026-03-01',
            'poster_url' => 'https://placehold.co/600x900/png?text=Midnight+Pursuit',
            'banner_url' => 'https://placehold.co/1280x720/png?text=Midnight+Pursuit',
            'gallery_url' => 'https://placehold.co/960x540/png?text=Midnight+Pursuit+Gallery',
            'trailer_url' => 'https://www.youtube.com/watch?v=midnight-pursuit-demo',
            'age_rating' => 'T18',
            'language' => 'Vietnamese',
            'director' => 'Le Minh',
            'writer' => 'Tran Bao',
            'cast_text' => 'Bao Anh, Minh Khang, Nhat Linh',
            'studio' => 'CinemaX Originals',
            'average_rating' => 4.60,
            'review_count' => 128,
            'status' => 'now_showing',
            'categories' => ['action', 'drama'],
        ],
        [
            'slug' => 'silent-harbor',
            'title' => 'Silent Harbor',
            'summary' => 'A shipping clerk discovers a conspiracy hidden inside routine cargo manifests.',
            'duration_minutes' => 104,
            'release_date' => '2026-02-21',
            'poster_url' => 'https://placehold.co/600x900/png?text=Silent+Harbor',
            'banner_url' => 'https://placehold.co/1280x720/png?text=Silent+Harbor',
            'gallery_url' => 'https://placehold.co/960x540/png?text=Silent+Harbor+Gallery',
            'trailer_url' => 'https://www.youtube.com/watch?v=silent-harbor-demo',
            'age_rating' => 'T16',
            'language' => 'English',
            'director' => 'Ana Rivera',
            'writer' => 'Khanh Vu',
            'cast_text' => 'Mina Park, Isaac Reed, Jae Kim',
            'studio' => 'Blue Dock Pictures',
            'average_rating' => 4.35,
            'review_count' => 82,
            'status' => 'now_showing',
            'categories' => ['drama', 'action'],
        ],
        [
            'slug' => 'bride-of-the-mist',
            'title' => 'Bride of the Mist',
            'summary' => 'A mountain wedding party wakes an old curse that only dawn can silence.',
            'duration_minutes' => 97,
            'release_date' => '2026-02-14',
            'poster_url' => 'https://placehold.co/600x900/png?text=Bride+of+the+Mist',
            'banner_url' => 'https://placehold.co/1280x720/png?text=Bride+of+the+Mist',
            'gallery_url' => 'https://placehold.co/960x540/png?text=Bride+of+the+Mist+Gallery',
            'trailer_url' => 'https://www.youtube.com/watch?v=bride-of-the-mist-demo',
            'age_rating' => 'T18',
            'language' => 'Vietnamese',
            'director' => 'Do Huyen',
            'writer' => 'Ngoc Mai',
            'cast_text' => 'Thao Nhi, Quoc Truong, Hoang Son',
            'studio' => 'Red Fog Studio',
            'average_rating' => 4.10,
            'review_count' => 64,
            'status' => 'now_showing',
            'categories' => ['horror', 'drama'],
        ],
        [
            'slug' => 'little-comet',
            'title' => 'Little Comet',
            'summary' => 'A fearless courier and a runaway comet save a city-wide festival under the stars.',
            'duration_minutes' => 89,
            'release_date' => '2026-01-29',
            'poster_url' => 'https://placehold.co/600x900/png?text=Little+Comet',
            'banner_url' => 'https://placehold.co/1280x720/png?text=Little+Comet',
            'gallery_url' => 'https://placehold.co/960x540/png?text=Little+Comet+Gallery',
            'trailer_url' => 'https://www.youtube.com/watch?v=little-comet-demo',
            'age_rating' => 'P',
            'language' => 'Vietnamese',
            'director' => 'Pham Linh',
            'writer' => 'Huy Hoang',
            'cast_text' => 'Gia Han, Bao Nam, Kim Anh',
            'studio' => 'Skyline Animation',
            'average_rating' => 4.75,
            'review_count' => 211,
            'status' => 'now_showing',
            'categories' => ['animation', 'sci-fi'],
        ],
        [
            'slug' => 'galaxy-classroom',
            'title' => 'Galaxy Classroom',
            'summary' => 'A new teacher turns a quiet school into the launch point for a science-fiction rescue.',
            'duration_minutes' => 112,
            'release_date' => '2026-04-05',
            'poster_url' => 'https://placehold.co/600x900/png?text=Galaxy+Classroom',
            'banner_url' => 'https://placehold.co/1280x720/png?text=Galaxy+Classroom',
            'gallery_url' => 'https://placehold.co/960x540/png?text=Galaxy+Classroom+Gallery',
            'trailer_url' => 'https://www.youtube.com/watch?v=galaxy-classroom-demo',
            'age_rating' => 'K',
            'language' => 'English',
            'director' => 'Noah Tran',
            'writer' => 'Ha An',
            'cast_text' => 'Luna Vo, Minh Quang, Ellie Harper',
            'studio' => 'North Orbit',
            'average_rating' => 4.25,
            'review_count' => 41,
            'status' => 'coming_soon',
            'categories' => ['sci-fi', 'animation'],
        ],
    ];

    $findStmt = $pdo->prepare('SELECT id FROM movies WHERE slug = :slug LIMIT 1');
    $insertStmt = $pdo->prepare('
        INSERT INTO movies (
            primary_category_id, slug, title, summary, duration_minutes, release_date, poster_url, trailer_url,
            age_rating, language, director, writer, cast_text, studio, average_rating, review_count, status
        ) VALUES (
            :primary_category_id, :slug, :title, :summary, :duration_minutes, :release_date, :poster_url, :trailer_url,
            :age_rating, :language, :director, :writer, :cast_text, :studio, :average_rating, :review_count, :status
        )
    ');
    $updateStmt = $pdo->prepare('
        UPDATE movies
        SET
            primary_category_id = :primary_category_id,
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
            review_count = :review_count,
            status = :status
        WHERE id = :id
    ');
    $assignmentFindStmt = $pdo->prepare('
        SELECT 1 FROM movie_category_assignments WHERE movie_id = :movie_id AND category_id = :category_id LIMIT 1
    ');
    $assignmentInsertStmt = $pdo->prepare('
        INSERT INTO movie_category_assignments (movie_id, category_id) VALUES (:movie_id, :category_id)
    ');
    $imageFindStmt = $pdo->prepare('
        SELECT id FROM movie_images WHERE movie_id = :movie_id AND asset_type = :asset_type AND image_url = :image_url LIMIT 1
    ');
    $imageInsertStmt = $pdo->prepare('
        INSERT INTO movie_images (movie_id, asset_type, image_url, alt_text, sort_order, is_primary, status)
        VALUES (:movie_id, :asset_type, :image_url, :alt_text, :sort_order, :is_primary, :status)
    ');

    $movieIds = [];

    foreach ($definitions as $definition) {
        $findStmt->execute(['slug' => $definition['slug']]);
        $movieId = $findStmt->fetchColumn();

        if ($movieId === false) {
            $primaryCategorySlug = $definition['categories'][0];
            $insertStmt->execute([
                'primary_category_id' => $categoryIds[$primaryCategorySlug] ?? null,
                'slug' => $definition['slug'],
                'title' => $definition['title'],
                'summary' => $definition['summary'],
                'duration_minutes' => $definition['duration_minutes'],
                'release_date' => $definition['release_date'],
                'poster_url' => $definition['poster_url'],
                'trailer_url' => $definition['trailer_url'],
                'age_rating' => $definition['age_rating'],
                'language' => $definition['language'],
                'director' => $definition['director'],
                'writer' => $definition['writer'],
                'cast_text' => $definition['cast_text'],
                'studio' => $definition['studio'],
                'average_rating' => $definition['average_rating'],
                'review_count' => $definition['review_count'],
                'status' => $definition['status'],
            ]);
            $movieId = (int) $pdo->lastInsertId();
            $summary['movies_created'] += 1;
        } else {
            $primaryCategorySlug = $definition['categories'][0];
            $updateStmt->execute([
                'id' => (int) $movieId,
                'primary_category_id' => $categoryIds[$primaryCategorySlug] ?? null,
                'title' => $definition['title'],
                'summary' => $definition['summary'],
                'duration_minutes' => $definition['duration_minutes'],
                'release_date' => $definition['release_date'],
                'poster_url' => $definition['poster_url'],
                'trailer_url' => $definition['trailer_url'],
                'age_rating' => $definition['age_rating'],
                'language' => $definition['language'],
                'director' => $definition['director'],
                'writer' => $definition['writer'],
                'cast_text' => $definition['cast_text'],
                'studio' => $definition['studio'],
                'average_rating' => $definition['average_rating'],
                'review_count' => $definition['review_count'],
                'status' => $definition['status'],
            ]);
            $movieId = (int) $movieId;
        }

        $movieIds[$definition['slug']] = $movieId;

        foreach ($definition['categories'] as $categorySlug) {
            $categoryId = $categoryIds[$categorySlug] ?? null;
            if ($categoryId === null) {
                continue;
            }

            $assignmentFindStmt->execute([
                'movie_id' => $movieId,
                'category_id' => $categoryId,
            ]);
            if ($assignmentFindStmt->fetchColumn() !== false) {
                continue;
            }

            $assignmentInsertStmt->execute([
                'movie_id' => $movieId,
                'category_id' => $categoryId,
            ]);
        }

        $assets = [
            [
                'asset_type' => 'poster',
                'image_url' => $definition['poster_url'],
                'alt_text' => $definition['title'] . ' Poster',
                'sort_order' => 1,
                'is_primary' => 1,
            ],
            [
                'asset_type' => 'banner',
                'image_url' => $definition['banner_url'],
                'alt_text' => $definition['title'] . ' Banner',
                'sort_order' => 2,
                'is_primary' => 0,
            ],
            [
                'asset_type' => 'gallery',
                'image_url' => $definition['gallery_url'],
                'alt_text' => $definition['title'] . ' Gallery',
                'sort_order' => 3,
                'is_primary' => 0,
            ],
        ];

        foreach ($assets as $asset) {
            $imageFindStmt->execute([
                'movie_id' => $movieId,
                'asset_type' => $asset['asset_type'],
                'image_url' => $asset['image_url'],
            ]);
            if ($imageFindStmt->fetchColumn() !== false) {
                continue;
            }

            $imageInsertStmt->execute([
                'movie_id' => $movieId,
                'asset_type' => $asset['asset_type'],
                'image_url' => $asset['image_url'],
                'alt_text' => $asset['alt_text'],
                'sort_order' => $asset['sort_order'],
                'is_primary' => $asset['is_primary'],
                'status' => 'active',
            ]);
            $summary['movie_assets_created'] += 1;
        }
    }

    return $movieIds;
}

function ensureCinemas(PDO $pdo, array &$summary): array
{
    $definitions = [
        [
            'slug' => Slugger::slugify('CinemaX Landmark 81'),
            'name' => 'CinemaX Landmark 81',
            'city' => 'Ho Chi Minh City',
            'address' => '720A Dien Bien Phu, Binh Thanh',
            'manager_name' => 'Tran Minh Duc',
            'support_phone' => '0900000101',
            'status' => 'active',
            'opening_time' => '09:00:00',
            'closing_time' => '23:30:00',
            'latitude' => '10.7953060',
            'longitude' => '106.7212970',
            'description' => 'Flagship multiplex with premium screens and IMAX hall.',
        ],
        [
            'slug' => Slugger::slugify('CinemaX Nguyen Hue'),
            'name' => 'CinemaX Nguyen Hue',
            'city' => 'Ho Chi Minh City',
            'address' => '45 Nguyen Hue, District 1',
            'manager_name' => 'Le Thi Hoa',
            'support_phone' => '0900000102',
            'status' => 'active',
            'opening_time' => '08:30:00',
            'closing_time' => '23:45:00',
            'latitude' => '10.7731700',
            'longitude' => '106.7041700',
            'description' => 'Downtown cinema with strong after-work traffic and family screenings.',
        ],
        [
            'slug' => Slugger::slugify('CinemaX Riverside'),
            'name' => 'CinemaX Riverside',
            'city' => 'Thu Duc City',
            'address' => '88 Vo Nguyen Giap, Thu Duc',
            'manager_name' => 'Nguyen Quoc Anh',
            'support_phone' => '0900000103',
            'status' => 'active',
            'opening_time' => '09:30:00',
            'closing_time' => '23:15:00',
            'latitude' => '10.8262210',
            'longitude' => '106.7596350',
            'description' => 'High-volume suburban site tuned for family and weekend traffic.',
        ],
        [
            'slug' => Slugger::slugify('CinemaX East Saigon'),
            'name' => 'CinemaX East Saigon',
            'city' => 'Thu Duc City',
            'address' => '11 Dang Van Bi, Thu Duc',
            'manager_name' => 'Pham Thi Yen',
            'support_phone' => '0900000104',
            'status' => 'renovation',
            'opening_time' => '10:00:00',
            'closing_time' => '22:30:00',
            'latitude' => '10.8491540',
            'longitude' => '106.7604830',
            'description' => 'Site under renovation, used to demonstrate status transitions.',
        ],
    ];

    $findStmt = $pdo->prepare('SELECT id FROM cinemas WHERE slug = :slug LIMIT 1');
    $insertStmt = $pdo->prepare('
        INSERT INTO cinemas (
            slug, name, city, address, manager_name, support_phone, status, opening_time, closing_time, latitude, longitude, description
        ) VALUES (
            :slug, :name, :city, :address, :manager_name, :support_phone, :status, :opening_time, :closing_time, :latitude, :longitude, :description
        )
    ');
    $updateStmt = $pdo->prepare('
        UPDATE cinemas
        SET
            name = :name,
            city = :city,
            address = :address,
            manager_name = :manager_name,
            support_phone = :support_phone,
            status = :status,
            opening_time = :opening_time,
            closing_time = :closing_time,
            latitude = :latitude,
            longitude = :longitude,
            description = :description
        WHERE id = :id
    ');

    $ids = [];

    foreach ($definitions as $definition) {
        $findStmt->execute(['slug' => $definition['slug']]);
        $cinemaId = $findStmt->fetchColumn();

        if ($cinemaId === false) {
            $insertStmt->execute($definition);
            $cinemaId = (int) $pdo->lastInsertId();
            $summary['cinemas_created'] += 1;
        } else {
            $updateStmt->execute([
                'id' => (int) $cinemaId,
                'name' => $definition['name'],
                'city' => $definition['city'],
                'address' => $definition['address'],
                'manager_name' => $definition['manager_name'],
                'support_phone' => $definition['support_phone'],
                'status' => $definition['status'],
                'opening_time' => $definition['opening_time'],
                'closing_time' => $definition['closing_time'],
                'latitude' => $definition['latitude'],
                'longitude' => $definition['longitude'],
                'description' => $definition['description'],
            ]);
            $summary['cinemas_updated'] += 1;
            $cinemaId = (int) $cinemaId;
        }

        $ids[$definition['slug']] = $cinemaId;
    }

    return $ids;
}

function ensureRooms(PDO $pdo, array $cinemaIds, array &$summary): array
{
    $definitions = [
        [
            'cinema_slug' => Slugger::slugify('CinemaX Landmark 81'),
            'room_name' => 'Hall 1 - IMAX',
            'room_type' => 'imax',
            'screen_label' => 'Screen 1',
            'projection_type' => 'imax_dual',
            'sound_profile' => 'dolby_atmos',
            'cleaning_buffer_minutes' => 20,
            'status' => 'active',
        ],
        [
            'cinema_slug' => Slugger::slugify('CinemaX Landmark 81'),
            'room_name' => 'Hall 2 - VIP',
            'room_type' => 'vip_recliner',
            'screen_label' => 'Screen 2',
            'projection_type' => 'laser',
            'sound_profile' => 'dolby_atmos',
            'cleaning_buffer_minutes' => 20,
            'status' => 'active',
        ],
        [
            'cinema_slug' => Slugger::slugify('CinemaX Nguyen Hue'),
            'room_name' => 'Hall 3 - Premium 3D',
            'room_type' => 'premium_3d',
            'screen_label' => 'Screen 3',
            'projection_type' => 'laser',
            'sound_profile' => 'dolby_7_1',
            'cleaning_buffer_minutes' => 15,
            'status' => 'active',
        ],
        [
            'cinema_slug' => Slugger::slugify('CinemaX Nguyen Hue'),
            'room_name' => 'Hall 4 - Standard',
            'room_type' => 'standard_2d',
            'screen_label' => 'Screen 4',
            'projection_type' => 'digital_4k',
            'sound_profile' => 'stereo',
            'cleaning_buffer_minutes' => 15,
            'status' => 'active',
        ],
        [
            'cinema_slug' => Slugger::slugify('CinemaX Riverside'),
            'room_name' => 'Hall 5 - Family',
            'room_type' => 'standard_2d',
            'screen_label' => 'Screen 5',
            'projection_type' => 'digital_4k',
            'sound_profile' => 'dolby_7_1',
            'cleaning_buffer_minutes' => 15,
            'status' => 'active',
        ],
        [
            'cinema_slug' => Slugger::slugify('CinemaX East Saigon'),
            'room_name' => 'Hall 6 - 4DX',
            'room_type' => '4dx',
            'screen_label' => 'Screen 6',
            'projection_type' => 'motion_rig',
            'sound_profile' => 'immersive_360',
            'cleaning_buffer_minutes' => 25,
            'status' => 'closed',
        ],
    ];

    $findStmt = $pdo->prepare('SELECT id FROM rooms WHERE cinema_id = :cinema_id AND room_name = :room_name LIMIT 1');
    $insertStmt = $pdo->prepare('
        INSERT INTO rooms (
            cinema_id, room_name, room_type, screen_label, projection_type, sound_profile,
            cleaning_buffer_minutes, total_seats, status
        ) VALUES (
            :cinema_id, :room_name, :room_type, :screen_label, :projection_type, :sound_profile,
            :cleaning_buffer_minutes, 0, :status
        )
    ');
    $updateStmt = $pdo->prepare('
        UPDATE rooms
        SET
            room_type = :room_type,
            screen_label = :screen_label,
            projection_type = :projection_type,
            sound_profile = :sound_profile,
            cleaning_buffer_minutes = :cleaning_buffer_minutes,
            status = :status
        WHERE id = :id
    ');

    $rooms = [];

    foreach ($definitions as $definition) {
        $cinemaId = $cinemaIds[$definition['cinema_slug']] ?? null;
        if ($cinemaId === null) {
            continue;
        }

        $findStmt->execute([
            'cinema_id' => $cinemaId,
            'room_name' => $definition['room_name'],
        ]);
        $roomId = $findStmt->fetchColumn();

        if ($roomId === false) {
            $insertStmt->execute([
                'cinema_id' => $cinemaId,
                'room_name' => $definition['room_name'],
                'room_type' => $definition['room_type'],
                'screen_label' => $definition['screen_label'],
                'projection_type' => $definition['projection_type'],
                'sound_profile' => $definition['sound_profile'],
                'cleaning_buffer_minutes' => $definition['cleaning_buffer_minutes'],
                'status' => $definition['status'],
            ]);
            $roomId = (int) $pdo->lastInsertId();
            $summary['rooms_created'] += 1;
        } else {
            $updateStmt->execute([
                'id' => (int) $roomId,
                'room_type' => $definition['room_type'],
                'screen_label' => $definition['screen_label'],
                'projection_type' => $definition['projection_type'],
                'sound_profile' => $definition['sound_profile'],
                'cleaning_buffer_minutes' => $definition['cleaning_buffer_minutes'],
                'status' => $definition['status'],
            ]);
            $summary['rooms_updated'] += 1;
            $roomId = (int) $roomId;
        }

        $roomKey = $definition['cinema_slug'] . '::' . $definition['room_name'];
        $rooms[$roomKey] = [
            'id' => $roomId,
            'cinema_slug' => $definition['cinema_slug'],
            'room_name' => $definition['room_name'],
            'room_type' => $definition['room_type'],
            'cleaning_buffer_minutes' => $definition['cleaning_buffer_minutes'],
            'status' => $definition['status'],
        ];
    }

    return $rooms;
}

function ensureRoomSeats(PDO $pdo, array $rooms, array &$summary): void
{
    $seatCountStmt = $pdo->prepare('SELECT COUNT(*) FROM seats WHERE room_id = :room_id');
    $seatInsertStmt = $pdo->prepare('
        INSERT INTO seats (room_id, seat_row, seat_number, seat_type, status)
        VALUES (:room_id, :seat_row, :seat_number, :seat_type, :status)
    ');
    $updateRoomSeatsStmt = $pdo->prepare("
        UPDATE rooms
        SET total_seats = (
            SELECT COUNT(*)
            FROM seats
            WHERE seats.room_id = rooms.id
              AND seats.status <> 'archived'
        )
        WHERE id = :room_id
    ");

    foreach ($rooms as $room) {
        $seatCountStmt->execute(['room_id' => $room['id']]);
        $existingSeatCount = (int) $seatCountStmt->fetchColumn();

        if ($existingSeatCount === 0) {
            foreach (buildSeatLayout($room['room_type']) as $seat) {
                $seatInsertStmt->execute([
                    'room_id' => $room['id'],
                    'seat_row' => $seat['seat_row'],
                    'seat_number' => $seat['seat_number'],
                    'seat_type' => $seat['seat_type'],
                    'status' => $seat['status'],
                ]);
                $summary['seats_created'] += 1;
            }
        }

        $updateRoomSeatsStmt->execute(['room_id' => $room['id']]);
    }
}

function ensureShowtimes(PDO $pdo, array $movieIds, array $rooms, array &$summary): void
{
    $movieStmt = $pdo->prepare('SELECT duration_minutes FROM movies WHERE id = :id LIMIT 1');
    $findStmt = $pdo->prepare('
        SELECT id
        FROM showtimes
        WHERE movie_id = :movie_id
          AND room_id = :room_id
          AND show_date = :show_date
          AND start_time = :start_time
        LIMIT 1
    ');
    $insertStmt = $pdo->prepare('
        INSERT INTO showtimes (
            movie_id, room_id, show_date, start_time, end_time, price, status, presentation_type, language_version
        ) VALUES (
            :movie_id, :room_id, :show_date, :start_time, :end_time, :price, :status, :presentation_type, :language_version
        )
    ');
    $updateStmt = $pdo->prepare('
        UPDATE showtimes
        SET
            end_time = :end_time,
            price = :price,
            status = :status,
            presentation_type = :presentation_type,
            language_version = :language_version
        WHERE id = :id
    ');

    $schedule = [
        [
            'movie_slug' => 'midnight-pursuit',
            'room_key' => Slugger::slugify('CinemaX Landmark 81') . '::Hall 1 - IMAX',
            'day_offsets' => [0, 1, 2, 3, 4],
            'times' => [
                ['10:00:00', 145000.00, 'imax', 'subtitled', 'published'],
                ['15:00:00', 155000.00, 'imax', 'original', 'published'],
                ['20:00:00', 165000.00, 'imax', 'original', 'published'],
            ],
        ],
        [
            'movie_slug' => 'silent-harbor',
            'room_key' => Slugger::slugify('CinemaX Nguyen Hue') . '::Hall 3 - Premium 3D',
            'day_offsets' => [0, 1, 2, 3, 4],
            'times' => [
                ['11:00:00', 120000.00, '3d', 'dubbed', 'published'],
                ['17:30:00', 125000.00, '3d', 'subtitled', 'published'],
            ],
        ],
        [
            'movie_slug' => 'bride-of-the-mist',
            'room_key' => Slugger::slugify('CinemaX Nguyen Hue') . '::Hall 4 - Standard',
            'day_offsets' => [0, 1, 2, 3, 4],
            'times' => [
                ['18:45:00', 105000.00, '2d', 'subtitled', 'published'],
                ['21:15:00', 105000.00, '2d', 'original', 'published'],
            ],
        ],
        [
            'movie_slug' => 'little-comet',
            'room_key' => Slugger::slugify('CinemaX Riverside') . '::Hall 5 - Family',
            'day_offsets' => [0, 1, 2, 3, 4],
            'times' => [
                ['09:30:00', 85000.00, '2d', 'dubbed', 'published'],
                ['14:15:00', 90000.00, '2d', 'dubbed', 'published'],
            ],
        ],
        [
            'movie_slug' => 'galaxy-classroom',
            'room_key' => Slugger::slugify('CinemaX Landmark 81') . '::Hall 2 - VIP',
            'day_offsets' => [5, 6, 7],
            'times' => [
                ['16:00:00', 140000.00, '2d', 'dubbed', 'draft'],
            ],
        ],
    ];

    foreach ($schedule as $item) {
        $movieId = $movieIds[$item['movie_slug']] ?? null;
        $room = $rooms[$item['room_key']] ?? null;

        if ($movieId === null || $room === null) {
            continue;
        }

        $movieStmt->execute(['id' => $movieId]);
        $durationMinutes = (int) $movieStmt->fetchColumn();
        if ($durationMinutes <= 0) {
            $durationMinutes = 110;
        }

        foreach ($item['day_offsets'] as $dayOffset) {
            $showDate = date('Y-m-d', strtotime('+' . $dayOffset . ' day'));

            foreach ($item['times'] as [$startTime, $price, $presentationType, $languageVersion, $status]) {
                $endTime = date(
                    'H:i:s',
                    strtotime('1970-01-01 ' . $startTime . ' +' . ($durationMinutes + (int) $room['cleaning_buffer_minutes']) . ' minutes')
                );

                $findStmt->execute([
                    'movie_id' => $movieId,
                    'room_id' => $room['id'],
                    'show_date' => $showDate,
                    'start_time' => $startTime,
                ]);
                $existingShowtimeId = $findStmt->fetchColumn();
                if ($existingShowtimeId !== false) {
                    $updateStmt->execute([
                        'id' => (int) $existingShowtimeId,
                        'end_time' => $endTime,
                        'price' => $price,
                        'status' => $status,
                        'presentation_type' => $presentationType,
                        'language_version' => $languageVersion,
                    ]);
                    continue;
                }

                $insertStmt->execute([
                    'movie_id' => $movieId,
                    'room_id' => $room['id'],
                    'show_date' => $showDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'price' => $price,
                    'status' => $status,
                    'presentation_type' => $presentationType,
                    'language_version' => $languageVersion,
                ]);
                $summary['showtimes_created'] += 1;
            }
        }
    }
}

function ensureDemoOrders(PDO $pdo, array &$summary): void
{
    $contactProfiles = [
        ['name' => 'Nguyen Van An', 'email' => 'ticket.an@example.com', 'phone' => '0901000001', 'fulfillment' => 'e_ticket', 'payment_method' => 'momo'],
        ['name' => 'Tran Minh Chau', 'email' => 'ticket.chau@example.com', 'phone' => '0901000002', 'fulfillment' => 'counter_pickup', 'payment_method' => 'cash'],
        ['name' => 'Le Gia Han', 'email' => 'ticket.han@example.com', 'phone' => '0901000003', 'fulfillment' => 'e_ticket', 'payment_method' => 'vnpay'],
    ];
    $showtimeStmt = $pdo->query("
        SELECT s.id, s.room_id, s.price
        FROM showtimes s
        INNER JOIN rooms r ON r.id = s.room_id
        INNER JOIN cinemas c ON c.id = r.cinema_id
        WHERE s.status = 'published'
          AND s.show_date >= CURRENT_DATE
          AND r.status = 'active'
          AND c.status = 'active'
        ORDER BY s.show_date ASC, s.start_time ASC, s.id ASC
        LIMIT 3
    ");
    $showtimes = $showtimeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($showtimes === []) {
        return;
    }

    $existingTicketCountStmt = $pdo->prepare('SELECT COUNT(*) FROM ticket_details WHERE showtime_id = :showtime_id');
    $seatStmt = $pdo->prepare("
        SELECT id, seat_type
        FROM seats
        WHERE room_id = :room_id
          AND status = 'available'
        ORDER BY seat_row ASC, seat_number ASC, id ASC
        LIMIT 4
    ");
    $orderInsertStmt = $pdo->prepare('
        INSERT INTO ticket_orders (
            order_code,
            user_id,
            contact_name,
            contact_email,
            contact_phone,
            fulfillment_method,
            seat_count,
            subtotal_price,
            discount_amount,
            fee_amount,
            total_price,
            currency,
            status,
            hold_expires_at,
            paid_at
        )
        VALUES (
            :order_code,
            NULL,
            :contact_name,
            :contact_email,
            :contact_phone,
            :fulfillment_method,
            :seat_count,
            :subtotal_price,
            0.00,
            0.00,
            :total_price,
            :currency,
            :status,
            :hold_expires_at,
            :paid_at
        )
    ');
    $detailInsertStmt = $pdo->prepare('
        INSERT INTO ticket_details (
            order_id,
            showtime_id,
            seat_id,
            ticket_code,
            status,
            base_price,
            surcharge_amount,
            discount_amount,
            price,
            qr_payload
        )
        VALUES (
            :order_id,
            :showtime_id,
            :seat_id,
            :ticket_code,
            :status,
            :base_price,
            :surcharge_amount,
            0.00,
            :price,
            :qr_payload
        )
    ');
    $paymentInsertStmt = $pdo->prepare('
        INSERT INTO payments (ticket_order_id, payment_method, payment_status, transaction_code)
        VALUES (:ticket_order_id, :payment_method, :payment_status, :transaction_code)
    ');

    foreach ($showtimes as $index => $showtime) {
        $showtimeId = (int) $showtime['id'];
        $existingTicketCountStmt->execute(['showtime_id' => $showtimeId]);
        if ((int) $existingTicketCountStmt->fetchColumn() > 0) {
            continue;
        }

        $seatStmt->execute(['room_id' => (int) $showtime['room_id']]);
        $seatRows = $seatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($seatRows === []) {
            continue;
        }

        $orderStatus = $index % 2 === 0 ? 'paid' : 'pending';
        $contact = $contactProfiles[$index % count($contactProfiles)];
        $seatPrice = (float) $showtime['price'];
        $seatCount = count($seatRows);
        $subtotalPrice = $seatPrice * $seatCount;
        $surchargeTotal = 0.0;

        foreach ($seatRows as $seatRow) {
            $surchargeTotal += ticketSeatSurcharge((string) ($seatRow['seat_type'] ?? 'normal'));
        }

        $holdExpiresAt = $orderStatus === 'pending'
            ? date('Y-m-d H:i:s', strtotime('+15 minutes'))
            : null;
        $paidAt = $orderStatus === 'paid'
            ? date('Y-m-d H:i:s')
            : null;

        $orderInsertStmt->execute([
            'order_code' => sprintf('TKT-DEMO-%06d', $showtimeId),
            'contact_name' => $contact['name'],
            'contact_email' => $contact['email'],
            'contact_phone' => $contact['phone'],
            'fulfillment_method' => $contact['fulfillment'],
            'seat_count' => $seatCount,
            'subtotal_price' => $subtotalPrice,
            'total_price' => $subtotalPrice + $surchargeTotal,
            'currency' => 'VND',
            'status' => $orderStatus,
            'hold_expires_at' => $holdExpiresAt,
            'paid_at' => $paidAt,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $summary['orders_created'] += 1;

        foreach ($seatRows as $seatRow) {
            $seatId = (int) $seatRow['id'];
            $surchargeAmount = ticketSeatSurcharge((string) ($seatRow['seat_type'] ?? 'normal'));
            $detailInsertStmt->execute([
                'order_id' => $orderId,
                'showtime_id' => $showtimeId,
                'seat_id' => $seatId,
                'ticket_code' => sprintf('TIC-DEMO-%06d-%06d', $showtimeId, $seatId),
                'status' => $orderStatus,
                'base_price' => $seatPrice,
                'surcharge_amount' => $surchargeAmount,
                'price' => $seatPrice + $surchargeAmount,
                'qr_payload' => sprintf('ticket:TIC-DEMO-%06d-%06d', $showtimeId, $seatId),
            ]);
            $summary['ticket_details_created'] += 1;
        }

        $paymentInsertStmt->execute([
            'ticket_order_id' => $orderId,
            'payment_method' => $contact['payment_method'],
            'payment_status' => $orderStatus === 'paid' ? 'success' : 'pending',
            'transaction_code' => sprintf('PAY-DEMO-%06d', $orderId),
        ]);
        $summary['payments_created'] += 1;
    }
}

function ticketSeatSurcharge(string $seatType): float
{
    $normalizedSeatType = strtolower(trim($seatType));

    if ($normalizedSeatType === 'vip') {
        return 15000.0;
    }
    if ($normalizedSeatType === 'couple') {
        return 30000.0;
    }

    return 0.0;
}

function buildSeatLayout(string $roomType): array
{
    $normalizedRoomType = strtolower(trim($roomType));

    if ($normalizedRoomType === 'imax') {
        return buildRowsLayout(range('A', 'J'), 14);
    }
    if ($normalizedRoomType === 'vip_recliner') {
        return buildRowsLayout(range('A', 'F'), 8, true);
    }

    return buildRowsLayout(range('A', 'H'), 12);
}

function buildRowsLayout(array $rows, int $seatsPerRow, bool $allVip = false): array
{
    $layout = [];
    $lastRow = $rows[count($rows) - 1];
    $vipRows = array_slice($rows, -2);

    foreach ($rows as $row) {
        for ($seatNumber = 1; $seatNumber <= $seatsPerRow; $seatNumber += 1) {
            $seatType = 'normal';
            $status = 'available';

            if ($allVip || in_array($row, $vipRows, true)) {
                $seatType = 'vip';
            }

            if ($row === $lastRow && $seatNumber > max(2, $seatsPerRow - 2)) {
                $seatType = 'couple';
            }

            if ($row === $rows[0] && $seatNumber <= 2) {
                $status = 'maintenance';
            }
            if ($row === $lastRow && $seatNumber <= 2) {
                $status = 'disabled';
            }

            $layout[] = [
                'seat_row' => $row,
                'seat_number' => $seatNumber,
                'seat_type' => $seatType,
                'status' => $status,
            ];
        }
    }

    return $layout;
}

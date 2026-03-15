# Cinema Operations v1

## Scope

Cinema Operations v1 makes `database/movie_shop.sql` the source of truth for:

- `cinemas`
- `rooms`
- `seats`
- `showtimes`

The admin UI now writes to live APIs instead of mock-only panels, and the public UI reads persisted showtime and seat availability data.

## Core Schema

### cinemas

- `id`
- `name`
- `slug`
- `city`
- `address`
- `manager_name`
- `support_phone`
- `status`
- `opening_time`
- `closing_time`
- `latitude`
- `longitude`
- `description`
- `created_at`
- `updated_at`

Allowed `cinemas.status` values:

- `active`
- `renovation`
- `closed`
- `archived`

### rooms

- `id`
- `cinema_id`
- `room_name`
- `room_type`
- `screen_label`
- `projection_type`
- `sound_profile`
- `cleaning_buffer_minutes`
- `total_seats`
- `status`
- `created_at`
- `updated_at`

Allowed `rooms.status` values:

- `active`
- `maintenance`
- `closed`
- `archived`

### seats

- `id`
- `room_id`
- `seat_row`
- `seat_number`
- `seat_type`
- `status`
- `created_at`
- `updated_at`

Allowed `seats.status` values:

- `available`
- `maintenance`
- `disabled`
- `archived`

Constraint:

- unique key on `(room_id, seat_row, seat_number)`

### showtimes

- `id`
- `movie_id`
- `room_id`
- `show_date`
- `start_time`
- `end_time`
- `price`
- `status`
- `presentation_type`
- `language_version`
- `created_at`
- `updated_at`

Allowed `showtimes.status` values:

- `draft`
- `published`
- `cancelled`
- `completed`
- `archived`

## Route Summary

### Public

- `GET /api/showtimes`
- `GET /api/showtimes/{id}/seat-map`
- `GET /api/movies/{slug}`

### Admin

- `GET /api/admin/cinemas`
- `POST /api/admin/cinemas`
- `GET /api/admin/cinemas/{id}`
- `PUT /api/admin/cinemas/{id}`
- `DELETE /api/admin/cinemas/{id}`
- `GET /api/admin/rooms`
- `POST /api/admin/rooms`
- `GET /api/admin/rooms/{id}`
- `PUT /api/admin/rooms/{id}`
- `DELETE /api/admin/rooms/{id}`
- `GET /api/admin/rooms/{id}/seats`
- `PUT /api/admin/rooms/{id}/seats`
- `GET /api/admin/showtimes`
- `POST /api/admin/showtimes`
- `GET /api/admin/showtimes/{id}`
- `PUT /api/admin/showtimes/{id}`
- `DELETE /api/admin/showtimes/{id}`

## Lifecycle Rules

- Delete actions for cinemas, rooms, and showtimes are soft archives by setting `status = 'archived'`.
- Cinemas cannot be moved to `closed` or `archived` while active rooms or future published showtimes still exist.
- Rooms cannot be moved to `closed` or `archived` while future published showtimes still exist.
- Published showtimes require both the room and cinema to be `active`.

## Seat Layout Rules

- `PUT /api/admin/rooms/{id}/seats` replaces the full layout in a single transaction.
- Duplicate positions in the payload are rejected before writing.
- `rooms.total_seats` is always recomputed on the server from the saved seat layout.
- Seat layout replacement is blocked when:
  - the room has future published showtimes
  - the room already has `pending` or `paid` ticket orders

## Showtime Overlap Rules

- `end_time` is calculated on the server.
- Formula:
  - `end_time = start_time + movie.duration_minutes + room.cleaning_buffer_minutes`
- Cross-day showtimes are rejected in v1.
- Showtimes in the same room and date cannot overlap.
- Overlap check ignores only `cancelled` and `archived` showtimes.

## Public Availability Rules

- Public showtime lists only include showtimes with:
  - `showtimes.status = 'published'`
  - `rooms.status = 'active'`
  - `cinemas.status = 'active'`
- A seat is selectable only when:
  - seat status is `available`
  - the seat is not already used by a `pending` or `paid` order

## Observability

- Admin mutations log `actor_id`, entity id, target status, and `duration_ms`.
- Business-rule blocks are logged at info-level by services.
- Unexpected exceptions are logged at error-level.

<?php

namespace Tests\Unit;

use App\Services\HomeService;
use App\Repositories\MovieRepository;
use App\Repositories\ProductRepository;
use App\Repositories\CinemaRepository;
use App\Repositories\MovieReviewRepository;
use App\Core\Logger;
use PHPUnit\Framework\TestCase;

class HomeServiceTest extends TestCase
{
    public function testGetHomeDataReturnsSuccessWithMockedData(): void
    {
        $movieRepo = $this->createMock(MovieRepository::class);
        $productRepo = $this->createMock(ProductRepository::class);
        $cinemaRepo = $this->createMock(CinemaRepository::class);
        $reviewRepo = $this->createMock(MovieReviewRepository::class);
        $logger = $this->createMock(Logger::class);

        // Mock MovieRepository::paginatePublicCatalog for "now_showing"
        $movieRepo->method('paginatePublicCatalog')
            ->willReturnMap([
                [['status' => 'now_showing', 'page' => 1, 'per_page' => 10, 'sort' => 'popular'], [
                    'items' => [
                        ['id' => 1, 'title' => 'Movie 1', 'average_rating' => 4.5, 'duration_minutes' => 120, 'primary_category_name' => 'Sci-Fi'],
                        ['id' => 2, 'title' => 'Movie 2', 'average_rating' => 4.0, 'duration_minutes' => 100, 'primary_category_name' => 'Drama'],
                    ],
                    'meta' => ['total' => 2]
                ]],
                [['status' => 'coming_soon', 'page' => 1, 'per_page' => 5, 'sort' => 'newest'], [
                    'items' => [
                        ['id' => 3, 'title' => 'Movie 3', 'average_rating' => 0.0, 'duration_minutes' => 90, 'primary_category_name' => 'Action'],
                    ],
                    'meta' => ['total' => 1]
                ]]
            ]);

        $movieRepo->method('countPublicCatalog')->willReturn(3);

        // Mock ProductRepository::paginatePublicCatalog
        $productRepo->method('paginatePublicCatalog')
            ->willReturn([
                'items' => [
                    ['id' => 1, 'name' => 'Product 1', 'price' => 10.0, 'currency' => 'USD'],
                ],
                'meta' => ['total' => 5]
            ]);

        // Mock CinemaRepository::summarize
        $cinemaRepo->method('summarize')->willReturn(['room_count' => 8]);

        $service = new HomeService($movieRepo, $productRepo, $cinemaRepo, $reviewRepo, $logger);
        $result = $service->getHomeData();

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']['hero_movies']);
        $this->assertSame('Movie 1', $result['data']['hero_movies'][0]['title']);
        $this->assertSame(3, $result['data']['stats']['movies_count']);
        $this->assertSame(8, $result['data']['stats']['rooms_count']);
        $this->assertSame(5, $result['data']['stats']['products_count']);
        $this->assertSame(4.3, $result['data']['stats']['avg_rating']); // (4.5 + 4.0) / 2 = 4.25 -> rounded to 4.3 in round($avgRating, 1) or 4.3 based on logic
        
        $this->assertCount(2, $result['data']['now_showing']);
        $this->assertCount(1, $result['data']['coming_soon']);
        $this->assertCount(1, $result['data']['popular_products']);
    }

    public function testGetHomeDataHandlesExceptionGracefully(): void
    {
        $movieRepo = $this->createMock(MovieRepository::class);
        $movieRepo->method('paginatePublicCatalog')->willThrowException(new \Exception('Database error'));

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('error');

        $service = new HomeService($movieRepo, null, null, null, $logger);
        $result = $service->getHomeData();

        $this->assertFalse($result['success']);
        $this->assertSame('Lỗi khi tải dữ liệu trang chủ.', $result['message']);
    }
}

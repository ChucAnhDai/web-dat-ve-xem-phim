<?php

namespace Tests\Unit;

use App\Validators\MovieManagementValidator;
use PHPUnit\Framework\TestCase;

class MovieManagementValidatorTest extends TestCase
{
    public function testValidateMoviePayloadRejectsInvalidFields(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateMoviePayload([
            'title' => 'Test Movie',
            'primary_category_id' => 'abc',
            'duration_minutes' => 0,
            'status' => 'published',
            'poster_url' => 'not-a-url',
            'category_ids' => [],
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('primary_category_id', $result['errors']);
        $this->assertArrayHasKey('duration_minutes', $result['errors']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertArrayHasKey('poster_url', $result['errors']);
        $this->assertArrayHasKey('category_ids', $result['errors']);
    }

    public function testValidateMoviePayloadNormalizesSlugAndCategoryIds(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateMoviePayload([
            'title' => '  Demo Movie  ',
            'primary_category_id' => '2',
            'duration_minutes' => '120',
            'status' => 'now_showing',
            'category_ids' => ['2', '3', '3'],
            'average_rating' => '4.567',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('demo-movie', $result['data']['slug']);
        $this->assertSame([2, 3], $result['data']['category_ids']);
        $this->assertSame(4.57, $result['data']['average_rating']);
    }

    public function testValidateAssetPayloadForcesArchivedAssetsToBeNonPrimary(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateAssetPayload([
            'movie_id' => '3',
            'asset_type' => 'poster',
            'image_url' => 'https://example.com/poster.jpg',
            'sort_order' => '1',
            'is_primary' => '1',
            'status' => 'archived',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['data']['is_primary']);
        $this->assertSame('archived', $result['data']['status']);
    }

    public function testValidateReviewModerationPayloadForcesRejectedReviewHidden(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateReviewModerationPayload([
            'status' => 'rejected',
            'is_visible' => '1',
            'moderation_note' => 'Contains spoilers.',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('rejected', $result['data']['status']);
        $this->assertSame(0, $result['data']['is_visible']);
        $this->assertSame('Contains spoilers.', $result['data']['moderation_note']);
    }

    public function testValidateOphimImportPayloadNormalizesFlagsAndStatusOverride(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateOphimImportPayload([
            'slug' => '  Tro-Choi-Con-Muc  ',
            'sync_images' => 'true',
            'overwrite_existing' => '0',
            'status_override' => 'COMING_SOON',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('tro-choi-con-muc', $result['data']['slug']);
        $this->assertSame(1, $result['data']['sync_images']);
        $this->assertSame(0, $result['data']['overwrite_existing']);
        $this->assertSame('coming_soon', $result['data']['status_override']);
    }

    public function testValidateOphimImportPayloadRejectsInvalidSlug(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateOphimImportPayload([
            'slug' => 'Tro Choi Con Muc',
        ]);

        $this->assertArrayHasKey('slug', $result['errors']);
    }

    public function testValidateOphimBatchImportPayloadNormalizesBatchFields(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateOphimBatchImportPayload([
            'list_slug' => 'PHIM-CHIEU-RAP',
            'page' => '2',
            'limit' => '24',
            'sync_images' => 'false',
            'overwrite_existing' => 'true',
            'status_override' => 'NOW_SHOWING',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('phim-chieu-rap', $result['data']['list_slug']);
        $this->assertSame(2, $result['data']['page']);
        $this->assertSame(24, $result['data']['limit']);
        $this->assertSame(0, $result['data']['sync_images']);
        $this->assertSame(1, $result['data']['overwrite_existing']);
        $this->assertSame('now_showing', $result['data']['status_override']);
    }

    public function testValidateOphimBatchImportPayloadRejectsInvalidListSlug(): void
    {
        $validator = new MovieManagementValidator();

        $result = $validator->validateOphimBatchImportPayload([
            'list_slug' => 'invalid-list',
            'page' => '0',
            'limit' => '99',
        ]);

        $this->assertArrayHasKey('list_slug', $result['errors']);
        $this->assertArrayHasKey('page', $result['errors']);
        $this->assertArrayHasKey('limit', $result['errors']);
    }
}

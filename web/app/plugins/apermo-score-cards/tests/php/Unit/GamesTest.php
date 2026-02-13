<?php
/**
 * Unit tests for the Games class.
 *
 * @package Apermo\ScoreCards\Tests\Unit
 */

declare(strict_types=1);

namespace Apermo\ScoreCards\Tests\Unit;

use Apermo\ScoreCards\Games;
use Apermo\ScoreCards\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Test case for Games class.
 */
class GamesTest extends TestCase
{
    /**
     * Test meta prefix constant value.
     */
    public function testMetaPrefixConstant(): void
    {
        $this->assertSame('_asc_game_', Games::META_PREFIX);
    }

    /**
     * Test get returns null when no data exists.
     */
    public function testGetReturnsNullWhenNoDataExists(): void
    {
        Functions\when('get_post_meta')->justReturn('');

        $result = Games::get(1, 'block-123');

        $this->assertNull($result);
    }

    /**
     * Test get returns null when data is not an array.
     */
    public function testGetReturnsNullWhenDataIsNotArray(): void
    {
        Functions\when('get_post_meta')->justReturn('not-an-array');

        $result = Games::get(1, 'block-123');

        $this->assertNull($result);
    }

    /**
     * Test get returns data when valid array exists.
     */
    public function testGetReturnsDataWhenValidArrayExists(): void
    {
        $expectedData = [
            'blockId' => 'block-123',
            'gameType' => 'wizard',
            'playerIds' => [1, 2, 3],
            'status' => 'in_progress',
        ];

        Functions\when('get_post_meta')->justReturn($expectedData);

        $result = Games::get(1, 'block-123');

        $this->assertSame($expectedData, $result);
    }

    /**
     * Test save creates correct meta key.
     */
    public function testSaveUsesCorrectMetaKey(): void
    {
        $postId = 42;
        $blockId = 'test-block';
        $expectedMetaKey = '_asc_game_test-block';

        Functions\when('current_time')->justReturn('2024-01-15T12:00:00+00:00');
        Functions\expect('wp_parse_args')
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });
        Functions\expect('update_post_meta')
            ->once()
            ->with($postId, $expectedMetaKey, \Mockery::type('array'))
            ->andReturn(true);

        $result = Games::save($postId, $blockId, ['gameType' => 'wizard']);

        $this->assertTrue($result);
    }

    /**
     * Test save returns true when data already exists.
     */
    public function testSaveReturnsTrueWhenDataAlreadyExists(): void
    {
        // The full data structure that would be saved after wp_parse_args.
        $fullData = [
            'blockId' => 'block-123',
            'gameType' => 'wizard',
            'playerIds' => [],
            'status' => 'in_progress',
            'rounds' => [],
            'finalScores' => [],
            'winnerId' => null,
            'startedAt' => '2024-01-15T12:00:00+00:00',
            'completedAt' => null,
        ];

        Functions\when('current_time')->justReturn('2024-01-15T12:00:00+00:00');
        Functions\expect('wp_parse_args')
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });
        Functions\expect('update_post_meta')->andReturn(false);
        // Return the same full data that would be saved.
        Functions\expect('get_post_meta')->andReturn($fullData);
        Functions\when('maybe_serialize')->alias(function ($value) {
            return serialize($value);
        });

        // This should return true because existing data matches.
        $result = Games::save(1, 'block-123', ['gameType' => 'wizard']);

        $this->assertTrue($result);
    }

    /**
     * Test delete removes the correct meta key.
     */
    public function testDeleteUsesCorrectMetaKey(): void
    {
        $postId = 42;
        $blockId = 'test-block';
        $expectedMetaKey = '_asc_game_test-block';

        Functions\expect('delete_post_meta')
            ->once()
            ->with($postId, $expectedMetaKey)
            ->andReturn(true);

        $result = Games::delete($postId, $blockId);

        $this->assertTrue($result);
    }

    /**
     * Test add_round creates game if not exists.
     */
    public function testAddRoundCreatesGameIfNotExists(): void
    {
        $postId = 1;
        $blockId = 'block-123';
        $roundData = [
            1 => ['bid' => 0, 'won' => 0],
            2 => ['bid' => 1, 'won' => 1],
        ];

        // First call for get() returns null (no existing game).
        Functions\expect('get_post_meta')
            ->once()
            ->with($postId, '_asc_game_block-123', true)
            ->andReturn('');

        Functions\when('current_time')->justReturn('2024-01-15T12:00:00+00:00');
        Functions\expect('wp_parse_args')
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function ($postId, $metaKey, $data) use ($roundData) {
                // Verify the round was added.
                $this->assertCount(1, $data['rounds']);
                $this->assertEquals($roundData, $data['rounds'][0]);
                // Verify player IDs were extracted.
                $this->assertEquals([1, 2], $data['playerIds']);
                return true;
            });

        $result = Games::add_round($postId, $blockId, $roundData);

        $this->assertTrue($result);
    }

    /**
     * Test add_round appends to existing game.
     */
    public function testAddRoundAppendsToExistingGame(): void
    {
        $postId = 1;
        $blockId = 'block-123';
        $existingGame = [
            'blockId' => 'block-123',
            'gameType' => 'wizard',
            'playerIds' => [1, 2],
            'status' => 'in_progress',
            'rounds' => [
                [1 => ['bid' => 0, 'won' => 0], 2 => ['bid' => 1, 'won' => 1]],
            ],
            'finalScores' => [],
            'winnerId' => null,
            'startedAt' => '2024-01-15T12:00:00+00:00',
            'completedAt' => null,
        ];
        $newRoundData = [1 => ['bid' => 1, 'won' => 0], 2 => ['bid' => 0, 'won' => 1]];

        Functions\expect('get_post_meta')
            ->once()
            ->with($postId, '_asc_game_block-123', true)
            ->andReturn($existingGame);

        Functions\when('current_time')->justReturn('2024-01-15T13:00:00+00:00');
        Functions\expect('wp_parse_args')
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function ($postId, $metaKey, $data) use ($newRoundData) {
                // Verify we now have 2 rounds.
                $this->assertCount(2, $data['rounds']);
                $this->assertEquals($newRoundData, $data['rounds'][1]);
                return true;
            });

        $result = Games::add_round($postId, $blockId, $newRoundData);

        $this->assertTrue($result);
    }

    /**
     * Test update_round fails when game doesn't exist.
     */
    public function testUpdateRoundFailsWhenGameDoesNotExist(): void
    {
        Functions\when('get_post_meta')->justReturn('');

        $result = Games::update_round(1, 'block-123', 0, []);

        $this->assertFalse($result);
    }

    /**
     * Test update_round fails when round index doesn't exist.
     */
    public function testUpdateRoundFailsWhenRoundIndexDoesNotExist(): void
    {
        $existingGame = [
            'rounds' => [
                [1 => ['bid' => 0, 'won' => 0]],
            ],
        ];

        Functions\when('get_post_meta')->justReturn($existingGame);

        // Try to update round index 5, which doesn't exist.
        $result = Games::update_round(1, 'block-123', 5, []);

        $this->assertFalse($result);
    }

    /**
     * Test complete marks game as completed with correct data.
     */
    public function testCompleteMarksGameAsCompleted(): void
    {
        $postId = 1;
        $blockId = 'block-123';
        $existingGame = [
            'blockId' => 'block-123',
            'gameType' => 'wizard',
            'playerIds' => [1, 2],
            'status' => 'in_progress',
            'rounds' => [],
            'finalScores' => [],
            'winnerId' => null,
            'startedAt' => '2024-01-15T12:00:00+00:00',
            'completedAt' => null,
        ];
        $finalScores = [1 => 100, 2 => 80];
        $winnerId = 1;

        Functions\expect('get_post_meta')
            ->once()
            ->with($postId, '_asc_game_block-123', true)
            ->andReturn($existingGame);

        Functions\when('current_time')->justReturn('2024-01-15T14:00:00+00:00');
        Functions\expect('wp_parse_args')
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function ($postId, $metaKey, $data) use ($finalScores, $winnerId) {
                $this->assertEquals('completed', $data['status']);
                $this->assertEquals($finalScores, $data['finalScores']);
                $this->assertEquals($winnerId, $data['winnerId']);
                $this->assertNotNull($data['completedAt']);
                return true;
            });

        $result = Games::complete($postId, $blockId, $finalScores, $winnerId);

        $this->assertTrue($result);
    }

    /**
     * Test complete fails when game doesn't exist.
     */
    public function testCompleteFailsWhenGameDoesNotExist(): void
    {
        Functions\when('get_post_meta')->justReturn('');

        $result = Games::complete(1, 'block-123', [], 1);

        $this->assertFalse($result);
    }
}

<?php
/**
 * Unit tests for the Wizard_Renderer class.
 *
 * @package Apermo\ScoreCards\Tests\Unit
 */

declare(strict_types=1);

namespace Apermo\ScoreCards\Tests\Unit;

use Apermo\ScoreCards\Wizard_Renderer;
use Apermo\ScoreCards\Tests\TestCase;

/**
 * Test case for Wizard scoring logic.
 */
class WizardRendererTest extends TestCase
{
    /**
     * Test round score calculation when bid equals won.
     */
    public function testCalculateRoundScoreBidEqualsWon(): void
    {
        // Bid 0, won 0 => 20 + (0 * 10) = 20.
        $this->assertSame(20, Wizard_Renderer::calculate_round_score(0, 0));

        // Bid 3, won 3 => 20 + (3 * 10) = 50.
        $this->assertSame(50, Wizard_Renderer::calculate_round_score(3, 3));

        // Bid 5, won 5 => 20 + (5 * 10) = 70.
        $this->assertSame(70, Wizard_Renderer::calculate_round_score(5, 5));
    }

    /**
     * Test round score calculation when bid does not equal won.
     */
    public function testCalculateRoundScoreBidNotEqualsWon(): void
    {
        // Bid 0, won 1 => -10 * |0 - 1| = -10.
        $this->assertSame(-10, Wizard_Renderer::calculate_round_score(0, 1));

        // Bid 3, won 0 => -10 * |3 - 0| = -30.
        $this->assertSame(-30, Wizard_Renderer::calculate_round_score(3, 0));

        // Bid 2, won 4 => -10 * |2 - 4| = -20.
        $this->assertSame(-20, Wizard_Renderer::calculate_round_score(2, 4));
    }

    /**
     * Test effective bid without werewolf.
     */
    public function testGetEffectiveBidWithoutWerewolf(): void
    {
        $round = [
            1 => ['bid' => 2, 'won' => 2],
            2 => ['bid' => 1, 'won' => 0],
        ];

        $this->assertSame(2, Wizard_Renderer::get_effective_bid($round, 1));
        $this->assertSame(1, Wizard_Renderer::get_effective_bid($round, 2));
    }

    /**
     * Test effective bid with werewolf adjustment.
     */
    public function testGetEffectiveBidWithWerewolf(): void
    {
        $round = [
            1 => ['bid' => 2, 'won' => 3],
            2 => ['bid' => 1, 'won' => 0],
            '_meta' => [
                'werewolfPlayerId' => 1,
                'werewolfAdjustment' => 1,
            ],
        ];

        // Player 1 has werewolf: bid 2 + adjustment 1 = 3.
        $this->assertSame(3, Wizard_Renderer::get_effective_bid($round, 1));

        // Player 2 is not the werewolf: bid 1.
        $this->assertSame(1, Wizard_Renderer::get_effective_bid($round, 2));
    }

    /**
     * Test effective bid returns 0 for missing player data.
     */
    public function testGetEffectiveBidReturnZeroForMissingPlayer(): void
    {
        $round = [
            1 => ['bid' => 2, 'won' => 2],
        ];

        $this->assertSame(0, Wizard_Renderer::get_effective_bid($round, 999));
    }

    /**
     * Test effective bid with negative werewolf adjustment.
     */
    public function testGetEffectiveBidWithNegativeWerewolfAdjustment(): void
    {
        $round = [
            1 => ['bid' => 3, 'won' => 2],
            '_meta' => [
                'werewolfPlayerId' => 1,
                'werewolfAdjustment' => -1,
            ],
        ];

        // Player 1: bid 3 + adjustment -1 = 2.
        $this->assertSame(2, Wizard_Renderer::get_effective_bid($round, 1));
    }
}

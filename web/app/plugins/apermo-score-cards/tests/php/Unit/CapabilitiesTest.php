<?php
/**
 * Unit tests for the Capabilities class.
 *
 * @package Apermo\ScoreCards\Tests\Unit
 */

declare(strict_types=1);

namespace Apermo\ScoreCards\Tests\Unit;

use Apermo\ScoreCards\Capabilities;
use Apermo\ScoreCards\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Test case for Capabilities class.
 */
class CapabilitiesTest extends TestCase
{
    /**
     * Test constants are defined correctly.
     */
    public function testConstantsAreDefined(): void
    {
        $this->assertSame('manage_scorecards', Capabilities::CAPABILITY);
        $this->assertSame('scorecard_maintainer', Capabilities::ROLE);
        $this->assertSame(8 * 3600, Capabilities::EDIT_WINDOW_SECONDS); // 8 hours in seconds
    }

    /**
     * Test map_meta_cap returns original caps for non-scorecard capabilities.
     */
    public function testMapMetaCapReturnsOriginalCapsForNonScorecardCap(): void
    {
        $caps = ['edit_posts'];

        $result = Capabilities::map_meta_cap($caps, 'edit_post', 1, []);

        $this->assertSame($caps, $result);
    }

    /**
     * Test map_meta_cap returns do_not_allow when post ID is missing.
     */
    public function testMapMetaCapReturnsDenyWhenPostIdMissing(): void
    {
        $result = Capabilities::map_meta_cap([], 'manage_scorecard', 1, []);

        $this->assertSame(['do_not_allow'], $result);
    }

    /**
     * Test map_meta_cap returns do_not_allow when user lacks capability.
     */
    public function testMapMetaCapReturnsDenyWhenUserLacksCapability(): void
    {
        Functions\when('user_can')->justReturn(false);

        $result = Capabilities::map_meta_cap([], 'manage_scorecard', 1, [42]);

        $this->assertSame(['do_not_allow'], $result);
    }

    /**
     * Test map_meta_cap returns do_not_allow when outside edit window.
     */
    public function testMapMetaCapReturnsDenyWhenOutsideEditWindow(): void
    {
        Functions\when('user_can')->justReturn(true);

        // Create a post object that was modified 10 hours ago (outside 8-hour window).
        $post = new \stdClass();
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - 10 * 3600);
        Functions\when('get_post')->justReturn($post);

        $result = Capabilities::map_meta_cap([], 'manage_scorecard', 1, [42]);

        $this->assertSame(['do_not_allow'], $result);
    }

    /**
     * Test map_meta_cap returns capability when user can manage within window.
     */
    public function testMapMetaCapReturnsCapabilityWhenUserCanManage(): void
    {
        Functions\when('user_can')->justReturn(true);

        // Create a post object that was modified 1 hour ago (within 8-hour window).
        $post = new \stdClass();
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - 3600);
        Functions\when('get_post')->justReturn($post);

        $result = Capabilities::map_meta_cap([], 'manage_scorecard', 1, [42]);

        $this->assertSame(['manage_scorecards'], $result);
    }

    /**
     * Test is_within_edit_window returns false when post doesn't exist.
     */
    public function testIsWithinEditWindowReturnsFalseWhenPostDoesNotExist(): void
    {
        Functions\when('get_post')->justReturn(null);

        $result = Capabilities::is_within_edit_window(999);

        $this->assertFalse($result);
    }

    /**
     * Test is_within_edit_window returns true for recently modified post.
     */
    public function testIsWithinEditWindowReturnsTrueForRecentPost(): void
    {
        $post = new \stdClass();
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - 3600); // 1 hour ago

        Functions\when('get_post')->justReturn($post);

        $result = Capabilities::is_within_edit_window(42);

        $this->assertTrue($result);
    }

    /**
     * Test is_within_edit_window returns false for old post.
     */
    public function testIsWithinEditWindowReturnsFalseForOldPost(): void
    {
        $post = new \stdClass();
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - 10 * 3600); // 10 hours ago

        Functions\when('get_post')->justReturn($post);

        $result = Capabilities::is_within_edit_window(42);

        $this->assertFalse($result);
    }

    /**
     * Test get_remaining_edit_time returns 0 when post doesn't exist.
     */
    public function testGetRemainingEditTimeReturnsZeroWhenPostDoesNotExist(): void
    {
        Functions\when('get_post')->justReturn(null);

        $result = Capabilities::get_remaining_edit_time(999);

        $this->assertSame(0, $result);
    }

    /**
     * Test get_remaining_edit_time returns correct value.
     */
    public function testGetRemainingEditTimeReturnsCorrectValue(): void
    {
        $post = new \stdClass();
        // Modified 2 hours ago, so 6 hours remain.
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - 2 * 3600);

        Functions\when('get_post')->justReturn($post);

        $result = Capabilities::get_remaining_edit_time(42);

        // Should be approximately 6 hours (allowing 5 seconds tolerance).
        $expected = 6 * 3600;
        $this->assertGreaterThan($expected - 5, $result);
        $this->assertLessThan($expected + 5, $result);
    }

    /**
     * Test get_remaining_edit_time returns 0 when window has passed.
     */
    public function testGetRemainingEditTimeReturnsZeroWhenWindowPassed(): void
    {
        $post = new \stdClass();
        $post->post_modified_gmt = gmdate('Y-m-d H:i:s', time() - 10 * 3600); // 10 hours ago

        Functions\when('get_post')->justReturn($post);

        $result = Capabilities::get_remaining_edit_time(42);

        $this->assertSame(0, $result);
    }

    /**
     * Test user_can_manage uses current user when no user ID provided.
     */
    public function testUserCanManageUsesCurrentUserWhenNotProvided(): void
    {
        Functions\expect('get_current_user_id')->once()->andReturn(1);
        Functions\expect('user_can')
            ->once()
            ->with(1, 'manage_scorecard', 42)
            ->andReturn(true);

        $result = Capabilities::user_can_manage(42);

        $this->assertTrue($result);
    }

    /**
     * Test user_can_manage uses provided user ID.
     */
    public function testUserCanManageUsesProvidedUserId(): void
    {
        Functions\expect('user_can')
            ->once()
            ->with(5, 'manage_scorecard', 42)
            ->andReturn(false);

        $result = Capabilities::user_can_manage(42, 5);

        $this->assertFalse($result);
    }
}

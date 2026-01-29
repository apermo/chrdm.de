<?php
/**
 * Capabilities and roles for Apermo Score Cards.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capabilities class for managing custom roles and capabilities.
 */
class Capabilities {

	/**
	 * Custom capability for managing score cards.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_scorecards';

	/**
	 * Custom role slug.
	 *
	 * @var string
	 */
	public const ROLE = 'scorecard_maintainer';

	/**
	 * Time window in seconds for editing scores after post update (8 hours).
	 *
	 * @var int
	 */
	public const EDIT_WINDOW_SECONDS = 8 * HOUR_IN_SECONDS;

	/**
	 * Initialize capabilities.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Register the custom role and capability on plugin activation.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Add the custom role.
		add_role(
			self::ROLE,
			__( 'Score Card Maintainer', 'apermo-score-cards' ),
			array(
				'read'            => true,
				self::CAPABILITY  => true,
			)
		);

		// Add capability to administrators.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAPABILITY );
		}

		// Add capability to editors.
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::CAPABILITY );
		}
	}

	/**
	 * Remove the custom role and capability on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		// Remove custom role.
		remove_role( self::ROLE );

		// Remove capability from administrators.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( self::CAPABILITY );
		}

		// Remove capability from editors.
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->remove_cap( self::CAPABILITY );
		}
	}

	/**
	 * Map meta capabilities for score card management.
	 *
	 * @param array  $caps    Required capabilities.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional arguments.
	 * @return array Modified capabilities.
	 */
	public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'manage_scorecard' !== $cap ) {
			return $caps;
		}

		// First argument is the post ID.
		$post_id = $args[0] ?? 0;

		if ( ! $post_id ) {
			return array( 'do_not_allow' );
		}

		// Check if user has the base capability.
		if ( ! user_can( $user_id, self::CAPABILITY ) ) {
			return array( 'do_not_allow' );
		}

		// Check time window.
		if ( ! self::is_within_edit_window( $post_id ) ) {
			return array( 'do_not_allow' );
		}

		// User has capability and is within time window.
		return array( self::CAPABILITY );
	}

	/**
	 * Check if the current time is within the edit window for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if within edit window.
	 */
	public static function is_within_edit_window( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		$modified_time = strtotime( $post->post_modified_gmt );
		$current_time  = time();
		$elapsed       = $current_time - $modified_time;

		return $elapsed <= self::EDIT_WINDOW_SECONDS;
	}

	/**
	 * Get remaining edit time for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Remaining seconds, or 0 if window has passed.
	 */
	public static function get_remaining_edit_time( int $post_id ): int {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return 0;
		}

		$modified_time = strtotime( $post->post_modified_gmt );
		$current_time  = time();
		$elapsed       = $current_time - $modified_time;
		$remaining     = self::EDIT_WINDOW_SECONDS - $elapsed;

		return max( 0, $remaining );
	}

	/**
	 * Check if a user can manage a specific score card.
	 *
	 * @param int $post_id Post ID containing the score card.
	 * @param int $user_id Optional user ID, defaults to current user.
	 * @return bool True if user can manage the score card.
	 */
	public static function user_can_manage( int $post_id, int $user_id = 0 ): bool {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		return user_can( $user_id, 'manage_scorecard', $post_id );
	}

	/**
	 * Get human-readable time remaining.
	 *
	 * @param int $post_id Post ID.
	 * @return string Human-readable time remaining.
	 */
	public static function get_remaining_edit_time_human( int $post_id ): string {
		$remaining = self::get_remaining_edit_time( $post_id );

		if ( $remaining <= 0 ) {
			return __( 'Edit window closed', 'apermo-score-cards' );
		}

		return human_time_diff( time(), time() + $remaining );
	}
}
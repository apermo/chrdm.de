<?php
/**
 * Base rendering helpers for score card blocks.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait with shared rendering utilities for all score card blocks.
 */
trait Base_Render {

	/**
	 * Medals for podium positions.
	 *
	 * @var array<int, string>
	 */
	protected static array $medals = array(
		1 => '🥇',
		2 => '🥈',
		3 => '🥉',
	);

	/**
	 * Calculate positions with tie handling (higher score wins).
	 *
	 * @param array $players      Sorted array of players (best first).
	 * @param array $final_scores Scores keyed by player ID.
	 * @return array<int, int> Positions keyed by player ID.
	 */
	protected static function calculate_positions_higher_wins( array $players, array $final_scores ): array {
		$positions        = array();
		$current_position = 1;
		$previous_score   = null;

		foreach ( $players as $index => $player ) {
			$score = $final_scores[ $player['id'] ];

			if ( $score !== $previous_score ) {
				$current_position = $index + 1;
				$previous_score   = $score;
			}

			$positions[ $player['id'] ] = $current_position;
		}

		return $positions;
	}

	/**
	 * Calculate positions with tie handling (lower score wins).
	 *
	 * @param array $players      Sorted array of players (best first).
	 * @param array $final_scores Scores keyed by player ID.
	 * @return array<int, int> Positions keyed by player ID.
	 */
	protected static function calculate_positions_lower_wins( array $players, array $final_scores ): array {
		return self::calculate_positions_higher_wins( $players, $final_scores );
	}

	/**
	 * Build a players map for quick lookup by ID.
	 *
	 * @param array $players Array of player data.
	 * @return array<int, array> Players keyed by ID.
	 */
	protected static function build_players_map( array $players ): array {
		$map = array();
		foreach ( $players as $player ) {
			$map[ $player['id'] ] = $player;
		}
		return $map;
	}

	/**
	 * Render a player avatar image tag.
	 *
	 * @param array  $player    Player data.
	 * @param string $css_class CSS class for the image.
	 * @return void
	 */
	protected static function render_avatar( array $player, string $css_class ): void {
		if ( ! empty( $player['avatarUrl'] ) ) {
			printf(
				'<img src="%s" alt="" class="%s" />',
				esc_url( $player['avatarUrl'] ),
				esc_attr( $css_class )
			);
		}
	}

	/**
	 * Render a status badge.
	 *
	 * @param string $status Game status.
	 * @param string $prefix CSS prefix for the block.
	 * @return void
	 */
	protected static function render_status_badge( string $status, string $prefix ): void {
		if ( 'completed' === $status ) {
			printf(
				'<span class="%1$s__status %1$s__status--completed">%2$s</span>',
				esc_attr( $prefix ),
				esc_html__( 'Completed', 'apermo-score-cards' )
			);
		}
	}

	/**
	 * Render a medal or position number.
	 *
	 * @param int    $position Position number.
	 * @param string $css_class CSS class for the medal span.
	 * @return void
	 */
	protected static function render_position( int $position, string $css_class = '' ): void {
		$medal = self::$medals[ $position ] ?? '';

		if ( $medal ) {
			printf(
				'<span class="%s">%s</span>',
				esc_attr( $css_class ),
				esc_html( $medal )
			);
		} else {
			echo esc_html( (string) $position );
		}
	}

	/**
	 * Render the pending state (waiting for scores).
	 *
	 * @param array  $players Array of player data.
	 * @param string $prefix  CSS prefix for the block.
	 * @param string $table_class CSS class for the table.
	 * @return void
	 */
	protected static function render_pending_state( array $players, string $prefix, string $table_class ): void {
		?>
		<div class="<?php echo esc_attr( $prefix . '__pending' ); ?>">
			<p><?php esc_html_e( 'Waiting for scores...', 'apermo-score-cards' ); ?></p>
			<table class="<?php echo esc_attr( $table_class ); ?>">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $players as $index => $player ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
							<td class="<?php echo esc_attr( $table_class . '__player' ); ?>">
								<?php self::render_avatar( $player, $table_class . '__avatar' ); ?>
								<span><?php echo esc_html( $player['name'] ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render player selector container for edit mode.
	 *
	 * @param string $prefix CSS prefix for the block.
	 * @return void
	 */
	protected static function render_player_editor( string $prefix ): void {
		?>
		<div class="<?php echo esc_attr( $prefix . '__player-actions' ); ?>">
			<button type="button" class="<?php echo esc_attr( $prefix . '__edit-players-btn' ); ?>">
				<?php esc_html_e( 'Edit Players', 'apermo-score-cards' ); ?>
			</button>
		</div>
		<div class="asc-player-selector-container" hidden></div>
		<?php
	}
}

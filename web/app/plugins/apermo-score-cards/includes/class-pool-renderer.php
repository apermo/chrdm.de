<?php
/**
 * Pool billiard game renderer.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Pool billiard score card block.
 */
class Pool_Renderer extends Game_Renderer {

	/**
	 * Games list.
	 *
	 * @var array
	 */
	protected array $games_list = array();

	/**
	 * Whether there are any games.
	 *
	 * @var bool
	 */
	protected bool $has_games = false;

	/**
	 * Standings keyed by player ID.
	 *
	 * @var array
	 */
	protected array $standings = array();

	/**
	 * Positions keyed by player ID.
	 *
	 * @var array
	 */
	protected array $positions = array();

	/**
	 * Whether game is completed.
	 *
	 * @var bool
	 */
	protected bool $is_completed = false;

	/**
	 * {@inheritDoc}
	 */
	protected function get_css_prefix(): string {
		return 'asc-pool';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_title(): string {
		return __( 'Pool Billiard', 'apermo-score-cards' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_display_class(): string {
		return 'asc-pool';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function calculate_scores(): void {
		$this->games_list   = $this->game['games'] ?? array();
		$this->has_games    = ! empty( $this->games_list );
		$this->is_completed = 'completed' === $this->status;
		$stored_positions   = $this->game['positions'] ?? array();
		$stored_scores      = $this->game['finalScores'] ?? array();

		// Initialize standings.
		foreach ( $this->player_ids as $pid ) {
			$this->standings[ $pid ] = array(
				'playerId'   => $pid,
				'wins'       => 0,
				'losses'     => 0,
				'points'     => 0,
				'headToHead' => array(),
			);
		}

		// Calculate standings from games.
		foreach ( $this->games_list as $g ) {
			$winner_id = $g['winnerId'] ?? null;
			$player1   = $g['player1'] ?? null;
			$player2   = $g['player2'] ?? null;

			if ( ! $winner_id || ! $player1 || ! $player2 ) {
				continue;
			}

			$loser_id = ( $winner_id === $player1 ) ? $player2 : $player1;

			if ( isset( $this->standings[ $winner_id ] ) ) {
				$this->standings[ $winner_id ]['wins']++;
				if ( ! $this->is_completed ) {
					$this->standings[ $winner_id ]['points'] += 3;
				}
				if ( ! isset( $this->standings[ $winner_id ]['headToHead'][ $loser_id ] ) ) {
					$this->standings[ $winner_id ]['headToHead'][ $loser_id ] = array( 'wins' => 0, 'losses' => 0 );
				}
				$this->standings[ $winner_id ]['headToHead'][ $loser_id ]['wins']++;
			}

			if ( isset( $this->standings[ $loser_id ] ) ) {
				$this->standings[ $loser_id ]['losses']++;
				if ( ! $this->is_completed ) {
					$this->standings[ $loser_id ]['points'] += 1;
				}
				if ( ! isset( $this->standings[ $loser_id ]['headToHead'][ $winner_id ] ) ) {
					$this->standings[ $loser_id ]['headToHead'][ $winner_id ] = array( 'wins' => 0, 'losses' => 0 );
				}
				$this->standings[ $loser_id ]['headToHead'][ $winner_id ]['losses']++;
			}
		}

		// Use stored scores for completed games.
		if ( $this->is_completed && ! empty( $stored_scores ) ) {
			foreach ( $this->standings as $pid => &$s ) {
				$s['points'] = $stored_scores[ $pid ] ?? $s['points'];
			}
			unset( $s );
		}

		// Sort standings.
		uasort(
			$this->standings,
			function ( $a, $b ) {
				if ( $a['points'] !== $b['points'] ) {
					return $b['points'] - $a['points'];
				}

				$a_total = $a['wins'] + $a['losses'];
				$b_total = $b['wins'] + $b['losses'];
				$a_pct   = $a_total > 0 ? $a['wins'] / $a_total : 0;
				$b_pct   = $b_total > 0 ? $b['wins'] / $b_total : 0;

				if ( $a_pct !== $b_pct ) {
					return $b_pct <=> $a_pct;
				}

				$h2h = $a['headToHead'][ $b['playerId'] ] ?? null;
				if ( $h2h ) {
					$diff = $h2h['wins'] - $h2h['losses'];
					if ( 0 !== $diff ) {
						return -$diff;
					}
				}

				return wp_rand( -1, 1 );
			}
		);

		// Calculate positions.
		if ( $this->is_completed && ! empty( $stored_positions ) ) {
			$this->positions = $stored_positions;
		} else {
			$current_position = 1;
			$prev_key         = null;
			$index            = 0;

			foreach ( $this->standings as $pid => $s ) {
				$a_total = $s['wins'] + $s['losses'];
				$a_pct   = $a_total > 0 ? round( $s['wins'] / $a_total, 4 ) : 0;
				$tie_key = $s['points'] . '-' . $a_pct;

				if ( $tie_key !== $prev_key ) {
					$current_position = $index + 1;
					$prev_key         = $tie_key;
				}

				$this->positions[ $pid ] = $current_position;
				$index++;
			}
		}
	}

	/**
	 * Pool has a unique layout, so we override the wrapper.
	 *
	 * {@inheritDoc}
	 */
	protected function render_wrapper(): void {
		$prefix = $this->get_css_prefix();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes.
		echo '<div ' . $this->get_wrapper_attributes() . '>';

		$this->render_header();
		$this->render_standings_table();

		if ( $this->has_games ) {
			$this->render_games_list();
		}

		if ( $this->can_manage ) {
			$this->render_manager_actions();
		} elseif ( ! $this->has_games ) {
			?>
			<div class="asc-pool__pending">
				<p><?php esc_html_e( 'Waiting for games...', 'apermo-score-cards' ); ?></p>
			</div>
			<?php
		}

		echo '</div>';
	}

	/**
	 * Render the standings table.
	 *
	 * @return void
	 */
	protected function render_standings_table(): void {
		?>
		<div class="asc-pool-standings">
			<table class="asc-pool-standings__table">
				<thead>
					<tr>
						<th class="asc-pool-standings__rank-col">#</th>
						<th class="asc-pool-standings__player-col"><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
						<th><?php esc_html_e( 'Pts', 'apermo-score-cards' ); ?></th>
						<th><?php esc_html_e( 'W', 'apermo-score-cards' ); ?></th>
						<th><?php esc_html_e( 'L', 'apermo-score-cards' ); ?></th>
						<th><?php esc_html_e( 'Win%', 'apermo-score-cards' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $this->standings as $pid => $s ) :
						$player   = $this->players_map[ $pid ] ?? null;
						$position = $this->positions[ $pid ] ?? 0;
						$medal    = self::$medals[ $position ] ?? '';
						$total    = $s['wins'] + $s['losses'];
						$win_pct  = $total > 0 ? (int) round( ( $s['wins'] / $total ) * 100 ) : 0;

						if ( ! $player ) {
							continue;
						}

						$row_classes = array( 'asc-pool-standings__row' );
						if ( $this->has_games && $position <= 3 ) {
							$row_classes[] = 'asc-pool-standings__row--position-' . $position;
						}
						?>
						<tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
							<td class="asc-pool-standings__rank">
								<?php if ( $this->has_games && $medal ) : ?>
									<?php echo esc_html( $medal ); ?>
								<?php elseif ( $this->has_games ) : ?>
									<?php echo esc_html( (string) $position ); ?>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
							<td class="asc-pool-standings__player">
								<?php self::render_avatar( $player, 'asc-pool-standings__avatar' ); ?>
								<span><?php echo esc_html( $player['name'] ); ?></span>
							</td>
							<td><strong><?php echo esc_html( (string) $s['points'] ); ?></strong></td>
							<td><?php echo esc_html( (string) $s['wins'] ); ?></td>
							<td><?php echo esc_html( (string) $s['losses'] ); ?></td>
							<td><?php echo $total > 0 ? esc_html( $win_pct . '%' ) : '-'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the games list.
	 *
	 * @return void
	 */
	protected function render_games_list(): void {
		?>
		<div class="asc-pool-games">
			<h4 class="asc-pool-games__title"><?php esc_html_e( 'Games', 'apermo-score-cards' ); ?></h4>
			<div class="asc-pool-games__list">
				<?php foreach ( $this->games_list as $game_index => $g ) :
					$player1   = $this->players_map[ $g['player1'] ] ?? null;
					$player2   = $this->players_map[ $g['player2'] ] ?? null;
					$winner_id = $g['winnerId'] ?? null;

					if ( ! $player1 || ! $player2 ) {
						continue;
					}

					$p1_is_winner = $winner_id === $g['player1'];
					$p2_is_winner = $winner_id === $g['player2'];
					?>
					<div class="asc-pool-games__item" data-game-index="<?php echo esc_attr( (string) $game_index ); ?>">
						<div class="asc-pool-games__matchup">
							<div class="asc-pool-games__player <?php echo $p1_is_winner ? 'asc-pool-games__player--winner' : 'asc-pool-games__player--loser'; ?>">
								<?php self::render_avatar( $player1, 'asc-pool-games__avatar' ); ?>
								<span class="asc-pool-games__name"><?php echo esc_html( $player1['name'] ); ?></span>
							</div>
							<span class="asc-pool-games__vs">vs</span>
							<div class="asc-pool-games__player <?php echo $p2_is_winner ? 'asc-pool-games__player--winner' : 'asc-pool-games__player--loser'; ?>">
								<?php self::render_avatar( $player2, 'asc-pool-games__avatar' ); ?>
								<span class="asc-pool-games__name"><?php echo esc_html( $player2['name'] ); ?></span>
							</div>
						</div>
						<div class="asc-pool-games__details">
							<?php if ( isset( $g['ballsLeft'] ) && $g['ballsLeft'] > 0 ) : ?>
								<span class="asc-pool-games__balls-left">
									<?php
									printf(
										/* translators: %d: number of balls left */
										esc_html__( '%d balls left', 'apermo-score-cards' ),
										$g['ballsLeft']
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( ! empty( $g['eightBallFoul'] ) ) : ?>
								<span class="asc-pool-games__foul">
									<?php esc_html_e( '8-ball foul', 'apermo-score-cards' ); ?>
								</span>
							<?php endif; ?>
						</div>
						<?php if ( $this->can_manage ) : ?>
							<div class="asc-pool-games__actions">
								<button type="button" class="asc-pool-games__edit-btn" data-action="edit">
									<?php esc_html_e( 'Edit', 'apermo-score-cards' ); ?>
								</button>
								<button type="button" class="asc-pool-games__delete-btn" data-action="delete">
									<?php esc_html_e( 'Delete', 'apermo-score-cards' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render manager actions and form containers.
	 *
	 * @return void
	 */
	protected function render_manager_actions(): void {
		$players_with_games = array();
		foreach ( $this->games_list as $g ) {
			$players_with_games[ $g['player1'] ?? 0 ] = true;
			$players_with_games[ $g['player2'] ?? 0 ] = true;
		}
		?>
		<div class="asc-pool__actions">
			<?php if ( 'completed' !== $this->status ) : ?>
				<button type="button" class="asc-pool__add-game-btn">
					<?php esc_html_e( 'Add Game', 'apermo-score-cards' ); ?>
				</button>
				<button type="button" class="asc-pool__edit-players-btn">
					<?php esc_html_e( 'Edit Players', 'apermo-score-cards' ); ?>
				</button>
				<?php if ( $this->has_games ) : ?>
					<button type="button" class="asc-pool__complete-btn">
						<?php esc_html_e( 'Finish', 'apermo-score-cards' ); ?>
					</button>
				<?php endif; ?>
			<?php else : ?>
				<button type="button" class="asc-pool__continue-btn">
					<?php esc_html_e( 'Continue', 'apermo-score-cards' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<div class="asc-pool-form-container" hidden></div>
		<div
			class="asc-player-selector-container"
			hidden
			data-locked-player-ids="<?php echo esc_attr( wp_json_encode( array_keys( $players_with_games ) ) ); ?>"
		></div>
		<?php
	}

	/**
	 * Not used - Pool overrides render_wrapper.
	 *
	 * {@inheritDoc}
	 */
	protected function render_results(): void {
		// Pool renders standings/games in render_wrapper instead.
	}

	/**
	 * Not used - Pool overrides render_wrapper.
	 *
	 * {@inheritDoc}
	 */
	protected function render_actions(): void {
		// Pool renders actions in render_manager_actions instead.
	}
}

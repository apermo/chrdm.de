<?php
/**
 * Phase 10 game renderer.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Phase 10 score card block.
 */
class Phase10_Renderer extends Game_Renderer {

	/**
	 * Game rounds.
	 *
	 * @var array
	 */
	protected array $rounds = array();

	/**
	 * Running totals per player per round.
	 *
	 * @var array
	 */
	protected array $running_totals = array();

	/**
	 * Final scores keyed by player ID.
	 *
	 * @var array
	 */
	protected array $final_scores = array();

	/**
	 * Current phase per player.
	 *
	 * @var array
	 */
	protected array $current_phases = array();

	/**
	 * Players who completed all 10 phases.
	 *
	 * @var array
	 */
	protected array $phase10_completers = array();

	/**
	 * Positions keyed by player ID.
	 *
	 * @var array
	 */
	protected array $positions = array();

	/**
	 * Current round number.
	 *
	 * @var int
	 */
	protected int $current_round = 0;

	/**
	 * {@inheritDoc}
	 */
	protected function get_css_prefix(): string {
		return 'asc-phase10';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_title(): string {
		return __( 'Phase 10', 'apermo-score-cards' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_display_class(): string {
		return 'asc-phase10';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function calculate_scores(): void {
		$this->rounds        = $this->game['rounds'] ?? array();
		$this->current_round = count( $this->rounds );

		foreach ( $this->player_ids as $pid ) {
			$this->running_totals[ $pid ] = array();
			$total                         = 0;
			$phase                         = 1;

			foreach ( $this->rounds as $round ) {
				$points = $round[ $pid ]['points'] ?? 0;
				$total += (int) $points;
				$this->running_totals[ $pid ][] = $total;

				if ( ! empty( $round[ $pid ]['phaseCompleted'] ) ) {
					$phase++;
				}
			}

			$this->final_scores[ $pid ]   = $total;
			$this->current_phases[ $pid ] = min( $phase, 10 );
		}

		// Find Phase 10 completers.
		foreach ( $this->player_ids as $pid ) {
			$completed_count = 0;
			foreach ( $this->rounds as $round ) {
				if ( ! empty( $round[ $pid ]['phaseCompleted'] ) ) {
					$completed_count++;
				}
			}
			if ( $completed_count >= 10 ) {
				$this->phase10_completers[ $pid ] = $this->final_scores[ $pid ];
			}
		}

		// Sort: completers first (by score asc), then others (by score asc).
		$sorted_players = $this->players;
		usort(
			$sorted_players,
			function ( $a, $b ) {
				$a_completed = isset( $this->phase10_completers[ $a['id'] ] );
				$b_completed = isset( $this->phase10_completers[ $b['id'] ] );

				if ( $a_completed && ! $b_completed ) {
					return -1;
				}
				if ( ! $a_completed && $b_completed ) {
					return 1;
				}

				return $this->final_scores[ $a['id'] ] <=> $this->final_scores[ $b['id'] ];
			}
		);

		// Calculate positions with tie handling.
		$current_position   = 1;
		$previous_score     = null;
		$previous_completed = null;

		foreach ( $sorted_players as $index => $player ) {
			$score     = $this->final_scores[ $player['id'] ];
			$completed = isset( $this->phase10_completers[ $player['id'] ] );

			if ( $score !== $previous_score || $completed !== $previous_completed ) {
				$current_position   = $index + 1;
				$previous_score     = $score;
				$previous_completed = $completed;
			}

			$this->positions[ $player['id'] ] = $current_position;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_results(): void {
		if ( empty( $this->rounds ) ) {
			return;
		}
		?>
		<div class="asc-phase10-display" data-player-count="<?php echo esc_attr( (string) count( $this->player_ids ) ); ?>">
			<p class="asc-phase10-display__progress">
				<?php
				printf(
					/* translators: %d: round number */
					esc_html__( 'Round %d', 'apermo-score-cards' ),
					$this->current_round
				);
				?>
			</p>

			<div class="asc-phase10-display__table-wrapper">
				<table class="asc-phase10-display__table">
					<thead>
						<tr>
							<th class="asc-phase10-display__round-col"></th>
							<?php foreach ( $this->player_ids as $pid ) :
								$player = $this->players_map[ $pid ] ?? null;
								if ( ! $player ) {
									continue;
								}
								?>
								<th class="asc-phase10-display__player-col">
									<div class="asc-phase10-display__player-header">
										<?php self::render_avatar( $player, 'asc-phase10-display__header-avatar' ); ?>
										<span class="asc-phase10-display__player-name"><?php echo esc_html( $player['name'] ); ?></span>
									</div>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php $this->render_phase_grid_row(); ?>
						<?php foreach ( $this->rounds as $round_index => $round ) : ?>
							<tr>
								<td class="asc-phase10-display__round-num">
									<?php echo esc_html( (string) ( $round_index + 1 ) ); ?>
								</td>
								<?php foreach ( $this->player_ids as $pid ) :
									$points         = $round[ $pid ]['points'] ?? 0;
									$phaseCompleted = $round[ $pid ]['phaseCompleted'] ?? false;
									$total          = $this->running_totals[ $pid ][ $round_index ] ?? 0;
									?>
									<td class="asc-phase10-display__score <?php echo $phaseCompleted ? 'asc-phase10-display__score--phase-completed' : ''; ?> <?php echo 0 === (int) $points ? 'asc-phase10-display__score--zero' : ''; ?>">
										<?php if ( $phaseCompleted ) : ?>
											<span class="asc-phase10-display__phase-completed-icon" title="<?php esc_attr_e( 'Phase completed', 'apermo-score-cards' ); ?>">✓</span>
										<?php endif; ?>
										<span class="asc-phase10-display__points"><?php echo esc_html( (string) $points ); ?></span>
										<span class="asc-phase10-display__total">(<?php echo esc_html( (string) $total ); ?>)</span>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="asc-phase10-display__total-row">
							<td class="asc-phase10-display__total-label"><?php esc_html_e( 'Total', 'apermo-score-cards' ); ?></td>
							<?php foreach ( $this->player_ids as $pid ) :
								$score         = $this->final_scores[ $pid ] ?? 0;
								$position      = $this->positions[ $pid ] ?? 0;
								$medal         = self::$medals[ $position ] ?? '';
								$finished_game = isset( $this->phase10_completers[ $pid ] );
								?>
								<td class="asc-phase10-display__total-score <?php echo $position <= 3 ? 'asc-phase10-display__total-score--position-' . $position : ''; ?> <?php echo $finished_game ? 'asc-phase10-display__total-score--finished' : ''; ?>">
									<?php if ( $medal ) : ?>
										<span class="asc-phase10-display__medal"><?php echo esc_html( $medal ); ?></span>
									<?php endif; ?>
									<strong><?php echo esc_html( (string) $score ); ?></strong>
								</td>
							<?php endforeach; ?>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the phase grid row showing completion status.
	 *
	 * @return void
	 */
	protected function render_phase_grid_row(): void {
		?>
		<tr class="asc-phase10-display__phase-row">
			<td class="asc-phase10-display__phase-label"><?php esc_html_e( 'Phase', 'apermo-score-cards' ); ?></td>
			<?php foreach ( $this->player_ids as $pid ) :
				$current_phase    = $this->current_phases[ $pid ] ?? 1;
				$completed_phases = array();
				$phase_count      = 0;
				foreach ( $this->rounds as $round ) {
					if ( ! empty( $round[ $pid ]['phaseCompleted'] ) ) {
						$phase_count++;
						$completed_phases[] = $phase_count;
					}
				}
				?>
				<td class="asc-phase10-display__phase-cell">
					<div class="asc-phase10-display__phase-grid">
						<?php for ( $phase = 1; $phase <= 10; $phase++ ) :
							$is_completed = in_array( $phase, $completed_phases, true );
							$is_current   = ( $phase === $current_phase ) && ! $is_completed;
							$phase_class  = 'asc-phase10-display__phase';
							if ( $is_completed ) {
								$phase_class .= ' asc-phase10-display__phase--completed';
							}
							if ( $is_current ) {
								$phase_class .= ' asc-phase10-display__phase--current';
							}
							?>
							<span class="<?php echo esc_attr( $phase_class ); ?>"><?php echo esc_html( (string) $phase ); ?></span>
						<?php endfor; ?>
					</div>
				</td>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_actions(): void {
		if ( 'completed' === $this->status ) {
			?>
			<div class="asc-phase10__actions">
				<?php $this->render_new_game_button(); ?>
			</div>
			<?php
			return;
		}
		?>
		<div class="asc-phase10__actions">
			<button type="button" class="asc-phase10__add-round-btn">
				<?php esc_html_e( 'Add Round', 'apermo-score-cards' ); ?>
			</button>
			<?php if ( $this->current_round > 0 ) : ?>
				<button type="button" class="asc-phase10__edit-round-btn" data-round="<?php echo esc_attr( (string) ( $this->current_round - 1 ) ); ?>">
					<?php esc_html_e( 'Edit Last Round', 'apermo-score-cards' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="asc-phase10__complete-btn">
				<?php esc_html_e( 'Complete Game', 'apermo-score-cards' ); ?>
			</button>
		</div>
		<?php
	}
}

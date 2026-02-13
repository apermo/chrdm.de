<?php
/**
 * Wizard game renderer.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Wizard score card block.
 */
class Wizard_Renderer extends Game_Renderer {

	/**
	 * Total number of rounds.
	 *
	 * @var int
	 */
	protected int $total_rounds = 0;

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
	 * Completed round count.
	 *
	 * @var int
	 */
	protected int $completed_rounds = 0;

	/**
	 * Whether the last round is incomplete.
	 *
	 * @var bool
	 */
	protected bool $has_incomplete_round = false;

	/**
	 * {@inheritDoc}
	 */
	protected function get_css_prefix(): string {
		return 'asc-wizard';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_title(): string {
		return __( 'Wizard', 'apermo-score-cards' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_display_class(): string {
		return 'asc-wizard';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_extra_data_attributes(): array {
		return array(
			'data-total-rounds' => (string) $this->total_rounds,
		);
	}

	/**
	 * Calculate score for a single round.
	 *
	 * @param int $effective_bid Effective bid (with werewolf adjustment).
	 * @param int $won           Tricks won.
	 * @return int Round score.
	 */
	public static function calculate_round_score( int $effective_bid, int $won ): int {
		if ( $effective_bid === $won ) {
			return 20 + ( $won * 10 );
		}
		return -10 * abs( $effective_bid - $won );
	}

	/**
	 * Get effective bid for a player (including werewolf adjustment).
	 *
	 * @param array $round     Round data.
	 * @param int   $player_id Player ID.
	 * @return int Effective bid.
	 */
	public static function get_effective_bid( array $round, int $player_id ): int {
		$data = $round[ $player_id ] ?? null;
		if ( ! $data || ! isset( $data['bid'] ) ) {
			return 0;
		}
		$base_bid = (int) $data['bid'];
		$meta     = $round['_meta'] ?? null;
		if ( $meta && isset( $meta['werewolfPlayerId'] ) && (int) $meta['werewolfPlayerId'] === $player_id ) {
			return $base_bid + (int) ( $meta['werewolfAdjustment'] ?? 0 );
		}
		return $base_bid;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function calculate_scores(): void {
		$player_count      = count( $this->players );
		$this->total_rounds = ( $player_count >= 3 && $player_count <= 6 ) ? intdiv( 60, $player_count ) : 0;
		$this->rounds       = $this->game['rounds'] ?? array();
		$this->current_round = count( $this->rounds );

		// Calculate running totals and final scores.
		foreach ( $this->player_ids as $pid ) {
			$this->running_totals[ $pid ] = array();
			$total                         = 0;

			foreach ( $this->rounds as $round ) {
				$data = $round[ $pid ] ?? null;
				if ( $data && isset( $data['bid'], $data['won'] ) && '' !== $data['won'] && null !== $data['won'] ) {
					$effective_bid = self::get_effective_bid( $round, $pid );
					$total        += self::calculate_round_score( $effective_bid, (int) $data['won'] );
				}
				$this->running_totals[ $pid ][] = $total;
			}

			$this->final_scores[ $pid ] = $total;
		}

		// Sort players by final score (descending).
		$sorted_players = $this->players;
		usort(
			$sorted_players,
			function ( $a, $b ) {
				return $this->final_scores[ $b['id'] ] <=> $this->final_scores[ $a['id'] ];
			}
		);

		$this->positions = self::calculate_positions_higher_wins( $sorted_players, $this->final_scores );

		// Check if last round is incomplete.
		if ( ! empty( $this->rounds ) ) {
			$last_round = end( $this->rounds );
			foreach ( $this->player_ids as $pid ) {
				$data = $last_round[ $pid ] ?? null;
				if ( $data && isset( $data['bid'] ) && '' !== $data['bid']
					&& ( ! isset( $data['won'] ) || '' === $data['won'] || null === $data['won'] ) ) {
					$this->has_incomplete_round = true;
					break;
				}
			}
		}

		$this->completed_rounds = $this->has_incomplete_round
			? $this->current_round - 1
			: $this->current_round;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_header_meta(): void {
		?>
		<span class="asc-wizard__rounds">
			<?php
			printf(
				/* translators: %d: total rounds */
				esc_html__( '%d rounds', 'apermo-score-cards' ),
				$this->total_rounds
			);
			?>
		</span>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_results(): void {
		if ( empty( $this->rounds ) ) {
			return;
		}
		?>
		<div class="asc-wizard-display">
			<p class="asc-wizard-display__progress">
				<?php
				if ( $this->has_incomplete_round ) {
					printf(
						/* translators: 1: current round, 2: total rounds */
						esc_html__( 'Round %1$d / %2$d (in progress)', 'apermo-score-cards' ),
						$this->current_round,
						$this->total_rounds
					);
				} else {
					printf(
						/* translators: 1: current round, 2: total rounds */
						esc_html__( 'Round %1$d / %2$d', 'apermo-score-cards' ),
						$this->completed_rounds,
						$this->total_rounds
					);
				}
				?>
			</p>

			<div class="asc-wizard-display__table-wrapper">
				<table class="asc-wizard-display__table asc-wizard-display__table--full">
					<thead>
						<tr>
							<th class="asc-wizard-display__round-col"><?php esc_html_e( 'Round', 'apermo-score-cards' ); ?></th>
							<?php foreach ( $this->player_ids as $pid ) :
								$player = $this->players_map[ $pid ] ?? null;
								if ( ! $player ) {
									continue;
								}
								?>
								<th class="asc-wizard-display__player-header" colspan="3">
									<?php self::render_avatar( $player, 'asc-wizard-display__header-avatar' ); ?>
									<span><?php echo esc_html( $player['name'] ); ?></span>
								</th>
							<?php endforeach; ?>
						</tr>
						<tr class="asc-wizard-display__subheader">
							<th></th>
							<?php foreach ( $this->player_ids as $pid ) : ?>
								<th class="asc-wizard-display__bid-col"><?php esc_html_e( 'Bid', 'apermo-score-cards' ); ?></th>
								<th class="asc-wizard-display__won-col"><?php esc_html_e( 'Won', 'apermo-score-cards' ); ?></th>
								<th class="asc-wizard-display__score-col"><?php esc_html_e( 'Pts', 'apermo-score-cards' ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->rounds as $round_index => $round ) :
							$is_incomplete = $this->is_round_incomplete( $round );
							?>
							<tr class="<?php echo $is_incomplete ? 'asc-wizard-display__row--incomplete' : ''; ?>">
								<td class="asc-wizard-display__round-num">
									<?php echo esc_html( (string) ( $round_index + 1 ) ); ?>
									<?php if ( $is_incomplete ) : ?>
										<span class="asc-wizard-display__in-progress" title="<?php esc_attr_e( 'In progress', 'apermo-score-cards' ); ?>">⏳</span>
									<?php endif; ?>
								</td>
								<?php $this->render_round_cells( $round ); ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="asc-wizard-display__total-row">
							<td class="asc-wizard-display__total-label"><?php esc_html_e( 'Total', 'apermo-score-cards' ); ?></td>
							<?php foreach ( $this->player_ids as $pid ) :
								$score    = $this->final_scores[ $pid ] ?? 0;
								$position = $this->positions[ $pid ] ?? 0;
								$medal    = self::$medals[ $position ] ?? '';
								?>
								<td colspan="3" class="asc-wizard-display__total-score <?php echo $position <= 3 ? 'asc-wizard-display__total-score--position-' . $position : ''; ?>">
									<?php if ( $medal ) : ?>
										<span class="asc-wizard-display__medal"><?php echo esc_html( $medal ); ?></span>
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
	 * Check if a round is incomplete.
	 *
	 * @param array $round Round data.
	 * @return bool Whether the round is incomplete.
	 */
	protected function is_round_incomplete( array $round ): bool {
		foreach ( $this->player_ids as $pid ) {
			$data = $round[ $pid ] ?? null;
			if ( $data && isset( $data['bid'] ) && '' !== $data['bid']
				&& ( ! isset( $data['won'] ) || '' === $data['won'] || null === $data['won'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render the cells for a single round row.
	 *
	 * @param array $round Round data.
	 * @return void
	 */
	protected function render_round_cells( array $round ): void {
		foreach ( $this->player_ids as $pid ) :
			$data          = $round[ $pid ] ?? null;
			$bid           = $data['bid'] ?? null;
			$won           = $data['won'] ?? null;
			$meta          = $round['_meta'] ?? null;
			$has_werewolf  = $meta && isset( $meta['werewolfPlayerId'] ) && (int) $meta['werewolfPlayerId'] === $pid;
			$adjustment    = $has_werewolf ? (int) ( $meta['werewolfAdjustment'] ?? 0 ) : 0;
			$effective_bid = null !== $bid && '' !== $bid ? ( (int) $bid + $adjustment ) : null;
			$won_valid     = null !== $won && '' !== $won;
			$score         = ( null !== $effective_bid && $won_valid )
				? self::calculate_round_score( $effective_bid, (int) $won )
				: null;
			$is_correct    = null !== $effective_bid && $won_valid && $effective_bid === (int) $won;
			?>
			<td class="asc-wizard-display__bid">
				<?php if ( null !== $bid ) : ?>
					<?php echo esc_html( (string) $bid ); ?>
					<?php if ( $has_werewolf && 0 !== $adjustment ) : ?>
						<sup class="asc-wizard-display__werewolf-indicator"><?php echo $adjustment > 0 ? '+' : ''; ?><?php echo esc_html( (string) $adjustment ); ?></sup>
					<?php endif; ?>
				<?php else : ?>
					-
				<?php endif; ?>
			</td>
			<td class="asc-wizard-display__won <?php echo $is_correct ? 'asc-wizard-display__won--correct' : ''; ?>">
				<?php echo $won_valid ? esc_html( (string) $won ) : '-'; ?>
			</td>
			<td class="asc-wizard-display__pts <?php echo null !== $score && $score < 0 ? 'asc-wizard-display__pts--negative' : ''; ?>">
				<?php echo null !== $score ? esc_html( (string) $score ) : '-'; ?>
			</td>
		<?php endforeach;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_actions(): void {
		if ( 'completed' === $this->status ) {
			return;
		}
		?>
		<div class="asc-wizard__actions">
			<?php if ( $this->has_incomplete_round ) : ?>
				<button type="button" class="asc-wizard__add-round-btn asc-wizard__add-round-btn--continue">
					<?php esc_html_e( 'Continue Round', 'apermo-score-cards' ); ?>
				</button>
			<?php elseif ( $this->current_round < $this->total_rounds ) : ?>
				<button type="button" class="asc-wizard__add-round-btn">
					<?php esc_html_e( 'Add Round', 'apermo-score-cards' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( ! $this->has_incomplete_round ) : ?>
				<button type="button" class="asc-wizard__edit-round-btn" data-round="<?php echo esc_attr( (string) ( $this->current_round - 1 ) ); ?>">
					<?php esc_html_e( 'Edit Last Round', 'apermo-score-cards' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( 'completed' !== $this->status && $this->completed_rounds >= $this->total_rounds ) : ?>
				<button type="button" class="asc-wizard__complete-btn">
					<?php esc_html_e( 'Complete Game', 'apermo-score-cards' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}
}

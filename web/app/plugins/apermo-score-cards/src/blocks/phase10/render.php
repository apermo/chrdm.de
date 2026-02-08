<?php
/**
 * Phase 10 Score Card - Server-side render
 *
 * @package ApermoScoreCards
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$block_id     = $attributes['blockId'] ?? '';
$player_ids   = $attributes['playerIds'] ?? array();
$custom_title = $attributes['customTitle'] ?? '';
$post_id      = $block->context['postId'] ?? get_the_ID();

if ( empty( $block_id ) || empty( $player_ids ) ) {
	return;
}

// Get players.
$players = Players::get_by_ids( $player_ids );

if ( empty( $players ) ) {
	return;
}

// Check if current user can manage.
$can_manage = Capabilities::user_can_manage( (int) $post_id );

// Get game data.
$game = Games::get( (int) $post_id, $block_id );

$show_form = ! $game && $can_manage;
$can_edit  = $game && $can_manage;

$rounds = $game['rounds'] ?? array();
$status = $game['status'] ?? 'pending';

// Calculate running totals, final scores, and current phases.
$running_totals = array();
$final_scores   = array();
$current_phases = array();

foreach ( $player_ids as $pid ) {
	$running_totals[ $pid ] = array();
	$total                  = 0;
	$phase                  = 1;

	foreach ( $rounds as $round ) {
		$points = $round[ $pid ]['points'] ?? 0;
		$total += (int) $points;
		$running_totals[ $pid ][] = $total;

		// Track phase completions.
		if ( ! empty( $round[ $pid ]['phaseCompleted'] ) ) {
			$phase++;
		}
	}

	$final_scores[ $pid ]   = $total;
	$current_phases[ $pid ] = min( $phase, 10 );
}

// Determine winner: must have completed Phase 10 (current phase would be 10 after completing it).
// A player who completed phase 10 has current_phases = 10 (capped) but will have phaseCompleted on their last phase.
$phase10_completers = array();
foreach ( $player_ids as $pid ) {
	// Count how many phases completed.
	$completed_count = 0;
	foreach ( $rounds as $round ) {
		if ( ! empty( $round[ $pid ]['phaseCompleted'] ) ) {
			$completed_count++;
		}
	}
	// If completed 10 phases, they finished Phase 10.
	if ( $completed_count >= 10 ) {
		$phase10_completers[ $pid ] = $final_scores[ $pid ];
	}
}

// Sort players for ranking.
// Phase 10 completers first (sorted by score), then others (sorted by score).
$sorted_players = $players;
usort(
	$sorted_players,
	function ( $a, $b ) use ( $final_scores, $phase10_completers ) {
		$a_completed = isset( $phase10_completers[ $a['id'] ] );
		$b_completed = isset( $phase10_completers[ $b['id'] ] );

		// Completers rank higher than non-completers.
		if ( $a_completed && ! $b_completed ) {
			return -1;
		}
		if ( ! $a_completed && $b_completed ) {
			return 1;
		}

		// Among same completion status, sort by score (ascending).
		return $final_scores[ $a['id'] ] <=> $final_scores[ $b['id'] ];
	}
);

// Calculate positions with tie handling.
$positions = array();
$medals    = array( 1 => '🥇', 2 => '🥈', 3 => '🥉' );

$current_position = 1;
$previous_score   = null;
$previous_completed = null;

foreach ( $sorted_players as $index => $player ) {
	$score     = $final_scores[ $player['id'] ];
	$completed = isset( $phase10_completers[ $player['id'] ] );

	if ( $score !== $previous_score || $completed !== $previous_completed ) {
		$current_position   = $index + 1;
		$previous_score     = $score;
		$previous_completed = $completed;
	}

	$positions[ $player['id'] ] = $current_position;
}

$current_round = count( $rounds );

// Create player map for quick lookup.
$players_map = array();
foreach ( $players as $player ) {
	$players_map[ $player['id'] ] = $player;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'           => 'asc-phase10',
		'data-post-id'    => $post_id,
		'data-block-id'   => $block_id,
		'data-can-manage' => $can_manage ? 'true' : 'false',
		'data-player-ids' => wp_json_encode( $player_ids ),
		'data-players'    => wp_json_encode( $players ),
		'data-game'       => $game ? wp_json_encode( $game ) : '',
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="asc-phase10__header">
		<h3 class="asc-phase10__title">
			<?php echo esc_html( $custom_title ?: __( 'Phase 10', 'apermo-score-cards' ) ); ?>
		</h3>
		<?php if ( 'completed' === $status ) : ?>
			<span class="asc-phase10__status asc-phase10__status--completed">
				<?php esc_html_e( 'Completed', 'apermo-score-cards' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $game && ! empty( $rounds ) ) : ?>
		<!-- Game results display -->
		<div class="asc-phase10-display" data-player-count="<?php echo esc_attr( count( $player_ids ) ); ?>">
			<p class="asc-phase10-display__progress">
				<?php
				printf(
					/* translators: %d: round number */
					esc_html__( 'Round %d', 'apermo-score-cards' ),
					$current_round
				);
				?>
			</p>

			<div class="asc-phase10-display__table-wrapper">
				<table class="asc-phase10-display__table">
					<thead>
						<tr>
							<th class="asc-phase10-display__round-col"></th>
							<?php foreach ( $player_ids as $pid ) :
								$player = $players_map[ $pid ] ?? null;
								if ( ! $player ) {
									continue;
								}
								?>
								<th class="asc-phase10-display__player-col">
									<div class="asc-phase10-display__player-header">
										<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
											<img
												src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
												alt=""
												class="asc-phase10-display__header-avatar"
											/>
										<?php endif; ?>
										<span class="asc-phase10-display__player-name"><?php echo esc_html( $player['name'] ); ?></span>
									</div>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<!-- Phase grid row -->
						<tr class="asc-phase10-display__phase-row">
							<td class="asc-phase10-display__phase-label"><?php esc_html_e( 'Phase', 'apermo-score-cards' ); ?></td>
							<?php foreach ( $player_ids as $pid ) :
								$current_phase = $current_phases[ $pid ] ?? 1;
								// Calculate completed phases for this player.
								$completed_phases = array();
								$phase_count = 0;
								foreach ( $rounds as $round ) {
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
											<span class="<?php echo esc_attr( $phase_class ); ?>"><?php echo esc_html( $phase ); ?></span>
										<?php endfor; ?>
									</div>
								</td>
							<?php endforeach; ?>
						</tr>
						<!-- Round score rows -->
						<?php foreach ( $rounds as $round_index => $round ) : ?>
							<tr>
								<td class="asc-phase10-display__round-num">
									<?php echo esc_html( $round_index + 1 ); ?>
								</td>
								<?php foreach ( $player_ids as $pid ) :
									$points         = $round[ $pid ]['points'] ?? 0;
									$phaseCompleted = $round[ $pid ]['phaseCompleted'] ?? false;
									$total          = $running_totals[ $pid ][ $round_index ] ?? 0;
									?>
									<td class="asc-phase10-display__score <?php echo $phaseCompleted ? 'asc-phase10-display__score--phase-completed' : ''; ?> <?php echo 0 === (int) $points ? 'asc-phase10-display__score--zero' : ''; ?>">
										<?php if ( $phaseCompleted ) : ?>
											<span class="asc-phase10-display__phase-completed-icon" title="<?php esc_attr_e( 'Phase completed', 'apermo-score-cards' ); ?>">✓</span>
										<?php endif; ?>
										<span class="asc-phase10-display__points"><?php echo esc_html( $points ); ?></span>
										<span class="asc-phase10-display__total">(<?php echo esc_html( $total ); ?>)</span>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="asc-phase10-display__total-row">
							<td class="asc-phase10-display__total-label"><?php esc_html_e( 'Total', 'apermo-score-cards' ); ?></td>
							<?php foreach ( $player_ids as $pid ) :
								$score          = $final_scores[ $pid ] ?? 0;
								$position       = $positions[ $pid ] ?? 0;
								$medal          = $medals[ $position ] ?? '';
								$finished_game  = isset( $phase10_completers[ $pid ] );
								?>
								<td class="asc-phase10-display__total-score <?php echo $position <= 3 ? 'asc-phase10-display__total-score--position-' . $position : ''; ?> <?php echo $finished_game ? 'asc-phase10-display__total-score--finished' : ''; ?>">
									<?php if ( $medal ) : ?>
										<span class="asc-phase10-display__medal"><?php echo esc_html( $medal ); ?></span>
									<?php endif; ?>
									<strong><?php echo esc_html( $score ); ?></strong>
								</td>
							<?php endforeach; ?>
						</tr>
					</tfoot>
				</table>
			</div>

			<?php if ( $can_edit && 'completed' !== $status ) : ?>
				<div class="asc-phase10__actions">
					<button type="button" class="asc-phase10__add-round-btn">
						<?php esc_html_e( 'Add Round', 'apermo-score-cards' ); ?>
					</button>
					<?php if ( $current_round > 0 ) : ?>
						<button type="button" class="asc-phase10__edit-round-btn" data-round="<?php echo esc_attr( $current_round - 1 ); ?>">
							<?php esc_html_e( 'Edit Last Round', 'apermo-score-cards' ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="asc-phase10__complete-btn">
						<?php esc_html_e( 'Complete Game', 'apermo-score-cards' ); ?>
					</button>
				</div>
				<div class="asc-phase10-form-container" hidden></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_form ) : ?>
		<!-- Score form container - hydrated by frontend JS -->
		<div class="asc-phase10-form-container"></div>
		<?php if ( $can_manage ) : ?>
			<div class="asc-phase10__player-actions">
				<button type="button" class="asc-phase10__edit-players-btn">
					<?php esc_html_e( 'Edit Players', 'apermo-score-cards' ); ?>
				</button>
			</div>
			<div class="asc-player-selector-container" hidden></div>
		<?php endif; ?>
	<?php elseif ( ! $game ) : ?>
		<!-- No game data and user cannot manage -->
		<div class="asc-phase10__pending">
			<p><?php esc_html_e( 'Waiting for scores...', 'apermo-score-cards' ); ?></p>
			<table class="asc-phase10-display__table">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $players as $index => $player ) : ?>
						<tr>
							<td><?php echo esc_html( $index + 1 ); ?></td>
							<td class="asc-phase10-display__player">
								<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
									<img
										src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
										alt=""
										class="asc-phase10-display__avatar"
									/>
								<?php endif; ?>
								<span><?php echo esc_html( $player['name'] ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

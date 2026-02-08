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

// Calculate running totals and final scores.
$running_totals = array();
$final_scores   = array();

foreach ( $player_ids as $pid ) {
	$running_totals[ $pid ] = array();
	$total                  = 0;

	foreach ( $rounds as $round ) {
		$points = $round[ $pid ]['points'] ?? 0;
		$total += (int) $points;
		$running_totals[ $pid ][] = $total;
	}

	$final_scores[ $pid ] = $total;
}

// Sort players by final score (ascending - lowest points wins).
$sorted_players = $players;
usort(
	$sorted_players,
	function ( $a, $b ) use ( $final_scores ) {
		return $final_scores[ $a['id'] ] <=> $final_scores[ $b['id'] ];
	}
);

// Calculate positions with tie handling.
$positions = array();
$medals    = array( 1 => '🥇', 2 => '🥈', 3 => '🥉' );

$current_position = 1;
$previous_score   = null;

foreach ( $sorted_players as $index => $player ) {
	$score = $final_scores[ $player['id'] ];

	if ( $score !== $previous_score ) {
		$current_position = $index + 1;
		$previous_score   = $score;
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
		<div class="asc-phase10-display">
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
							<th class="asc-phase10-display__round-col"><?php esc_html_e( 'Round', 'apermo-score-cards' ); ?></th>
							<?php foreach ( $player_ids as $pid ) :
								$player = $players_map[ $pid ] ?? null;
								if ( ! $player ) {
									continue;
								}
								?>
								<th class="asc-phase10-display__player-header">
									<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
										<img
											src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
											alt=""
											class="asc-phase10-display__header-avatar"
										/>
									<?php endif; ?>
									<span><?php echo esc_html( $player['name'] ); ?></span>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rounds as $round_index => $round ) : ?>
							<tr>
								<td class="asc-phase10-display__round-num">
									<?php echo esc_html( $round_index + 1 ); ?>
								</td>
								<?php foreach ( $player_ids as $pid ) :
									$points   = $round[ $pid ]['points'] ?? 0;
									$finished = $round[ $pid ]['finished'] ?? false;
									$total    = $running_totals[ $pid ][ $round_index ] ?? 0;
									?>
									<td class="asc-phase10-display__score <?php echo $finished ? 'asc-phase10-display__score--finished' : ''; ?> <?php echo 0 === (int) $points && ! $finished ? 'asc-phase10-display__score--zero' : ''; ?>">
										<?php if ( $finished ) : ?>
											<span class="asc-phase10-display__finished-icon" title="<?php esc_attr_e( 'Phase completed', 'apermo-score-cards' ); ?>">✓</span>
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
								$score    = $final_scores[ $pid ] ?? 0;
								$position = $positions[ $pid ] ?? 0;
								$medal    = $medals[ $position ] ?? '';
								?>
								<td class="asc-phase10-display__total-score <?php echo $position <= 3 ? 'asc-phase10-display__total-score--position-' . $position : ''; ?>">
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

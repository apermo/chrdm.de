<?php
/**
 * Wizard Score Card - Server-side render
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

$player_count = count( $players );
$total_rounds = ( $player_count >= 3 && $player_count <= 6 ) ? intdiv( 60, $player_count ) : 0;

// Check if current user can manage.
$can_manage = Capabilities::user_can_manage( (int) $post_id );

// Get game data.
$game = Games::get( (int) $post_id, $block_id );

$show_form = ! $game && $can_manage;
$can_edit  = $game && $can_manage;

$rounds  = $game['rounds'] ?? array();
$status  = $game['status'] ?? 'pending';

/**
 * Calculate score for a single round.
 *
 * @param int $bid Tricks bid.
 * @param int $won Tricks won.
 * @return int Round score.
 */
function calculate_wizard_round_score( int $bid, int $won ): int {
	if ( $bid === $won ) {
		return 20 + ( $won * 10 );
	}
	return -10 * abs( $bid - $won );
}

// Calculate running totals and final scores.
$running_totals = array();
$final_scores   = array();

foreach ( $player_ids as $pid ) {
	$running_totals[ $pid ] = array();
	$total                  = 0;

	foreach ( $rounds as $round ) {
		$data = $round[ $pid ] ?? null;
		if ( $data && isset( $data['bid'], $data['won'] ) ) {
			$total += calculate_wizard_round_score( (int) $data['bid'], (int) $data['won'] );
		}
		$running_totals[ $pid ][] = $total;
	}

	$final_scores[ $pid ] = $total;
}

// Sort players by final score (descending).
$sorted_players = $players;
usort(
	$sorted_players,
	function ( $a, $b ) use ( $final_scores ) {
		return $final_scores[ $b['id'] ] <=> $final_scores[ $a['id'] ];
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
		'class'            => 'asc-wizard',
		'data-post-id'     => $post_id,
		'data-block-id'    => $block_id,
		'data-total-rounds' => $total_rounds,
		'data-can-manage'  => $can_manage ? 'true' : 'false',
		'data-player-ids'  => wp_json_encode( $player_ids ),
		'data-players'     => wp_json_encode( $players ),
		'data-game'        => $game ? wp_json_encode( $game ) : '',
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="asc-wizard__header">
		<h3 class="asc-wizard__title">
			<?php echo esc_html( $custom_title ?: __( 'Wizard', 'apermo-score-cards' ) ); ?>
		</h3>
		<span class="asc-wizard__rounds">
			<?php
			printf(
				/* translators: %d: total rounds */
				esc_html__( '%d rounds', 'apermo-score-cards' ),
				$total_rounds
			);
			?>
		</span>
		<?php if ( 'completed' === $status ) : ?>
			<span class="asc-wizard__status asc-wizard__status--completed">
				<?php esc_html_e( 'Completed', 'apermo-score-cards' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $game && ! empty( $rounds ) ) : ?>
		<!-- Game results display -->
		<div class="asc-wizard-display">
			<p class="asc-wizard-display__progress">
				<?php
				printf(
					/* translators: 1: current round, 2: total rounds */
					esc_html__( 'Round %1$d / %2$d', 'apermo-score-cards' ),
					$current_round,
					$total_rounds
				);
				?>
			</p>

			<div class="asc-wizard-display__table-wrapper">
				<table class="asc-wizard-display__table asc-wizard-display__table--full">
					<thead>
						<tr>
							<th class="asc-wizard-display__round-col"><?php esc_html_e( 'Round', 'apermo-score-cards' ); ?></th>
							<?php foreach ( $player_ids as $pid ) :
								$player = $players_map[ $pid ] ?? null;
								if ( ! $player ) {
									continue;
								}
								?>
								<th class="asc-wizard-display__player-header" colspan="3">
									<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
										<img
											src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
											alt=""
											class="asc-wizard-display__header-avatar"
										/>
									<?php endif; ?>
									<span><?php echo esc_html( $player['name'] ); ?></span>
								</th>
							<?php endforeach; ?>
						</tr>
						<tr class="asc-wizard-display__subheader">
							<th></th>
							<?php foreach ( $player_ids as $pid ) : ?>
								<th class="asc-wizard-display__bid-col"><?php esc_html_e( 'Bid', 'apermo-score-cards' ); ?></th>
								<th class="asc-wizard-display__won-col"><?php esc_html_e( 'Won', 'apermo-score-cards' ); ?></th>
								<th class="asc-wizard-display__score-col"><?php esc_html_e( 'Pts', 'apermo-score-cards' ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rounds as $round_index => $round ) : ?>
							<tr>
								<td class="asc-wizard-display__round-num"><?php echo esc_html( $round_index + 1 ); ?></td>
								<?php foreach ( $player_ids as $pid ) :
									$data       = $round[ $pid ] ?? null;
									$bid        = $data['bid'] ?? null;
									$won        = $data['won'] ?? null;
									$score      = ( null !== $bid && null !== $won )
										? calculate_wizard_round_score( (int) $bid, (int) $won )
										: null;
									$is_correct = null !== $bid && $bid === $won;
									?>
									<td class="asc-wizard-display__bid"><?php echo null !== $bid ? esc_html( $bid ) : '-'; ?></td>
									<td class="asc-wizard-display__won <?php echo $is_correct ? 'asc-wizard-display__won--correct' : ''; ?>">
										<?php echo null !== $won ? esc_html( $won ) : '-'; ?>
									</td>
									<td class="asc-wizard-display__pts <?php echo null !== $score && $score < 0 ? 'asc-wizard-display__pts--negative' : ''; ?>">
										<?php echo null !== $score ? esc_html( $score ) : '-'; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr class="asc-wizard-display__total-row">
							<td class="asc-wizard-display__total-label"><?php esc_html_e( 'Total', 'apermo-score-cards' ); ?></td>
							<?php foreach ( $player_ids as $pid ) :
								$score    = $final_scores[ $pid ] ?? 0;
								$position = $positions[ $pid ] ?? 0;
								$medal    = $medals[ $position ] ?? '';
								?>
								<td colspan="3" class="asc-wizard-display__total-score <?php echo $position <= 3 ? 'asc-wizard-display__total-score--position-' . $position : ''; ?>">
									<?php if ( $medal ) : ?>
										<span class="asc-wizard-display__medal"><?php echo esc_html( $medal ); ?></span>
									<?php endif; ?>
									<strong><?php echo esc_html( $score ); ?></strong>
								</td>
							<?php endforeach; ?>
						</tr>
					</tfoot>
				</table>
			</div>

			<?php if ( $can_edit ) : ?>
				<div class="asc-wizard__actions">
					<?php if ( $current_round < $total_rounds ) : ?>
						<button type="button" class="asc-wizard__add-round-btn">
							<?php esc_html_e( 'Add Round', 'apermo-score-cards' ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="asc-wizard__edit-round-btn" data-round="<?php echo esc_attr( $current_round - 1 ); ?>">
						<?php esc_html_e( 'Edit Last Round', 'apermo-score-cards' ); ?>
					</button>
					<?php if ( 'completed' !== $status && $current_round >= $total_rounds ) : ?>
						<button type="button" class="asc-wizard__complete-btn">
							<?php esc_html_e( 'Complete Game', 'apermo-score-cards' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<div class="asc-wizard-form-container" hidden></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_form ) : ?>
		<!-- Score form container - hydrated by frontend JS -->
		<div class="asc-wizard-form-container"></div>
		<?php if ( $can_manage ) : ?>
			<div class="asc-wizard__player-actions">
				<button type="button" class="asc-wizard__edit-players-btn">
					<?php esc_html_e( 'Edit Players', 'apermo-score-cards' ); ?>
				</button>
			</div>
			<div class="asc-player-selector-container" hidden></div>
		<?php endif; ?>
	<?php elseif ( ! $game ) : ?>
		<!-- No game data and user cannot manage -->
		<div class="asc-wizard__pending">
			<p><?php esc_html_e( 'Waiting for scores...', 'apermo-score-cards' ); ?></p>
			<table class="asc-wizard-display__table">
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
							<td class="asc-wizard-display__player">
								<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
									<img
										src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
										alt=""
										class="asc-wizard-display__avatar"
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

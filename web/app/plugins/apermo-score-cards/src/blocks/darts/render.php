<?php
/**
 * Darts Score Card - Server-side render
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

$block_id       = $attributes['blockId'] ?? '';
$player_ids     = $attributes['playerIds'] ?? array();
$starting_score = $attributes['startingScore'] ?? 501;
$post_id        = $block->context['postId'] ?? get_the_ID();

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

// Show form if user can manage (for both new and editing existing)
$show_form = $can_manage;

$scores        = $game['scores'] ?? array();
$status        = $game['status'] ?? 'pending';
$finished_round = $game['finishedRound'] ?? null;

// Sort players by ranking (only if game exists).
if ( $game && ! empty( $scores ) ) {
	usort(
		$players,
		function ( $a, $b ) use ( $scores ) {
			$score_a = $scores[ $a['id'] ]['finalScore'] ?? PHP_INT_MAX;
			$score_b = $scores[ $b['id'] ]['finalScore'] ?? PHP_INT_MAX;

			// Lower score is better
			return $score_a <=> $score_b;
		}
	);
}

// Calculate positions with tie handling
$positions = array();
$medals    = array( 1 => '🥇', 2 => '🥈', 3 => '🥉' );

if ( $game && ! empty( $scores ) ) {
	$current_position = 1;
	$previous_score   = null;
	$players_at_score = 0;

	foreach ( $players as $index => $player ) {
		$player_score = $scores[ $player['id'] ]['finalScore'] ?? null;

		if ( $player_score !== $previous_score ) {
			$current_position = $index + 1;
			$previous_score   = $player_score;
		}

		$positions[ $player['id'] ] = $current_position;
	}
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'              => 'asc-darts',
		'data-post-id'       => $post_id,
		'data-block-id'      => $block_id,
		'data-starting-score' => $starting_score,
		'data-can-manage'    => $can_manage ? 'true' : 'false',
		'data-player-ids'    => wp_json_encode( $player_ids ),
		'data-players'       => wp_json_encode( $players ),
		'data-game'          => $game ? wp_json_encode( $game ) : '',
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="asc-darts__header">
		<h3 class="asc-darts__title">
			<?php
			printf(
				/* translators: %d: starting score */
				esc_html__( 'Darts – %d', 'apermo-score-cards' ),
				$starting_score
			);
			?>
		</h3>
		<?php if ( 'completed' === $status ) : ?>
			<span class="asc-darts__status asc-darts__status--completed">
				<?php esc_html_e( 'Completed', 'apermo-score-cards' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $game && ! empty( $scores ) ) : ?>
		<!-- Game results display -->
		<div class="asc-darts-display">
			<table class="asc-darts-display__table">
				<thead>
					<tr>
						<th class="asc-darts-display__rank-header">#</th>
						<th><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
						<th><?php esc_html_e( 'Remaining', 'apermo-score-cards' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $players as $player ) :
						$player_score = $scores[ $player['id'] ]['finalScore'] ?? null;
						$position     = $positions[ $player['id'] ] ?? 0;
						$medal        = $medals[ $position ] ?? '';
						$is_finished  = 0 === $player_score;

						$row_classes = array( 'asc-darts-display__row' );
						if ( $position <= 3 ) {
							$row_classes[] = 'asc-darts-display__row--podium';
							$row_classes[] = 'asc-darts-display__row--position-' . $position;
						}
						if ( $is_finished ) {
							$row_classes[] = 'asc-darts-display__row--finished';
						}
						?>
						<tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
							<td class="asc-darts-display__rank">
								<?php if ( $medal ) : ?>
									<span class="asc-darts-display__medal"><?php echo esc_html( $medal ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $position ); ?>
								<?php endif; ?>
							</td>
							<td class="asc-darts-display__player">
								<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
									<img
										src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
										alt=""
										class="asc-darts-display__avatar"
									/>
								<?php endif; ?>
								<span class="asc-darts-display__name">
									<?php echo esc_html( $player['name'] ); ?>
								</span>
							</td>
							<td class="asc-darts-display__score <?php echo $is_finished ? 'asc-darts-display__score--zero' : ''; ?>">
								<?php echo null !== $player_score ? esc_html( $player_score ) : '-'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $finished_round ) : ?>
				<p class="asc-darts-display__round-info">
					<?php
					printf(
						/* translators: %d: round number */
						esc_html__( 'Finished after round %d', 'apermo-score-cards' ),
						$finished_round
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_form ) : ?>
		<!-- Score form container - hydrated by frontend JS -->
		<div class="asc-darts-form-container"></div>
	<?php elseif ( ! $game ) : ?>
		<!-- No game data and user cannot manage -->
		<div class="asc-darts__pending">
			<p><?php esc_html_e( 'Waiting for scores...', 'apermo-score-cards' ); ?></p>
			<table class="asc-darts-display__table">
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
							<td class="asc-darts-display__player">
								<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
									<img
										src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
										alt=""
										class="asc-darts-display__avatar"
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

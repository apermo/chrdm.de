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

// Get game data.
$game = Games::get( (int) $post_id, $block_id );

if ( ! $game ) {
	return;
}

// Get players.
$players = Players::get_by_ids( $player_ids );

if ( empty( $players ) ) {
	return;
}

$scores     = $game['scores'] ?? array();
$winner_ids = $game['winnerIds'] ?? array();
$status     = $game['status'] ?? 'in_progress';
$is_draw    = count( $winner_ids ) > 1;

// Sort players by ranking.
usort(
	$players,
	function ( $a, $b ) use ( $scores ) {
		$score_a = $scores[ $a['id'] ] ?? null;
		$score_b = $scores[ $b['id'] ] ?? null;

		if ( ! $score_a || ! $score_b ) {
			return 0;
		}

		// Finished players first.
		if ( 0 === $score_a['finalScore'] && 0 !== $score_b['finalScore'] ) {
			return -1;
		}
		if ( 0 === $score_b['finalScore'] && 0 !== $score_a['finalScore'] ) {
			return 1;
		}

		// Among finished, sort by round.
		if ( 0 === $score_a['finalScore'] && 0 === $score_b['finalScore'] ) {
			$round_a = $score_a['finishedRound'] ?? PHP_INT_MAX;
			$round_b = $score_b['finishedRound'] ?? PHP_INT_MAX;
			return $round_a <=> $round_b;
		}

		// Sort by remaining score.
		return $score_a['finalScore'] <=> $score_b['finalScore'];
	}
);

// Check if current user can manage.
$can_manage = Capabilities::user_can_manage( (int) $post_id );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'           => 'asc-darts',
		'data-post-id'    => $post_id,
		'data-block-id'   => $block_id,
		'data-can-manage' => $can_manage ? 'true' : 'false',
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

	<div class="asc-darts-display">
		<table class="asc-darts-display__table">
			<thead>
				<tr>
					<th class="asc-darts-display__rank-header">#</th>
					<th><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
					<th><?php esc_html_e( 'Remaining', 'apermo-score-cards' ); ?></th>
					<th><?php esc_html_e( 'Round', 'apermo-score-cards' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $players as $index => $player ) :
					$player_score = $scores[ $player['id'] ] ?? null;
					$is_winner    = in_array( $player['id'], $winner_ids, true );
					$is_finished  = $player_score && 0 === $player_score['finalScore'];

					$row_classes = array( 'asc-darts-display__row' );
					if ( $is_winner ) {
						$row_classes[] = 'asc-darts-display__row--winner';
					}
					if ( $is_finished ) {
						$row_classes[] = 'asc-darts-display__row--finished';
					}
					?>
					<tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
						<td class="asc-darts-display__rank">
							<?php if ( $is_winner ) : ?>
								<span class="asc-darts-display__trophy">🏆</span>
							<?php endif; ?>
							<?php echo esc_html( $index + 1 ); ?>
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
								<?php if ( $is_winner && $is_draw ) : ?>
									<span class="asc-darts-display__draw-label">
										<?php esc_html_e( '(Draw)', 'apermo-score-cards' ); ?>
									</span>
								<?php endif; ?>
							</span>
						</td>
						<td class="asc-darts-display__score <?php echo $is_finished ? 'asc-darts-display__score--zero' : ''; ?>">
							<?php echo $player_score ? esc_html( $player_score['finalScore'] ) : '-'; ?>
						</td>
						<td class="asc-darts-display__round">
							<?php echo $player_score && $player_score['finishedRound'] ? esc_html( $player_score['finishedRound'] ) : '-'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( 'completed' === $status && ! empty( $winner_ids ) ) : ?>
			<div class="asc-darts-display__winner-banner">
				<span class="asc-darts-display__winner-icon">🎯</span>
				<?php
				if ( $is_draw ) {
					esc_html_e( 'Draw!', 'apermo-score-cards' );
				} else {
					$winner = array_filter( $players, fn( $p ) => $p['id'] === $winner_ids[0] );
					$winner = reset( $winner );
					printf(
						/* translators: %s: winner name */
						esc_html__( '%s wins!', 'apermo-score-cards' ),
						esc_html( $winner['name'] ?? __( 'Unknown', 'apermo-score-cards' ) )
					);
				}
				?>
			</div>
		<?php endif; ?>
	</div>
</div>

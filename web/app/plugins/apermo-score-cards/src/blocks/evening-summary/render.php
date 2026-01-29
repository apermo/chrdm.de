<?php
/**
 * Evening Summary - Server-side render
 *
 * Shows a summary of all games played and calculates the evening winner.
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

$post_id = $block->context['postId'] ?? get_the_ID();

// Get all games for this post.
$games = Games::get_all_for_post( (int) $post_id );

if ( empty( $games ) ) {
	return;
}

// Filter to only completed games with positions (actual results).
$completed_games = array_filter(
	$games,
	fn( $game ) => 'completed' === ( $game['status'] ?? '' ) && ! empty( $game['positions'] )
);

if ( empty( $completed_games ) ) {
	return;
}

// Collect all unique player IDs across all games.
$all_player_ids = array();
foreach ( $completed_games as $game ) {
	$player_ids     = $game['playerIds'] ?? array();
	$all_player_ids = array_merge( $all_player_ids, $player_ids );
}
$all_player_ids = array_unique( $all_player_ids );

if ( empty( $all_player_ids ) ) {
	return;
}

// Get player data.
$players     = Players::get_by_ids( $all_player_ids );
$players_map = array();
foreach ( $players as $player ) {
	$players_map[ $player['id'] ] = $player;
}

// Calculate points for each player across all games.
// Winner gets N points (N = number of players), second N-1, ..., last gets 1.
// Players who didn't play a game get 0.
$player_points    = array_fill_keys( $all_player_ids, 0 );
$player_positions = array(); // player_id => array of positions per game

foreach ( $all_player_ids as $player_id ) {
	$player_positions[ $player_id ] = array();
}

$game_labels = array();
$game_index  = 0;

foreach ( $completed_games as $block_id => $game ) {
	$game_index++;
	$game_type     = $game['gameType'] ?? 'game';
	$game_labels[] = ucfirst( $game_type ) . ' ' . $game_index;

	$game_player_ids = $game['playerIds'] ?? array();
	$positions       = $game['positions'] ?? array();
	$num_players     = count( $game_player_ids );

	// Calculate points based on position.
	foreach ( $all_player_ids as $player_id ) {
		if ( in_array( $player_id, $game_player_ids, true ) ) {
			$position = $positions[ $player_id ] ?? $num_players;
			// Points = N - position + 1 (winner gets N, last gets 1).
			$points                              = max( 0, $num_players - $position + 1 );
			$player_points[ $player_id ]        += $points;
			$player_positions[ $player_id ][]    = $position;
		} else {
			// Player didn't participate in this game.
			$player_positions[ $player_id ][] = null;
		}
	}
}

// Sort players by total points (descending).
arsort( $player_points );

// Calculate overall positions with tie handling.
$overall_positions = array();
$current_position  = 1;
$previous_points   = null;
$index             = 0;

foreach ( $player_points as $player_id => $points ) {
	if ( $points !== $previous_points ) {
		$current_position = $index + 1;
		$previous_points  = $points;
	}
	$overall_positions[ $player_id ] = $current_position;
	$index++;
}

$medals = array( 1 => '🥇', 2 => '🥈', 3 => '🥉' );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'asc-evening-summary',
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="asc-evening-summary__header">
		<h3 class="asc-evening-summary__title">
			<?php esc_html_e( 'Evening Summary', 'apermo-score-cards' ); ?>
		</h3>
	</div>

	<div class="asc-evening-summary__table-wrapper">
		<table class="asc-evening-summary__table">
			<thead>
				<tr>
					<th class="asc-evening-summary__rank-col">#</th>
					<th class="asc-evening-summary__player-col"><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
					<?php foreach ( $game_labels as $label ) : ?>
						<th class="asc-evening-summary__game-col"><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
					<th class="asc-evening-summary__total-col"><?php esc_html_e( 'Total', 'apermo-score-cards' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $player_points as $player_id => $total_points ) :
					$player   = $players_map[ $player_id ] ?? null;
					$position = $overall_positions[ $player_id ] ?? 0;
					$medal    = $medals[ $position ] ?? '';

					if ( ! $player ) {
						continue;
					}

					$row_classes = array( 'asc-evening-summary__row' );
					if ( $position <= 3 ) {
						$row_classes[] = 'asc-evening-summary__row--position-' . $position;
					}
					?>
					<tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
						<td class="asc-evening-summary__rank">
							<?php if ( $medal ) : ?>
								<span class="asc-evening-summary__medal"><?php echo esc_html( $medal ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $position ); ?>
							<?php endif; ?>
						</td>
						<td class="asc-evening-summary__player">
							<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
								<img
									src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
									alt=""
									class="asc-evening-summary__avatar"
								/>
							<?php endif; ?>
							<span class="asc-evening-summary__name"><?php echo esc_html( $player['name'] ); ?></span>
						</td>
						<?php
						$game_idx = 0;
						foreach ( $completed_games as $game ) :
							$game_position = $player_positions[ $player_id ][ $game_idx ] ?? null;
							$game_medal    = $medals[ $game_position ] ?? '';

							$cell_classes = array( 'asc-evening-summary__game-cell' );
							if ( null === $game_position ) {
								$cell_classes[] = 'asc-evening-summary__game-cell--skipped';
							} elseif ( $game_position <= 3 ) {
								$cell_classes[] = 'asc-evening-summary__game-cell--position-' . $game_position;
							}
							?>
							<td class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>">
								<?php if ( null === $game_position ) : ?>
									<span class="asc-evening-summary__skipped">–</span>
								<?php elseif ( $game_medal ) : ?>
									<span class="asc-evening-summary__game-medal"><?php echo esc_html( $game_medal ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $game_position ); ?>
								<?php endif; ?>
							</td>
							<?php
							$game_idx++;
						endforeach;
						?>
						<td class="asc-evening-summary__total">
							<strong><?php echo esc_html( $total_points ); ?></strong>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

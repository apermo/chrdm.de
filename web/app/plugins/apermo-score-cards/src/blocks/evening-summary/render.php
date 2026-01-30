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

// Get game meta data for this post.
$game_meta = Games::get_all_for_post( (int) $post_id );

// Parse post content to get all game blocks and their playerIds.
// This catches games that haven't been started yet (no meta entry).
$post         = get_post( $post_id );
$post_content = $post->post_content ?? '';
$blocks       = parse_blocks( $post_content );

// Game block types to look for.
$game_block_types = array(
	'apermo-score-cards/darts',
	'apermo-score-cards/pool',
);

// Collect all games from blocks, merging with meta data.
$games = array();

foreach ( $blocks as $parsed_block ) {
	if ( ! in_array( $parsed_block['blockName'], $game_block_types, true ) ) {
		continue;
	}

	$block_id   = $parsed_block['attrs']['blockId'] ?? '';
	$player_ids = $parsed_block['attrs']['playerIds'] ?? array();

	if ( empty( $block_id ) || empty( $player_ids ) ) {
		continue;
	}

	// Merge block attributes with meta data (if exists).
	$meta_data = $game_meta[ $block_id ] ?? array();

	$games[ $block_id ] = array_merge(
		array(
			'blockId'   => $block_id,
			'gameType'  => str_replace( 'apermo-score-cards/', '', $parsed_block['blockName'] ),
			'playerIds' => $player_ids,
		),
		$meta_data
	);
}

if ( empty( $games ) ) {
	return;
}

// Filter to only completed games with positions (actual results).
$completed_games = array_filter(
	$games,
	fn( $game ) => 'completed' === ( $game['status'] ?? '' ) && ! empty( $game['positions'] )
);

$has_completed_games = ! empty( $completed_games );

// Collect all unique player IDs across ALL games (including unfinished).
$all_player_ids = array();
foreach ( $games as $game ) {
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

foreach ( $completed_games as $block_id => $game ) {
	$game_type     = $game['gameType'] ?? 'game';
	$game_labels[] = ucfirst( $game_type );

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

// Prepare display order and positions.
$display_player_ids = array();
$overall_positions  = array();
$medals             = array( 1 => '🥇', 2 => '🥈', 3 => '🥉' );

if ( $has_completed_games ) {
	// Count position finishes for tiebreaker (1st places, 2nd places, etc.).
	$position_counts = array();
	foreach ( $all_player_ids as $player_id ) {
		$position_counts[ $player_id ] = array();
		foreach ( $player_positions[ $player_id ] as $pos ) {
			if ( null !== $pos ) {
				$position_counts[ $player_id ][ $pos ] = ( $position_counts[ $player_id ][ $pos ] ?? 0 ) + 1;
			}
		}
	}

	// Build sortable array with all criteria.
	$sortable = array();
	foreach ( $all_player_ids as $player_id ) {
		$sortable[ $player_id ] = array(
			'points'          => $player_points[ $player_id ],
			'position_counts' => $position_counts[ $player_id ],
			'random'          => wp_rand(),
		);
	}

	// Sort by: points desc, then 1st places desc, 2nd places desc, etc., then random.
	uasort(
		$sortable,
		function ( $a, $b ) {
			// First: total points (descending).
			if ( $a['points'] !== $b['points'] ) {
				return $b['points'] <=> $a['points'];
			}

			// Tiebreaker: compare position counts (1st, 2nd, 3rd, ...).
			$max_pos = max(
				empty( $a['position_counts'] ) ? 0 : max( array_keys( $a['position_counts'] ) ),
				empty( $b['position_counts'] ) ? 0 : max( array_keys( $b['position_counts'] ) )
			);

			for ( $pos = 1; $pos <= $max_pos; $pos++ ) {
				$a_count = $a['position_counts'][ $pos ] ?? 0;
				$b_count = $b['position_counts'][ $pos ] ?? 0;
				if ( $a_count !== $b_count ) {
					return $b_count <=> $a_count; // More wins = better.
				}
			}

			// Still tied: use random order.
			return $a['random'] <=> $b['random'];
		}
	);

	$display_player_ids = array_keys( $sortable );

	// Calculate overall positions with tie handling.
	// Players are truly tied only if points AND all position counts match.
	$current_position = 1;
	$previous_key     = null;
	$index            = 0;

	foreach ( $sortable as $player_id => $data ) {
		$tie_key = $data['points'] . '-' . wp_json_encode( $data['position_counts'] );

		if ( $tie_key !== $previous_key ) {
			$current_position = $index + 1;
			$previous_key     = $tie_key;
		}
		$overall_positions[ $player_id ] = $current_position;
		$index++;
	}
} else {
	// No completed games - all players tied at position 1, random order.
	$display_player_ids = $all_player_ids;
	shuffle( $display_player_ids );

	foreach ( $display_player_ids as $player_id ) {
		$overall_positions[ $player_id ] = 1;
	}
}

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

	<?php if ( ! $has_completed_games ) : ?>
		<p class="asc-evening-summary__notice">
			<?php esc_html_e( 'No games finished so far', 'apermo-score-cards' ); ?>
		</p>
	<?php endif; ?>

	<div class="asc-evening-summary__table-wrapper">
		<table class="asc-evening-summary__table">
			<thead>
				<tr>
					<th class="asc-evening-summary__rank-col">#</th>
					<th class="asc-evening-summary__player-col"><?php esc_html_e( 'Player', 'apermo-score-cards' ); ?></th>
					<?php if ( $has_completed_games ) : ?>
						<?php foreach ( $game_labels as $label ) : ?>
							<th class="asc-evening-summary__game-col"><span><?php echo esc_html( $label ); ?></span></th>
						<?php endforeach; ?>
						<th class="asc-evening-summary__total-col"><?php esc_html_e( 'Total', 'apermo-score-cards' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $display_player_ids as $player_id ) :
					$player       = $players_map[ $player_id ] ?? null;
					$position     = $overall_positions[ $player_id ] ?? 0;
					$total_points = $player_points[ $player_id ] ?? 0;
					$medal        = $medals[ $position ] ?? '';

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
						<?php if ( $has_completed_games ) : ?>
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
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

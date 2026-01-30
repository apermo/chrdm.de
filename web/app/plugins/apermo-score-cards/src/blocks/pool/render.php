<?php
/**
 * Pool Billiard - Server-side render
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

// Build players map.
$players_map = array();
foreach ( $players as $player ) {
	$players_map[ $player['id'] ] = $player;
}

// Check if current user can manage.
$can_manage = Capabilities::user_can_manage( (int) $post_id );

// Get game data.
$game = Games::get( (int) $post_id, $block_id );

$games_list        = $game['games'] ?? array();
$status            = $game['status'] ?? 'pending';
$has_games         = ! empty( $games_list );
$stored_positions  = $game['positions'] ?? array();
$stored_scores     = $game['finalScores'] ?? array();
$is_completed      = 'completed' === $status;

// Calculate standings.
// For completed games, use stored finalScores. For in-progress, calculate live.
// Scoring: 1 point per game + 2 bonus for winning (win = 3pts, loss = 1pt).
$standings = array();
foreach ( $player_ids as $pid ) {
	$standings[ $pid ] = array(
		'playerId'   => $pid,
		'wins'       => 0,
		'losses'     => 0,
		'points'     => 0,
		'headToHead' => array(),
	);
}

foreach ( $games_list as $g ) {
	$winner_id = $g['winnerId'] ?? null;
	$player1   = $g['player1'] ?? null;
	$player2   = $g['player2'] ?? null;

	if ( ! $winner_id || ! $player1 || ! $player2 ) {
		continue;
	}

	$loser_id = ( $winner_id === $player1 ) ? $player2 : $player1;

	if ( isset( $standings[ $winner_id ] ) ) {
		$standings[ $winner_id ]['wins']++;
		if ( ! $is_completed ) {
			$standings[ $winner_id ]['points'] += 3; // Live calculation for in-progress.
		}
		if ( ! isset( $standings[ $winner_id ]['headToHead'][ $loser_id ] ) ) {
			$standings[ $winner_id ]['headToHead'][ $loser_id ] = array( 'wins' => 0, 'losses' => 0 );
		}
		$standings[ $winner_id ]['headToHead'][ $loser_id ]['wins']++;
	}

	if ( isset( $standings[ $loser_id ] ) ) {
		$standings[ $loser_id ]['losses']++;
		if ( ! $is_completed ) {
			$standings[ $loser_id ]['points'] += 1; // Live calculation for in-progress.
		}
		if ( ! isset( $standings[ $loser_id ]['headToHead'][ $winner_id ] ) ) {
			$standings[ $loser_id ]['headToHead'][ $winner_id ] = array( 'wins' => 0, 'losses' => 0 );
		}
		$standings[ $loser_id ]['headToHead'][ $winner_id ]['losses']++;
	}
}

// For completed games, use stored scores; otherwise use calculated.
if ( $is_completed && ! empty( $stored_scores ) ) {
	foreach ( $standings as $pid => &$s ) {
		$s['points'] = $stored_scores[ $pid ] ?? $s['points'];
	}
	unset( $s );
}

// Sort standings: points desc, win% desc, head-to-head, random.
uasort(
	$standings,
	function ( $a, $b ) {
		// 1. Points descending.
		if ( $a['points'] !== $b['points'] ) {
			return $b['points'] - $a['points'];
		}

		// 2. Win percentage descending.
		$a_total = $a['wins'] + $a['losses'];
		$b_total = $b['wins'] + $b['losses'];
		$a_pct   = $a_total > 0 ? $a['wins'] / $a_total : 0;
		$b_pct   = $b_total > 0 ? $b['wins'] / $b_total : 0;

		if ( $a_pct !== $b_pct ) {
			return $b_pct <=> $a_pct;
		}

		// 3. Head-to-head.
		$h2h = $a['headToHead'][ $b['playerId'] ] ?? null;
		if ( $h2h ) {
			$diff = $h2h['wins'] - $h2h['losses'];
			if ( $diff !== 0 ) {
				return -$diff;
			}
		}

		// 4. Random.
		return wp_rand( -1, 1 );
	}
);

// For completed games with stored positions, use those; otherwise calculate.
if ( $is_completed && ! empty( $stored_positions ) ) {
	$positions = $stored_positions;
} else {
	// Calculate positions with tie handling.
	$positions        = array();
	$current_position = 1;
	$prev_key         = null;
	$index            = 0;

	foreach ( $standings as $pid => $s ) {
		$a_total = $s['wins'] + $s['losses'];
		$a_pct   = $a_total > 0 ? round( $s['wins'] / $a_total, 4 ) : 0;
		$tie_key = $s['points'] . '-' . $a_pct;

		if ( $tie_key !== $prev_key ) {
			$current_position = $index + 1;
			$prev_key         = $tie_key;
		}

		$positions[ $pid ] = $current_position;
		$index++;
	}
}

$medals = array( 1 => '🥇', 2 => '🥈', 3 => '🥉' );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'           => 'asc-pool',
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
	<div class="asc-pool__header">
		<h3 class="asc-pool__title">
			<?php echo esc_html( $custom_title ?: __( 'Pool Billiard', 'apermo-score-cards' ) ); ?>
		</h3>
		<?php if ( 'completed' === $status ) : ?>
			<span class="asc-pool__status asc-pool__status--completed">
				<?php esc_html_e( 'Completed', 'apermo-score-cards' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<!-- Standings table -->
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
				<?php foreach ( $standings as $pid => $s ) :
					$player   = $players_map[ $pid ] ?? null;
					$position = $positions[ $pid ] ?? 0;
					$medal    = $medals[ $position ] ?? '';
					$total    = $s['wins'] + $s['losses'];
					$win_pct  = $total > 0 ? round( ( $s['wins'] / $total ) * 100 ) : 0;

					if ( ! $player ) {
						continue;
					}

					$row_classes = array( 'asc-pool-standings__row' );
					if ( $has_games && $position <= 3 ) {
						$row_classes[] = 'asc-pool-standings__row--position-' . $position;
					}
					?>
					<tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
						<td class="asc-pool-standings__rank">
							<?php if ( $has_games && $medal ) : ?>
								<?php echo esc_html( $medal ); ?>
							<?php elseif ( $has_games ) : ?>
								<?php echo esc_html( $position ); ?>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td class="asc-pool-standings__player">
							<?php if ( ! empty( $player['avatarUrl'] ) ) : ?>
								<img
									src="<?php echo esc_url( $player['avatarUrl'] ); ?>"
									alt=""
									class="asc-pool-standings__avatar"
								/>
							<?php endif; ?>
							<span><?php echo esc_html( $player['name'] ); ?></span>
						</td>
						<td><strong><?php echo esc_html( $s['points'] ); ?></strong></td>
						<td><?php echo esc_html( $s['wins'] ); ?></td>
						<td><?php echo esc_html( $s['losses'] ); ?></td>
						<td><?php echo $total > 0 ? esc_html( $win_pct . '%' ) : '-'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $has_games ) : ?>
		<!-- Games list -->
		<div class="asc-pool-games">
			<h4 class="asc-pool-games__title"><?php esc_html_e( 'Games', 'apermo-score-cards' ); ?></h4>
			<div class="asc-pool-games__list">
				<?php foreach ( $games_list as $game_index => $g ) :
					$player1   = $players_map[ $g['player1'] ] ?? null;
					$player2   = $players_map[ $g['player2'] ] ?? null;
					$winner_id = $g['winnerId'] ?? null;

					if ( ! $player1 || ! $player2 ) {
						continue;
					}

					$p1_is_winner = $winner_id === $g['player1'];
					$p2_is_winner = $winner_id === $g['player2'];
					?>
					<div class="asc-pool-games__item" data-game-index="<?php echo esc_attr( $game_index ); ?>">
						<div class="asc-pool-games__matchup">
							<div class="asc-pool-games__player <?php echo $p1_is_winner ? 'asc-pool-games__player--winner' : 'asc-pool-games__player--loser'; ?>">
								<?php if ( ! empty( $player1['avatarUrl'] ) ) : ?>
									<img src="<?php echo esc_url( $player1['avatarUrl'] ); ?>" alt="" class="asc-pool-games__avatar" />
								<?php endif; ?>
								<span class="asc-pool-games__name"><?php echo esc_html( $player1['name'] ); ?></span>
							</div>
							<span class="asc-pool-games__vs">vs</span>
							<div class="asc-pool-games__player <?php echo $p2_is_winner ? 'asc-pool-games__player--winner' : 'asc-pool-games__player--loser'; ?>">
								<?php if ( ! empty( $player2['avatarUrl'] ) ) : ?>
									<img src="<?php echo esc_url( $player2['avatarUrl'] ); ?>" alt="" class="asc-pool-games__avatar" />
								<?php endif; ?>
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
						<?php if ( $can_manage ) : ?>
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
	<?php endif; ?>

	<?php if ( $can_manage ) : ?>
		<?php
		// Determine which players have played at least one game.
		$players_with_games = array();
		foreach ( $games_list as $g ) {
			$players_with_games[ $g['player1'] ?? 0 ] = true;
			$players_with_games[ $g['player2'] ?? 0 ] = true;
		}
		?>
		<div class="asc-pool__actions">
			<?php if ( 'completed' !== $status ) : ?>
				<button type="button" class="asc-pool__add-game-btn">
					<?php esc_html_e( 'Add Game', 'apermo-score-cards' ); ?>
				</button>
				<button type="button" class="asc-pool__edit-players-btn">
					<?php esc_html_e( 'Edit Players', 'apermo-score-cards' ); ?>
				</button>
				<?php if ( $has_games ) : ?>
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
	<?php elseif ( ! $has_games ) : ?>
		<div class="asc-pool__pending">
			<p><?php esc_html_e( 'Waiting for games...', 'apermo-score-cards' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Darts game renderer.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Darts score card block.
 */
class Darts_Renderer extends Game_Renderer {

	/**
	 * Starting score.
	 *
	 * @var int
	 */
	protected int $starting_score;

	/**
	 * Scores keyed by player ID.
	 *
	 * @var array
	 */
	protected array $scores = array();

	/**
	 * Positions keyed by player ID.
	 *
	 * @var array
	 */
	protected array $positions = array();

	/**
	 * Finished round number.
	 *
	 * @var int|null
	 */
	protected ?int $finished_round = null;

	/**
	 * Constructor.
	 *
	 * @param array     $attributes Block attributes.
	 * @param \WP_Block $block      Block instance.
	 */
	public function __construct( array $attributes, \WP_Block $block ) {
		parent::__construct( $attributes, $block );
		$this->starting_score = (int) ( $attributes['startingScore'] ?? 501 );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_css_prefix(): string {
		return 'asc-darts';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_title(): string {
		return sprintf(
			/* translators: %d: starting score */
			__( 'Darts – %d', 'apermo-score-cards' ),
			$this->starting_score
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_display_class(): string {
		return 'asc-darts';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_extra_data_attributes(): array {
		return array(
			'data-starting-score' => (string) $this->starting_score,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function calculate_scores(): void {
		$this->scores        = $this->game['scores'] ?? array();
		$this->finished_round = isset( $this->game['finishedRound'] )
			? (int) $this->game['finishedRound']
			: null;

		if ( ! $this->game || empty( $this->scores ) ) {
			return;
		}

		// Sort players by ranking (lower remaining score is better).
		usort(
			$this->players,
			function ( $a, $b ) {
				$score_a = $this->scores[ $a['id'] ]['finalScore'] ?? PHP_INT_MAX;
				$score_b = $this->scores[ $b['id'] ]['finalScore'] ?? PHP_INT_MAX;
				return $score_a <=> $score_b;
			}
		);

		// Calculate positions with tie handling.
		$current_position = 1;
		$previous_score   = null;

		foreach ( $this->players as $index => $player ) {
			$player_score = $this->scores[ $player['id'] ]['finalScore'] ?? null;

			if ( $player_score !== $previous_score ) {
				$current_position = $index + 1;
				$previous_score   = $player_score;
			}

			$this->positions[ $player['id'] ] = $current_position;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_header(): void {
		$prefix = $this->get_css_prefix();
		$title  = $this->custom_title ?: $this->get_default_title();
		?>
		<div class="<?php echo esc_attr( $prefix . '__header' ); ?>">
			<h3 class="<?php echo esc_attr( $prefix . '__title' ); ?>">
				<?php echo esc_html( $title ); ?>
			</h3>
			<?php self::render_status_badge( $this->status, $prefix ); ?>
		</div>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_results(): void {
		if ( empty( $this->scores ) ) {
			return;
		}
		?>
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
					<?php foreach ( $this->players as $player ) :
						$player_score = $this->scores[ $player['id'] ]['finalScore'] ?? null;
						$position     = $this->positions[ $player['id'] ] ?? 0;
						$medal        = self::$medals[ $position ] ?? '';
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
									<?php echo esc_html( (string) $position ); ?>
								<?php endif; ?>
							</td>
							<td class="asc-darts-display__player">
								<?php self::render_avatar( $player, 'asc-darts-display__avatar' ); ?>
								<span class="asc-darts-display__name">
									<?php echo esc_html( $player['name'] ); ?>
								</span>
							</td>
							<td class="asc-darts-display__score <?php echo $is_finished ? 'asc-darts-display__score--zero' : ''; ?>">
								<?php echo null !== $player_score ? esc_html( (string) $player_score ) : '-'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $this->finished_round ) : ?>
				<p class="asc-darts-display__round-info">
					<?php
					printf(
						/* translators: %d: round number */
						esc_html__( 'Finished after round %d', 'apermo-score-cards' ),
						$this->finished_round
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_actions(): void {
		?>
		<div class="asc-darts__actions">
			<button type="button" class="asc-darts__edit-btn">
				<?php esc_html_e( 'Edit Results', 'apermo-score-cards' ); ?>
			</button>
			<button type="button" class="asc-darts__duplicate-btn">
				<?php esc_html_e( 'Add Another Darts Game', 'apermo-score-cards' ); ?>
			</button>
			<?php $this->render_new_game_button(); ?>
		</div>
		<?php
	}
}

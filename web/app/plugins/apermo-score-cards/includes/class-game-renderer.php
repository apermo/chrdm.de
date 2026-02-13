<?php
/**
 * Abstract game renderer for score card blocks.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for game block rendering.
 *
 * Implements the template method pattern: shared setup and structure
 * with game-specific rendering delegated to concrete subclasses.
 */
abstract class Game_Renderer {

	use Base_Render;

	/**
	 * Block attributes.
	 *
	 * @var array
	 */
	protected array $attributes;

	/**
	 * Block instance.
	 *
	 * @var \WP_Block
	 */
	protected \WP_Block $block;

	/**
	 * Block ID.
	 *
	 * @var string
	 */
	protected string $block_id;

	/**
	 * Player IDs.
	 *
	 * @var array
	 */
	protected array $player_ids;

	/**
	 * Custom title.
	 *
	 * @var string
	 */
	protected string $custom_title;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected int $post_id;

	/**
	 * Player data array.
	 *
	 * @var array
	 */
	protected array $players;

	/**
	 * Players keyed by ID.
	 *
	 * @var array
	 */
	protected array $players_map;

	/**
	 * Whether current user can manage this scorecard.
	 *
	 * @var bool
	 */
	protected bool $can_manage;

	/**
	 * Game data from post meta.
	 *
	 * @var array|null
	 */
	protected ?array $game;

	/**
	 * Game status.
	 *
	 * @var string
	 */
	protected string $status;

	/**
	 * Whether the form should be shown (no game, can manage).
	 *
	 * @var bool
	 */
	protected bool $show_form;

	/**
	 * Whether the user can edit (game exists, can manage).
	 *
	 * @var bool
	 */
	protected bool $can_edit;

	/**
	 * Constructor.
	 *
	 * @param array     $attributes Block attributes.
	 * @param \WP_Block $block      Block instance.
	 */
	public function __construct( array $attributes, \WP_Block $block ) {
		$this->attributes   = $attributes;
		$this->block        = $block;
		$this->block_id     = $attributes['blockId'] ?? '';
		$this->player_ids   = $attributes['playerIds'] ?? array();
		$this->custom_title = $attributes['customTitle'] ?? '';
		$this->post_id      = (int) ( $block->context['postId'] ?? get_the_ID() );
	}

	/**
	 * Get the CSS prefix for this game block.
	 *
	 * @return string CSS prefix (e.g., 'asc-wizard').
	 */
	abstract protected function get_css_prefix(): string;

	/**
	 * Get the default title for this game.
	 *
	 * @return string Default title.
	 */
	abstract protected function get_default_title(): string;

	/**
	 * Get the display class suffix (e.g., 'wizard-display').
	 *
	 * @return string Display class.
	 */
	abstract protected function get_display_class(): string;

	/**
	 * Get additional wrapper data attributes.
	 *
	 * @return array Data attributes keyed by attribute name.
	 */
	protected function get_extra_data_attributes(): array {
		return array();
	}

	/**
	 * Calculate game-specific scores and positions.
	 *
	 * Called after common setup. Subclasses should populate
	 * any game-specific data needed for rendering.
	 *
	 * @return void
	 */
	abstract protected function calculate_scores(): void;

	/**
	 * Render the game results display.
	 *
	 * @return void
	 */
	abstract protected function render_results(): void;

	/**
	 * Render the action buttons for editing.
	 *
	 * @return void
	 */
	abstract protected function render_actions(): void;

	/**
	 * Render the block output.
	 *
	 * Template method: calls abstract methods in a fixed order.
	 *
	 * @return string|null Rendered HTML or null to skip.
	 */
	public function render(): ?string {
		if ( empty( $this->block_id ) || empty( $this->player_ids ) ) {
			return null;
		}

		$this->players = Players::get_by_ids( $this->player_ids );

		if ( empty( $this->players ) ) {
			return null;
		}

		$this->players_map = self::build_players_map( $this->players );
		$this->can_manage  = Capabilities::user_can_manage( $this->post_id );
		$this->game        = Games::get( $this->post_id, $this->block_id );
		$this->show_form   = ! $this->game && $this->can_manage;
		$this->can_edit    = (bool) $this->game && $this->can_manage;
		$this->status      = $this->game['status'] ?? 'pending';

		$this->calculate_scores();

		ob_start();
		$this->render_wrapper();
		return ob_get_clean();
	}

	/**
	 * Build the wrapper attributes array.
	 *
	 * @return string Wrapper attributes string.
	 */
	protected function get_wrapper_attributes(): string {
		$data = array_merge(
			array(
				'class'           => $this->get_css_prefix(),
				'data-post-id'    => (string) $this->post_id,
				'data-block-id'   => $this->block_id,
				'data-can-manage' => $this->can_manage ? 'true' : 'false',
				'data-player-ids' => wp_json_encode( $this->player_ids ),
				'data-players'    => wp_json_encode( $this->players ),
				'data-game'       => $this->game ? wp_json_encode( $this->game ) : '',
			),
			$this->get_extra_data_attributes()
		);

		return get_block_wrapper_attributes( $data );
	}

	/**
	 * Render the complete block wrapper.
	 *
	 * @return void
	 */
	protected function render_wrapper(): void {
		$prefix = $this->get_css_prefix();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes.
		echo '<div ' . $this->get_wrapper_attributes() . '>';

		$this->render_header();

		if ( $this->game ) {
			$this->render_results();

			if ( $this->can_edit ) {
				$this->render_actions();
				printf(
					'<div class="%s" hidden></div>',
					esc_attr( $this->get_display_class() . '-form-container' )
				);
			}
		}

		if ( $this->show_form ) {
			printf(
				'<div class="%s"></div>',
				esc_attr( $this->get_display_class() . '-form-container' )
			);

			if ( $this->can_manage ) {
				self::render_player_editor( $prefix );
			}
		} elseif ( ! $this->game ) {
			self::render_pending_state(
				$this->players,
				$prefix,
				$this->get_display_class() . '__table'
			);
		}

		echo '</div>';
	}

	/**
	 * Render the block header (title + status).
	 *
	 * @return void
	 */
	protected function render_header(): void {
		$prefix = $this->get_css_prefix();
		$title  = $this->custom_title ?: $this->get_default_title();
		?>
		<div class="<?php echo esc_attr( $prefix . '__header' ); ?>">
			<h3 class="<?php echo esc_attr( $prefix . '__title' ); ?>">
				<?php echo esc_html( $title ); ?>
			</h3>
			<?php
			$this->render_header_meta();
			self::render_status_badge( $this->status, $prefix );
			?>
		</div>
		<?php
	}

	/**
	 * Render additional header metadata (e.g., round count).
	 *
	 * Override in subclasses to add game-specific header content.
	 *
	 * @return void
	 */
	protected function render_header_meta(): void {
		// Default: nothing extra.
	}
}

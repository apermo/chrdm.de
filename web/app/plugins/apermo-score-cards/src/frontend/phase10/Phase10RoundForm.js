/**
 * Phase 10 Round Form - Frontend Component
 *
 * Handles score entry for Phase 10 with optional card calculator.
 */

/**
 * Card point values for Phase 10.
 */
const CARD_POINTS = {
	low: { name: 'Karten 1-9', points: 5 },
	high: { name: 'Karten 10-12', points: 10 },
	skip: { name: 'Aussetzen', points: 15 },
	joker: { name: 'Joker', points: 20 },
};

export default class Phase10RoundForm {
	/**
	 * Create a new Phase10RoundForm.
	 *
	 * @param {HTMLElement} container  Container element.
	 * @param {Object}      options    Form options.
	 */
	constructor( container, options ) {
		this.container = container;
		this.postId = options.postId;
		this.blockId = options.blockId;
		this.players = options.players || [];
		this.rounds = options.rounds || [];
		this.editRoundIndex = options.editRoundIndex;
		this.onSave = options.onSave;
		this.onCancel = options.onCancel;

		this.isEditing = this.editRoundIndex !== null && this.editRoundIndex !== undefined;
		this.roundNumber = this.isEditing ? this.editRoundIndex + 1 : this.rounds.length + 1;

		// Initialize scores from existing round if editing
		this.scores = {};
		this.players.forEach( ( player ) => {
			if ( this.isEditing && this.rounds[ this.editRoundIndex ] ) {
				this.scores[ player.id ] = this.rounds[ this.editRoundIndex ][ player.id ]?.points ?? 0;
			} else {
				this.scores[ player.id ] = '';
			}
		} );

		this.activeCalculatorPlayerId = null;

		this.render();
	}

	/**
	 * Render the form.
	 */
	render() {
		const __ = window.wp?.i18n?.__ || ( ( s ) => s );

		this.container.innerHTML = `
			<div class="asc-phase10-form">
				<h4 class="asc-phase10-form__title">
					${ this.isEditing
						? __( 'Edit Round', 'apermo-score-cards' ) + ' ' + this.roundNumber
						: __( 'Round', 'apermo-score-cards' ) + ' ' + this.roundNumber
					}
				</h4>

				<div class="asc-phase10-form__players">
					${ this.players.map( ( player ) => this.renderPlayerRow( player ) ).join( '' ) }
				</div>

				<div class="asc-phase10-form__actions">
					<button type="button" class="asc-phase10-form__save-btn">
						${ this.isEditing ? __( 'Update Round', 'apermo-score-cards' ) : __( 'Save Round', 'apermo-score-cards' ) }
					</button>
					${ this.onCancel ? `
						<button type="button" class="asc-phase10-form__cancel-btn">
							${ __( 'Cancel', 'apermo-score-cards' ) }
						</button>
					` : '' }
				</div>
			</div>
		`;

		this.bindEvents();
	}

	/**
	 * Render a player row.
	 *
	 * @param {Object} player Player object.
	 * @return {string} HTML string.
	 */
	renderPlayerRow( player ) {
		const __ = window.wp?.i18n?.__ || ( ( s ) => s );
		const score = this.scores[ player.id ];

		return `
			<div class="asc-phase10-form__player-row" data-player-id="${ player.id }">
				<div class="asc-phase10-form__player-info">
					${ player.avatarUrl
						? `<img src="${ player.avatarUrl }" alt="" class="asc-phase10-form__player-avatar" />`
						: ''
					}
					<span class="asc-phase10-form__player-name">${ this.escapeHtml( player.name ) }</span>
				</div>
				<div class="asc-phase10-form__input-group">
					<input
						type="number"
						class="asc-phase10-form__input"
						data-player-id="${ player.id }"
						value="${ score }"
						min="0"
						placeholder="0"
					/>
					<button type="button" class="asc-phase10-form__calc-btn" data-player-id="${ player.id }" title="${ __( 'Calculate from cards', 'apermo-score-cards' ) }">
						🧮
					</button>
				</div>
			</div>
		`;
	}

	/**
	 * Bind form events.
	 */
	bindEvents() {
		// Input changes
		this.container.querySelectorAll( '.asc-phase10-form__input' ).forEach( ( input ) => {
			input.addEventListener( 'input', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				this.scores[ playerId ] = e.target.value === '' ? '' : parseInt( e.target.value, 10 ) || 0;
			} );
		} );

		// Calculator buttons
		this.container.querySelectorAll( '.asc-phase10-form__calc-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				this.openCalculator( playerId );
			} );
		} );

		// Save button
		const saveBtn = this.container.querySelector( '.asc-phase10-form__save-btn' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', () => this.save() );
		}

		// Cancel button
		const cancelBtn = this.container.querySelector( '.asc-phase10-form__cancel-btn' );
		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => {
				if ( this.onCancel ) {
					this.onCancel();
				}
			} );
		}
	}

	/**
	 * Open card calculator modal.
	 *
	 * @param {number} playerId Player ID.
	 */
	openCalculator( playerId ) {
		const __ = window.wp?.i18n?.__ || ( ( s ) => s );
		const player = this.players.find( ( p ) => p.id === playerId );

		if ( ! player ) {
			return;
		}

		this.activeCalculatorPlayerId = playerId;

		// Create modal
		const modal = document.createElement( 'div' );
		modal.className = 'asc-phase10-calc';
		modal.innerHTML = `
			<div class="asc-phase10-calc__modal">
				<div class="asc-phase10-calc__header">
					<h4 class="asc-phase10-calc__title">${ __( 'Calculate Points', 'apermo-score-cards' ) } - ${ this.escapeHtml( player.name ) }</h4>
					<button type="button" class="asc-phase10-calc__close">&times;</button>
				</div>
				<div class="asc-phase10-calc__body">
					<div class="asc-phase10-calc__card-types">
						${ Object.entries( CARD_POINTS ).map( ( [ key, card ] ) => `
							<div class="asc-phase10-calc__card-row">
								<div class="asc-phase10-calc__card-label">
									<span class="asc-phase10-calc__card-name">${ card.name }</span>
									<span class="asc-phase10-calc__card-points">${ card.points } ${ __( 'points each', 'apermo-score-cards' ) }</span>
								</div>
								<input
									type="number"
									class="asc-phase10-calc__card-input"
									data-card-type="${ key }"
									value="0"
									min="0"
								/>
							</div>
						` ).join( '' ) }
					</div>
					<div class="asc-phase10-calc__total">
						<span>${ __( 'Total', 'apermo-score-cards' ) }:</span>
						<span class="asc-phase10-calc__total-value">0</span>
					</div>
				</div>
				<div class="asc-phase10-calc__footer">
					<button type="button" class="asc-phase10-calc__apply-btn">${ __( 'Apply', 'apermo-score-cards' ) }</button>
					<button type="button" class="asc-phase10-calc__cancel-btn">${ __( 'Cancel', 'apermo-score-cards' ) }</button>
				</div>
			</div>
		`;

		document.body.appendChild( modal );

		// Bind modal events
		const closeModal = () => {
			modal.remove();
			this.activeCalculatorPlayerId = null;
		};

		modal.querySelector( '.asc-phase10-calc__close' ).addEventListener( 'click', closeModal );
		modal.querySelector( '.asc-phase10-calc__cancel-btn' ).addEventListener( 'click', closeModal );

		// Close on backdrop click
		modal.addEventListener( 'click', ( e ) => {
			if ( e.target === modal ) {
				closeModal();
			}
		} );

		// Update total on input
		const inputs = modal.querySelectorAll( '.asc-phase10-calc__card-input' );
		const totalValue = modal.querySelector( '.asc-phase10-calc__total-value' );

		const updateTotal = () => {
			let total = 0;
			inputs.forEach( ( input ) => {
				const cardType = input.dataset.cardType;
				const count = parseInt( input.value, 10 ) || 0;
				total += count * CARD_POINTS[ cardType ].points;
			} );
			totalValue.textContent = total;
		};

		inputs.forEach( ( input ) => {
			input.addEventListener( 'input', updateTotal );
		} );

		// Apply button
		modal.querySelector( '.asc-phase10-calc__apply-btn' ).addEventListener( 'click', () => {
			const total = parseInt( totalValue.textContent, 10 ) || 0;
			this.scores[ playerId ] = total;

			// Update input
			const input = this.container.querySelector( `.asc-phase10-form__input[data-player-id="${ playerId }"]` );
			if ( input ) {
				input.value = total;
			}

			closeModal();
		} );

		// Focus first input
		inputs[ 0 ]?.focus();
	}

	/**
	 * Save the round.
	 */
	async save() {
		const __ = window.wp?.i18n?.__ || ( ( s ) => s );
		const config = window.apermoScoreCards || {};
		const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
		const nonce = config.restNonce;

		// Build round data
		const roundData = {};
		this.players.forEach( ( player ) => {
			roundData[ player.id ] = {
				points: parseInt( this.scores[ player.id ], 10 ) || 0,
			};
		} );

		const saveBtn = this.container.querySelector( '.asc-phase10-form__save-btn' );
		if ( saveBtn ) {
			saveBtn.disabled = true;
			saveBtn.textContent = __( 'Saving...', 'apermo-score-cards' );
		}

		try {
			let response;

			if ( this.isEditing ) {
				// Update existing round
				response = await fetch(
					`${ restUrl }/posts/${ this.postId }/games/${ this.blockId }/rounds/${ this.editRoundIndex }`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,
						},
						body: JSON.stringify( roundData ),
					}
				);
			} else {
				// Add new round
				response = await fetch(
					`${ restUrl }/posts/${ this.postId }/games/${ this.blockId }/rounds`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,
						},
						body: JSON.stringify( {
							round: roundData,
							gameType: 'phase10',
						} ),
					}
				);
			}

			if ( ! response.ok ) {
				const error = await response.json().catch( () => ( {} ) );
				throw new Error( error.message || 'Failed to save round' );
			}

			if ( this.onSave ) {
				this.onSave();
			}
		} catch ( error ) {
			console.error( 'Failed to save Phase 10 round:', error );
			alert( error.message || __( 'Failed to save round. Please try again.', 'apermo-score-cards' ) );

			if ( saveBtn ) {
				saveBtn.disabled = false;
				saveBtn.textContent = this.isEditing
					? __( 'Update Round', 'apermo-score-cards' )
					: __( 'Save Round', 'apermo-score-cards' );
			}
		}
	}

	/**
	 * Escape HTML to prevent XSS.
	 *
	 * @param {string} str String to escape.
	 * @return {string} Escaped string.
	 */
	escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}
}

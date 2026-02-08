/**
 * Wizard Round Form
 *
 * Vanilla JavaScript form for entering round scores.
 */

export default class WizardRoundForm {
	constructor( container, options = {} ) {
		this.container = container;
		this.postId = options.postId;
		this.blockId = options.blockId;
		this.players = options.players || [];
		this.rounds = options.rounds || [];
		this.totalRounds = options.totalRounds || 15;
		this.editRoundIndex = options.editRoundIndex ?? null;
		this.onSave = options.onSave || ( () => window.location.reload() );
		this.onCancel = options.onCancel || null;

		this.isSubmitting = false;
		this.roundData = {};

		// Initialize round data
		const isEditing = this.editRoundIndex !== null;
		const existingRound = isEditing ? this.rounds[ this.editRoundIndex ] : null;

		this.players.forEach( ( player ) => {
			this.roundData[ player.id ] = {
				bid: existingRound?.[ player.id ]?.bid ?? '',
				won: existingRound?.[ player.id ]?.won ?? '',
			};
		} );

		this.render();
	}

	/**
	 * Calculate score for a single round.
	 *
	 * @param {number} bid Tricks bid.
	 * @param {number} won Tricks won.
	 * @return {number} Round score.
	 */
	calculateScore( bid, won ) {
		if ( bid === won ) {
			return 20 + won * 10;
		}
		return -10 * Math.abs( bid - won );
	}

	render() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const isEditing = this.editRoundIndex !== null;
		const roundNumber = isEditing ? this.editRoundIndex + 1 : this.rounds.length + 1;
		const maxTricks = roundNumber; // In Wizard, max tricks equals round number

		this.container.innerHTML = `
			<div class="asc-wizard-form">
				<h4 class="asc-wizard-form__title">
					${ isEditing
						? `${ __( 'Edit Round', 'apermo-score-cards' ) } ${ roundNumber }`
						: `${ __( 'Round', 'apermo-score-cards' ) } ${ roundNumber }`
					}
					<span class="asc-wizard-form__max-tricks">
						(${ __( 'max', 'apermo-score-cards' ) } ${ maxTricks } ${ __( 'tricks', 'apermo-score-cards' ) })
					</span>
				</h4>

				<div class="asc-wizard-form__grid">
					${ this.players.map( ( player ) => {
						const data = this.roundData[ player.id ];
						const bid = data.bid;
						const won = data.won;
						const hasValues = bid !== '' && won !== '';
						const score = hasValues ? this.calculateScore( parseInt( bid, 10 ), parseInt( won, 10 ) ) : null;

						return `
							<div class="asc-wizard-form__player-row" data-player-id="${ player.id }">
								<div class="asc-wizard-form__player-info">
									${ player.avatarUrl
										? `<img src="${ player.avatarUrl }" alt="" class="asc-wizard-form__player-avatar" />`
										: ''
									}
									<span class="asc-wizard-form__player-name">${ this.escapeHtml( player.name ) }</span>
								</div>
								<div class="asc-wizard-form__inputs">
									<div class="asc-wizard-form__input-group">
										<label class="asc-wizard-form__label">${ __( 'Bid', 'apermo-score-cards' ) }</label>
										<input
											type="number"
											class="asc-wizard-form__input asc-wizard-form__input--bid"
											min="0"
											max="${ maxTricks }"
											value="${ bid }"
											data-player-id="${ player.id }"
										/>
									</div>
									<div class="asc-wizard-form__input-group">
										<label class="asc-wizard-form__label">${ __( 'Won', 'apermo-score-cards' ) }</label>
										<input
											type="number"
											class="asc-wizard-form__input asc-wizard-form__input--won"
											min="0"
											max="${ maxTricks }"
											value="${ won }"
											data-player-id="${ player.id }"
										/>
									</div>
								</div>
								<div class="asc-wizard-form__score-preview ${ score !== null && score >= 0 ? 'asc-wizard-form__score-preview--positive' : '' } ${ score !== null && score < 0 ? 'asc-wizard-form__score-preview--negative' : '' }">
									${ score !== null ? ( score >= 0 ? '+' : '' ) + score : '-' }
								</div>
							</div>
						`;
					} ).join( '' ) }
				</div>

				<div class="asc-wizard-form__actions">
					<button type="button" class="asc-wizard-form__submit" ${ ! this.isValid() ? 'disabled' : '' }>
						${ isEditing
							? __( 'Update Round', 'apermo-score-cards' )
							: __( 'Save Round', 'apermo-score-cards' )
						}
					</button>
					<button type="button" class="asc-wizard-form__cancel">
						${ __( 'Cancel', 'apermo-score-cards' ) }
					</button>
				</div>
				<div class="asc-wizard-form__message" hidden></div>
			</div>
		`;

		this.bindEvents();
	}

	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	bindEvents() {
		// Bid inputs
		this.container.querySelectorAll( '.asc-wizard-form__input--bid' ).forEach( ( input ) => {
			input.addEventListener( 'input', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				this.roundData[ playerId ].bid = e.target.value === '' ? '' : parseInt( e.target.value, 10 );
				this.updateScorePreview( playerId );
				this.updateSubmitButton();
			} );
		} );

		// Won inputs
		this.container.querySelectorAll( '.asc-wizard-form__input--won' ).forEach( ( input ) => {
			input.addEventListener( 'input', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				this.roundData[ playerId ].won = e.target.value === '' ? '' : parseInt( e.target.value, 10 );
				this.updateScorePreview( playerId );
				this.updateSubmitButton();
			} );
		} );

		// Submit
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', () => this.submit() );
		}

		// Cancel
		const cancelBtn = this.container.querySelector( '.asc-wizard-form__cancel' );
		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => {
				if ( this.onCancel ) {
					this.onCancel();
				} else {
					this.container.hidden = true;
					this.container.innerHTML = '';
				}
			} );
		}
	}

	updateScorePreview( playerId ) {
		const row = this.container.querySelector( `[data-player-id="${ playerId }"].asc-wizard-form__player-row` );
		if ( ! row ) {
			return;
		}

		const preview = row.querySelector( '.asc-wizard-form__score-preview' );
		const data = this.roundData[ playerId ];
		const bid = data.bid;
		const won = data.won;

		if ( bid === '' || won === '' ) {
			preview.textContent = '-';
			preview.className = 'asc-wizard-form__score-preview';
			return;
		}

		const score = this.calculateScore( bid, won );
		preview.textContent = ( score >= 0 ? '+' : '' ) + score;
		preview.className = 'asc-wizard-form__score-preview';
		preview.classList.add( score >= 0 ? 'asc-wizard-form__score-preview--positive' : 'asc-wizard-form__score-preview--negative' );
	}

	updateSubmitButton() {
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		if ( submitBtn ) {
			submitBtn.disabled = ! this.isValid() || this.isSubmitting;
		}
	}

	isValid() {
		// Check all players have both bid and won
		return this.players.every( ( player ) => {
			const data = this.roundData[ player.id ];
			return data.bid !== '' && data.won !== '';
		} );
	}

	async submit() {
		if ( ! this.isValid() || this.isSubmitting ) {
			return;
		}

		this.isSubmitting = true;
		this.updateSubmitButton();

		const config = window.apermoScoreCards || {};
		const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
		const nonce = config.restNonce;

		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		const messageEl = this.container.querySelector( '.asc-wizard-form__message' );
		const originalText = submitBtn.textContent;

		submitBtn.textContent = window.wp?.i18n?.__( 'Saving...', 'apermo-score-cards' ) || 'Saving...';

		try {
			const isEditing = this.editRoundIndex !== null;
			const roundData = {};

			this.players.forEach( ( player ) => {
				roundData[ player.id ] = {
					bid: this.roundData[ player.id ].bid,
					won: this.roundData[ player.id ].won,
				};
			} );

			let response;

			if ( isEditing ) {
				// Update existing round
				response = await fetch(
					`${ restUrl }/posts/${ this.postId }/games/${ this.blockId }/rounds/${ this.editRoundIndex }`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,
						},
						body: JSON.stringify( { roundData } ),
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
						body: JSON.stringify( { roundData } ),
					}
				);
			}

			if ( ! response.ok ) {
				const error = await response.json().catch( () => ( {} ) );
				throw new Error( error.message || 'Failed to save round' );
			}

			this.onSave();
		} catch ( error ) {
			console.error( 'Failed to save round:', error );
			messageEl.textContent = error.message || 'Failed to save. Please try again.';
			messageEl.className = 'asc-wizard-form__message asc-wizard-form__message--error';
			messageEl.hidden = false;

			this.isSubmitting = false;
			submitBtn.textContent = originalText;
			this.updateSubmitButton();
		}
	}
}

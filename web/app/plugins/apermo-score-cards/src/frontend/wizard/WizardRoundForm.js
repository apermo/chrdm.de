/**
 * Wizard Round Form
 *
 * Multi-step form for entering round scores:
 * Step 1: Enter bids
 * Step 2: Store bids (saves to DB)
 * Step 3: Werewolf adjustment (optional, saves to DB)
 * Step 4: Enter results (saves to DB)
 *
 * Each step persists data to the database so:
 * - Other users can see partial data
 * - Another admin can take over entering scores
 * - Reloading the page doesn't lose entered data
 */

const STEPS = {
	BID: 1,
	WEREWOLF: 2,
	RESULTS: 3,
};

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
		this.currentStep = STEPS.BID;
		this.roundData = {};
		this.meta = {
			step: 'bids', // Track current step: 'bids', 'werewolf', 'results'
			bomb: false,
			werewolfPlayerId: null,
			werewolfAdjustment: 0,
		};

		// Determine if we're editing an existing round or adding a new one
		// Also check for incomplete rounds (bids entered but no results)
		const isEditing = this.editRoundIndex !== null;
		let existingRound = isEditing ? this.rounds[ this.editRoundIndex ] : null;

		// Check if there's an incomplete round (last round has no 'won' values)
		if ( ! isEditing && this.rounds.length > 0 ) {
			const lastRound = this.rounds[ this.rounds.length - 1 ];
			const isIncomplete = this.players.some( ( player ) => {
				const data = lastRound[ player.id ];
				return data && data.bid !== undefined && data.bid !== '' &&
					( data.won === undefined || data.won === '' || data.won === null );
			} );

			if ( isIncomplete ) {
				// Resume the incomplete round
				this.editRoundIndex = this.rounds.length - 1;
				existingRound = lastRound;
			}
		}

		// Initialize round data from existing round or empty
		this.players.forEach( ( player ) => {
			this.roundData[ player.id ] = {
				bid: existingRound?.[ player.id ]?.bid ?? '',
				won: existingRound?.[ player.id ]?.won ?? '',
			};
		} );

		// Load existing meta if editing
		if ( existingRound?._meta ) {
			this.meta = { ...this.meta, ...existingRound._meta };
		}

		// Determine starting step based on stored step in meta
		if ( this.meta.step ) {
			switch ( this.meta.step ) {
				case 'werewolf':
					this.currentStep = STEPS.WEREWOLF;
					break;
				case 'results':
					this.currentStep = STEPS.RESULTS;
					break;
				default:
					this.currentStep = STEPS.BID;
			}
		} else if ( this.allBidsEntered() ) {
			// Fallback for rounds without step tracking
			if ( this.allResultsEntered() ) {
				this.currentStep = STEPS.RESULTS;
			} else {
				this.currentStep = STEPS.WEREWOLF;
			}
		}

		this.render();
	}

	/**
	 * Get the round number.
	 *
	 * @return {number} Round number (1-based).
	 */
	getRoundNumber() {
		if ( this.editRoundIndex !== null ) {
			return this.editRoundIndex + 1;
		}
		return this.rounds.length + 1;
	}

	/**
	 * Get the round index for API calls.
	 *
	 * @return {number|null} Round index (0-based) or null for new round.
	 */
	getRoundIndex() {
		return this.editRoundIndex;
	}

	/**
	 * Calculate the sum of all bids.
	 *
	 * @return {number} Sum of bids.
	 */
	getBidSum() {
		return this.players.reduce( ( sum, player ) => {
			const bid = this.roundData[ player.id ].bid;
			return sum + ( bid === '' ? 0 : parseInt( bid, 10 ) );
		}, 0 );
	}

	/**
	 * Check if a bid value is valid.
	 *
	 * @param {number|string} bid Bid value.
	 * @return {boolean} True if valid.
	 */
	isValidBid( bid ) {
		if ( bid === '' || bid === null || bid === undefined ) {
			return false;
		}
		const numBid = parseInt( bid, 10 );
		const maxTricks = this.getRoundNumber();
		return ! isNaN( numBid ) && numBid >= 0 && numBid <= maxTricks;
	}

	/**
	 * Check if a won value is valid.
	 *
	 * @param {number|string} won Won value.
	 * @return {boolean} True if valid.
	 */
	isValidWon( won ) {
		if ( won === '' || won === null || won === undefined ) {
			return false;
		}
		const numWon = parseInt( won, 10 );
		const maxTricks = this.getRoundNumber();
		return ! isNaN( numWon ) && numWon >= 0 && numWon <= maxTricks;
	}

	/**
	 * Check if all bids are entered and valid.
	 *
	 * @return {boolean} True if all bids are entered and valid.
	 */
	allBidsEntered() {
		return this.players.every( ( player ) => {
			return this.isValidBid( this.roundData[ player.id ].bid );
		} );
	}

	/**
	 * Get the sum of tricks won.
	 *
	 * @return {number} Sum of won tricks.
	 */
	getWonSum() {
		return this.players.reduce( ( sum, player ) => {
			const won = this.roundData[ player.id ].won;
			return sum + ( won === '' ? 0 : parseInt( won, 10 ) );
		}, 0 );
	}

	/**
	 * Check if all results are entered and valid.
	 *
	 * @return {boolean} True if all results are entered and valid.
	 */
	allResultsEntered() {
		return this.players.every( ( player ) => {
			return this.isValidWon( this.roundData[ player.id ].won );
		} );
	}

	/**
	 * Get expected tricks for validation.
	 *
	 * @return {number} Expected number of tricks.
	 */
	getExpectedTricks() {
		const roundNumber = this.getRoundNumber();
		return this.meta.bomb ? roundNumber - 1 : roundNumber;
	}

	/**
	 * Check if results validation passes.
	 *
	 * @return {boolean} True if validation passes.
	 */
	isResultsValid() {
		if ( ! this.allResultsEntered() ) {
			return false;
		}
		return this.getWonSum() === this.getExpectedTricks();
	}

	/**
	 * Get effective bid for a player (including werewolf adjustment).
	 *
	 * @param {number} playerId Player ID.
	 * @return {number} Effective bid.
	 */
	getEffectiveBid( playerId ) {
		const baseBid = this.roundData[ playerId ].bid;
		if ( baseBid === '' ) {
			return 0;
		}
		const bid = parseInt( baseBid, 10 );
		if ( this.meta.werewolfPlayerId === playerId ) {
			return bid + this.meta.werewolfAdjustment;
		}
		return bid;
	}

	/**
	 * Calculate score for a single round.
	 *
	 * @param {number} effectiveBid Effective bid (with werewolf adjustment).
	 * @param {number} won          Tricks won.
	 * @return {number} Round score.
	 */
	calculateScore( effectiveBid, won ) {
		if ( effectiveBid === won ) {
			return 20 + won * 10;
		}
		return -10 * Math.abs( effectiveBid - won );
	}

	/**
	 * Build round data object for API.
	 *
	 * @return {Object} Round data with player scores and meta.
	 */
	buildRoundData() {
		const roundData = {};

		this.players.forEach( ( player ) => {
			roundData[ player.id ] = {
				bid: this.roundData[ player.id ].bid,
				won: this.roundData[ player.id ].won,
			};
		} );

		// Add meta data
		roundData._meta = {
			step: this.meta.step,
			bomb: this.meta.bomb,
			werewolfPlayerId: this.meta.werewolfPlayerId,
			werewolfAdjustment: this.meta.werewolfAdjustment,
		};

		return roundData;
	}

	/**
	 * Save round data to the database.
	 *
	 * @return {Promise<boolean>} True on success.
	 */
	async saveRoundData() {
		const config = window.apermoScoreCards || {};
		const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
		const nonce = config.restNonce;

		const roundData = this.buildRoundData();
		const roundIndex = this.getRoundIndex();

		let response;

		if ( roundIndex !== null ) {
			// Update existing round
			response = await fetch(
				`${ restUrl }/posts/${ this.postId }/games/${ this.blockId }/rounds/${ roundIndex }`,
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

			// After adding a new round, we need to track it as an existing round
			if ( response.ok ) {
				const data = await response.json();
				// The new round is now the last one
				this.editRoundIndex = ( data.rounds?.length ?? 1 ) - 1;
			}
		}

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to save round' );
		}

		return true;
	}

	render() {
		switch ( this.currentStep ) {
			case STEPS.BID:
				this.renderBidStep();
				break;
			case STEPS.WEREWOLF:
				this.renderWerewolfStep();
				break;
			case STEPS.RESULTS:
				this.renderResultsStep();
				break;
		}
	}

	renderBidStep() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const isEditing = this.editRoundIndex !== null;
		const roundNumber = this.getRoundNumber();
		const maxTricks = roundNumber;
		const bidSum = this.getBidSum();
		const difference = bidSum - roundNumber;
		const isBalanced = difference === 0;

		this.container.innerHTML = `
			<div class="asc-wizard-form">
				<div class="asc-wizard-form__step-indicator">
					<span class="asc-wizard-form__step asc-wizard-form__step--active">${ __( 'Bids', 'apermo-score-cards' ) }</span>
					<span class="asc-wizard-form__step-separator">→</span>
					<span class="asc-wizard-form__step">${ __( 'Werewolf', 'apermo-score-cards' ) }</span>
					<span class="asc-wizard-form__step-separator">→</span>
					<span class="asc-wizard-form__step">${ __( 'Results', 'apermo-score-cards' ) }</span>
				</div>

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
						const bid = this.roundData[ player.id ].bid;
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
											inputmode="numeric"
										/>
									</div>
								</div>
							</div>
						`;
					} ).join( '' ) }
				</div>

				<div class="asc-wizard-form__bid-summary asc-wizard-form__bid-summary--${ isBalanced ? 'balanced' : 'unbalanced' }">
					<span class="asc-wizard-form__bid-total">
						${ __( 'Total', 'apermo-score-cards' ) }: ${ bidSum } / ${ roundNumber } ${ __( 'tricks', 'apermo-score-cards' ) }
					</span>
					${ ! isBalanced ? `
						<span class="asc-wizard-form__bid-difference">
							${ difference > 0 ? '+' : '' }${ difference }
						</span>
					` : '' }
				</div>

				<div class="asc-wizard-form__actions">
					<button type="button" class="asc-wizard-form__submit" ${ ! this.allBidsEntered() ? 'disabled' : '' }>
						${ __( 'Store Bids', 'apermo-score-cards' ) }
					</button>
					<button type="button" class="asc-wizard-form__cancel">
						${ __( 'Cancel', 'apermo-score-cards' ) }
					</button>
				</div>
				<div class="asc-wizard-form__message" hidden></div>
			</div>
		`;

		this.bindBidStepEvents();
	}

	bindBidStepEvents() {
		const maxTricks = this.getRoundNumber();

		// Bid inputs
		this.container.querySelectorAll( '.asc-wizard-form__input--bid' ).forEach( ( input ) => {
			input.addEventListener( 'input', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				let value = e.target.value;

				if ( value !== '' ) {
					let numValue = parseInt( value, 10 );
					// Clamp value to valid range.
					if ( ! isNaN( numValue ) ) {
						if ( numValue < 0 ) {
							numValue = 0;
							e.target.value = '0';
						} else if ( numValue > maxTricks ) {
							numValue = maxTricks;
							e.target.value = String( maxTricks );
						}
						value = numValue;
					}
				}

				this.roundData[ playerId ].bid = value === '' ? '' : parseInt( value, 10 );
				this.updateInputValidation( e.target, this.isValidBid( this.roundData[ playerId ].bid ) );
				this.updateBidSummary();
				this.updateSubmitButton();
			} );

			// Also validate on blur to catch edge cases.
			input.addEventListener( 'blur', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				const bid = this.roundData[ playerId ].bid;
				if ( bid !== '' && ! this.isValidBid( bid ) ) {
					// Clamp to valid range on blur.
					const numBid = parseInt( bid, 10 );
					if ( numBid < 0 ) {
						this.roundData[ playerId ].bid = 0;
						e.target.value = '0';
					} else if ( numBid > maxTricks ) {
						this.roundData[ playerId ].bid = maxTricks;
						e.target.value = String( maxTricks );
					}
					this.updateInputValidation( e.target, true );
					this.updateBidSummary();
					this.updateSubmitButton();
				}
			} );
		} );

		// Submit (Store Bids) - saves to DB
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', () => this.storeBids() );
		}

		// Cancel
		this.bindCancelButton();
	}

	/**
	 * Update input validation visual state.
	 *
	 * @param {HTMLElement} input   Input element.
	 * @param {boolean}     isValid Whether the value is valid.
	 */
	updateInputValidation( input, isValid ) {
		if ( isValid || input.value === '' ) {
			input.classList.remove( 'asc-wizard-form__input--invalid' );
		} else {
			input.classList.add( 'asc-wizard-form__input--invalid' );
		}
	}

	async storeBids() {
		if ( ! this.allBidsEntered() || this.isSubmitting ) {
			return;
		}

		this.isSubmitting = true;
		this.updateSubmitButton();

		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		const messageEl = this.container.querySelector( '.asc-wizard-form__message' );
		const originalText = submitBtn.textContent;

		submitBtn.textContent = __( 'Saving...', 'apermo-score-cards' );

		// Update step before saving
		this.meta.step = 'werewolf';

		try {
			await this.saveRoundData();

			// Move to next step
			this.isSubmitting = false;
			this.currentStep = STEPS.WEREWOLF;
			this.render();
		} catch ( error ) {
			console.error( 'Failed to save bids:', error );
			messageEl.textContent = error.message || __( 'Failed to save. Please try again.', 'apermo-score-cards' );
			messageEl.className = 'asc-wizard-form__message asc-wizard-form__message--error';
			messageEl.hidden = false;

			// Revert step on error
			this.meta.step = 'bids';
			this.isSubmitting = false;
			submitBtn.textContent = originalText;
			this.updateSubmitButton();
		}
	}

	updateBidSummary() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const roundNumber = this.getRoundNumber();
		const bidSum = this.getBidSum();
		const difference = bidSum - roundNumber;
		const isBalanced = difference === 0;

		const summary = this.container.querySelector( '.asc-wizard-form__bid-summary' );
		if ( summary ) {
			summary.className = `asc-wizard-form__bid-summary asc-wizard-form__bid-summary--${ isBalanced ? 'balanced' : 'unbalanced' }`;
			summary.innerHTML = `
				<span class="asc-wizard-form__bid-total">
					${ __( 'Total', 'apermo-score-cards' ) }: ${ bidSum } / ${ roundNumber } ${ __( 'tricks', 'apermo-score-cards' ) }
				</span>
				${ ! isBalanced ? `
					<span class="asc-wizard-form__bid-difference">
						${ difference > 0 ? '+' : '' }${ difference }
					</span>
				` : '' }
			`;
		}
	}

	renderWerewolfStep() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const roundNumber = this.getRoundNumber();

		this.container.innerHTML = `
			<div class="asc-wizard-form">
				<div class="asc-wizard-form__step-indicator">
					<span class="asc-wizard-form__step asc-wizard-form__step--done">${ __( 'Bids', 'apermo-score-cards' ) }</span>
					<span class="asc-wizard-form__step-separator">→</span>
					<span class="asc-wizard-form__step asc-wizard-form__step--active">${ __( 'Werewolf', 'apermo-score-cards' ) }</span>
					<span class="asc-wizard-form__step-separator">→</span>
					<span class="asc-wizard-form__step">${ __( 'Results', 'apermo-score-cards' ) }</span>
				</div>

				<h4 class="asc-wizard-form__title">
					${ __( 'Round', 'apermo-score-cards' ) } ${ roundNumber } - ${ __( 'Werewolf Adjustment', 'apermo-score-cards' ) }
				</h4>

				<div class="asc-wizard-form__werewolf-grid">
					${ this.players.map( ( player ) => {
						const bid = parseInt( this.roundData[ player.id ].bid, 10 );
						const isSelected = this.meta.werewolfPlayerId === player.id;
						const selectedMinus = isSelected && this.meta.werewolfAdjustment === -1;
						const selectedPlus = isSelected && this.meta.werewolfAdjustment === 1;
						// Disable -1 if bid is already 0, disable +1 if bid is already max.
						const canMinus = bid > 0;
						const canPlus = bid < roundNumber;

						return `
							<div class="asc-wizard-form__werewolf-row" data-player-id="${ player.id }">
								<div class="asc-wizard-form__player-info">
									${ player.avatarUrl
										? `<img src="${ player.avatarUrl }" alt="" class="asc-wizard-form__player-avatar" />`
										: ''
									}
									<span class="asc-wizard-form__player-name">${ this.escapeHtml( player.name ) }</span>
									<span class="asc-wizard-form__player-bid">(${ bid })</span>
								</div>
								<div class="asc-wizard-form__werewolf-buttons">
									<button
										type="button"
										class="asc-wizard-form__werewolf-btn asc-wizard-form__werewolf-btn--minus ${ selectedMinus ? 'asc-wizard-form__werewolf-btn--selected' : '' }"
										data-player-id="${ player.id }"
										data-adjustment="-1"
										${ ! canMinus ? 'disabled' : '' }
									>-1</button>
									<button
										type="button"
										class="asc-wizard-form__werewolf-btn asc-wizard-form__werewolf-btn--plus ${ selectedPlus ? 'asc-wizard-form__werewolf-btn--selected' : '' }"
										data-player-id="${ player.id }"
										data-adjustment="1"
										${ ! canPlus ? 'disabled' : '' }
									>+1</button>
								</div>
							</div>
						`;
					} ).join( '' ) }

					<div class="asc-wizard-form__werewolf-row asc-wizard-form__werewolf-row--no-werewolf">
						<button
							type="button"
							class="asc-wizard-form__no-werewolf-btn ${ this.meta.werewolfPlayerId === null ? 'asc-wizard-form__no-werewolf-btn--selected' : '' }"
						>
							${ __( 'No Werewolf this round', 'apermo-score-cards' ) }
						</button>
					</div>
				</div>

				<div class="asc-wizard-form__actions">
					<button type="button" class="asc-wizard-form__submit">
						${ __( 'Continue', 'apermo-score-cards' ) }
					</button>
					<button type="button" class="asc-wizard-form__back">
						${ __( 'Back', 'apermo-score-cards' ) }
					</button>
				</div>
				<div class="asc-wizard-form__message" hidden></div>
			</div>
		`;

		this.bindWerewolfStepEvents();
	}

	bindWerewolfStepEvents() {
		// Werewolf adjustment buttons
		this.container.querySelectorAll( '.asc-wizard-form__werewolf-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				const adjustment = parseInt( e.target.dataset.adjustment, 10 );

				// Toggle: if same button clicked again, deselect
				if ( this.meta.werewolfPlayerId === playerId && this.meta.werewolfAdjustment === adjustment ) {
					this.meta.werewolfPlayerId = null;
					this.meta.werewolfAdjustment = 0;
				} else {
					this.meta.werewolfPlayerId = playerId;
					this.meta.werewolfAdjustment = adjustment;
				}

				this.updateWerewolfSelection();
			} );
		} );

		// No Werewolf button
		const noWerewolfBtn = this.container.querySelector( '.asc-wizard-form__no-werewolf-btn' );
		if ( noWerewolfBtn ) {
			noWerewolfBtn.addEventListener( 'click', () => {
				this.meta.werewolfPlayerId = null;
				this.meta.werewolfAdjustment = 0;
				this.updateWerewolfSelection();
			} );
		}

		// Continue button - saves to DB
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', () => this.storeWerewolf() );
		}

		// Back button
		const backBtn = this.container.querySelector( '.asc-wizard-form__back' );
		if ( backBtn ) {
			backBtn.addEventListener( 'click', () => {
				this.currentStep = STEPS.BID;
				this.render();
			} );
		}
	}

	async storeWerewolf() {
		if ( this.isSubmitting ) {
			return;
		}

		this.isSubmitting = true;

		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		const messageEl = this.container.querySelector( '.asc-wizard-form__message' );
		const originalText = submitBtn.textContent;

		submitBtn.textContent = __( 'Saving...', 'apermo-score-cards' );
		submitBtn.disabled = true;

		// Update step before saving
		this.meta.step = 'results';

		try {
			await this.saveRoundData();

			// Move to next step
			this.isSubmitting = false;
			this.currentStep = STEPS.RESULTS;
			this.render();
		} catch ( error ) {
			console.error( 'Failed to save werewolf adjustment:', error );
			messageEl.textContent = error.message || __( 'Failed to save. Please try again.', 'apermo-score-cards' );
			messageEl.className = 'asc-wizard-form__message asc-wizard-form__message--error';
			messageEl.hidden = false;

			// Revert step on error
			this.meta.step = 'werewolf';
			this.isSubmitting = false;
			submitBtn.textContent = originalText;
			submitBtn.disabled = false;
		}
	}

	updateWerewolfSelection() {
		// Clear all selections
		this.container.querySelectorAll( '.asc-wizard-form__werewolf-btn' ).forEach( ( btn ) => {
			btn.classList.remove( 'asc-wizard-form__werewolf-btn--selected' );
		} );

		const noWerewolfBtn = this.container.querySelector( '.asc-wizard-form__no-werewolf-btn' );

		if ( this.meta.werewolfPlayerId !== null ) {
			// Select the appropriate button
			const selector = `.asc-wizard-form__werewolf-btn[data-player-id="${ this.meta.werewolfPlayerId }"][data-adjustment="${ this.meta.werewolfAdjustment }"]`;
			const selectedBtn = this.container.querySelector( selector );
			if ( selectedBtn ) {
				selectedBtn.classList.add( 'asc-wizard-form__werewolf-btn--selected' );
			}
			if ( noWerewolfBtn ) {
				noWerewolfBtn.classList.remove( 'asc-wizard-form__no-werewolf-btn--selected' );
			}
		} else if ( noWerewolfBtn ) {
			noWerewolfBtn.classList.add( 'asc-wizard-form__no-werewolf-btn--selected' );
		}
	}

	renderResultsStep() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const isEditing = this.editRoundIndex !== null;
		const roundNumber = this.getRoundNumber();
		const maxTricks = roundNumber;
		const wonSum = this.getWonSum();
		const expectedTricks = this.getExpectedTricks();
		const isValid = wonSum === expectedTricks;

		this.container.innerHTML = `
			<div class="asc-wizard-form">
				<div class="asc-wizard-form__step-indicator">
					<span class="asc-wizard-form__step asc-wizard-form__step--done">${ __( 'Bids', 'apermo-score-cards' ) }</span>
					<span class="asc-wizard-form__step-separator">→</span>
					<span class="asc-wizard-form__step asc-wizard-form__step--done">${ __( 'Werewolf', 'apermo-score-cards' ) }</span>
					<span class="asc-wizard-form__step-separator">→</span>
					<span class="asc-wizard-form__step asc-wizard-form__step--active">${ __( 'Results', 'apermo-score-cards' ) }</span>
				</div>

				<h4 class="asc-wizard-form__title">
					${ __( 'Round', 'apermo-score-cards' ) } ${ roundNumber } - ${ __( 'Enter Results', 'apermo-score-cards' ) }
				</h4>

				<div class="asc-wizard-form__grid">
					${ this.players.map( ( player ) => {
						const bid = this.roundData[ player.id ].bid;
						const won = this.roundData[ player.id ].won;
						const effectiveBid = this.getEffectiveBid( player.id );
						const hasWerewolf = this.meta.werewolfPlayerId === player.id;
						const hasValues = bid !== '' && won !== '';
						const score = hasValues ? this.calculateScore( effectiveBid, parseInt( won, 10 ) ) : null;

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
										<span class="asc-wizard-form__bid-display">
											${ bid }${ hasWerewolf ? `<sup class="asc-wizard-form__werewolf-indicator">${ this.meta.werewolfAdjustment > 0 ? '+' : '' }${ this.meta.werewolfAdjustment }</sup>` : '' }
										</span>
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
											inputmode="numeric"
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

				<div class="asc-wizard-form__bomb-option">
					<label class="asc-wizard-form__bomb-label">
						<input
							type="checkbox"
							class="asc-wizard-form__bomb-checkbox"
							${ this.meta.bomb ? 'checked' : '' }
						/>
						${ __( 'Bomb played (one trick voided)', 'apermo-score-cards' ) }
					</label>
				</div>

				<div class="asc-wizard-form__results-summary asc-wizard-form__results-summary--${ isValid ? 'valid' : 'invalid' }">
					<span class="asc-wizard-form__results-total">
						${ __( 'Total', 'apermo-score-cards' ) }: ${ wonSum } / ${ expectedTricks } ${ __( 'tricks', 'apermo-score-cards' ) }
						${ isValid ? '✓' : '' }
					</span>
					${ ! isValid && this.allResultsEntered() ? `
						<span class="asc-wizard-form__results-error">
							${ __( 'Total tricks must equal', 'apermo-score-cards' ) } ${ expectedTricks }
						</span>
					` : '' }
				</div>

				<div class="asc-wizard-form__actions">
					<button type="button" class="asc-wizard-form__submit" ${ ! this.isResultsValid() ? 'disabled' : '' }>
						${ isEditing && this.allResultsEntered()
							? __( 'Update Round', 'apermo-score-cards' )
							: __( 'Save Round', 'apermo-score-cards' )
						}
					</button>
					<button type="button" class="asc-wizard-form__back">
						${ __( 'Back', 'apermo-score-cards' ) }
					</button>
				</div>
				<div class="asc-wizard-form__message" hidden></div>
			</div>
		`;

		this.bindResultsStepEvents();
	}

	bindResultsStepEvents() {
		const maxTricks = this.getRoundNumber();

		// Won inputs
		this.container.querySelectorAll( '.asc-wizard-form__input--won' ).forEach( ( input ) => {
			input.addEventListener( 'input', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				let value = e.target.value;

				if ( value !== '' ) {
					let numValue = parseInt( value, 10 );
					// Clamp value to valid range.
					if ( ! isNaN( numValue ) ) {
						if ( numValue < 0 ) {
							numValue = 0;
							e.target.value = '0';
						} else if ( numValue > maxTricks ) {
							numValue = maxTricks;
							e.target.value = String( maxTricks );
						}
						value = numValue;
					}
				}

				this.roundData[ playerId ].won = value === '' ? '' : parseInt( value, 10 );
				this.updateInputValidation( e.target, this.isValidWon( this.roundData[ playerId ].won ) );
				this.updateScorePreview( playerId );
				this.updateResultsSummary();
				this.updateSubmitButton();
			} );

			// Also validate on blur to catch edge cases.
			input.addEventListener( 'blur', ( e ) => {
				const playerId = parseInt( e.target.dataset.playerId, 10 );
				const won = this.roundData[ playerId ].won;
				if ( won !== '' && ! this.isValidWon( won ) ) {
					// Clamp to valid range on blur.
					const numWon = parseInt( won, 10 );
					if ( numWon < 0 ) {
						this.roundData[ playerId ].won = 0;
						e.target.value = '0';
					} else if ( numWon > maxTricks ) {
						this.roundData[ playerId ].won = maxTricks;
						e.target.value = String( maxTricks );
					}
					this.updateInputValidation( e.target, true );
					this.updateScorePreview( playerId );
					this.updateResultsSummary();
					this.updateSubmitButton();
				}
			} );
		} );

		// Bomb checkbox
		const bombCheckbox = this.container.querySelector( '.asc-wizard-form__bomb-checkbox' );
		if ( bombCheckbox ) {
			bombCheckbox.addEventListener( 'change', ( e ) => {
				this.meta.bomb = e.target.checked;
				this.updateResultsSummary();
				this.updateSubmitButton();
			} );
		}

		// Submit - saves to DB and calls onSave
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', () => this.submit() );
		}

		// Back button
		const backBtn = this.container.querySelector( '.asc-wizard-form__back' );
		if ( backBtn ) {
			backBtn.addEventListener( 'click', () => {
				this.currentStep = STEPS.WEREWOLF;
				this.render();
			} );
		}
	}

	updateResultsSummary() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const wonSum = this.getWonSum();
		const expectedTricks = this.getExpectedTricks();
		const isValid = wonSum === expectedTricks;

		const summary = this.container.querySelector( '.asc-wizard-form__results-summary' );
		if ( summary ) {
			summary.className = `asc-wizard-form__results-summary asc-wizard-form__results-summary--${ isValid ? 'valid' : 'invalid' }`;
			summary.innerHTML = `
				<span class="asc-wizard-form__results-total">
					${ __( 'Total', 'apermo-score-cards' ) }: ${ wonSum } / ${ expectedTricks } ${ __( 'tricks', 'apermo-score-cards' ) }
					${ isValid ? '✓' : '' }
				</span>
				${ ! isValid && this.allResultsEntered() ? `
					<span class="asc-wizard-form__results-error">
						${ __( 'Total tricks must equal', 'apermo-score-cards' ) } ${ expectedTricks }
					</span>
				` : '' }
			`;
		}
	}

	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	bindCancelButton() {
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
		const won = this.roundData[ playerId ].won;
		const bid = this.roundData[ playerId ].bid;

		if ( bid === '' || won === '' ) {
			preview.textContent = '-';
			preview.className = 'asc-wizard-form__score-preview';
			return;
		}

		const effectiveBid = this.getEffectiveBid( playerId );
		const score = this.calculateScore( effectiveBid, parseInt( won, 10 ) );
		preview.textContent = ( score >= 0 ? '+' : '' ) + score;
		preview.className = 'asc-wizard-form__score-preview';
		preview.classList.add( score >= 0 ? 'asc-wizard-form__score-preview--positive' : 'asc-wizard-form__score-preview--negative' );
	}

	updateSubmitButton() {
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		if ( ! submitBtn ) {
			return;
		}

		if ( this.currentStep === STEPS.BID ) {
			submitBtn.disabled = ! this.allBidsEntered() || this.isSubmitting;
		} else if ( this.currentStep === STEPS.RESULTS ) {
			submitBtn.disabled = ! this.isResultsValid() || this.isSubmitting;
		}
	}

	async submit() {
		if ( ! this.isResultsValid() || this.isSubmitting ) {
			return;
		}

		this.isSubmitting = true;
		this.updateSubmitButton();

		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const submitBtn = this.container.querySelector( '.asc-wizard-form__submit' );
		const messageEl = this.container.querySelector( '.asc-wizard-form__message' );
		const originalText = submitBtn.textContent;

		submitBtn.textContent = __( 'Saving...', 'apermo-score-cards' );

		try {
			await this.saveRoundData();
			this.onSave();
		} catch ( error ) {
			console.error( 'Failed to save round:', error );
			messageEl.textContent = error.message || __( 'Failed to save. Please try again.', 'apermo-score-cards' );
			messageEl.className = 'asc-wizard-form__message asc-wizard-form__message--error';
			messageEl.hidden = false;

			this.isSubmitting = false;
			submitBtn.textContent = originalText;
			this.updateSubmitButton();
		}
	}
}

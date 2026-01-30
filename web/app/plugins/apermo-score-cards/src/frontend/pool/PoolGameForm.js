/**
 * Pool Game Form - Frontend component for adding/editing pool games
 */

export default class PoolGameForm {
	constructor( container, options ) {
		this.container = container;
		this.postId = options.postId;
		this.blockId = options.blockId;
		this.players = options.players || [];
		this.games = options.games || [];
		this.editIndex = options.editIndex ?? null; // null = new game, number = edit existing
		this.onSave = options.onSave || ( () => {} );
		this.onCancel = options.onCancel || ( () => {} );

		this.config = window.apermoScoreCards || {};
		this.restUrl = this.config.restUrl || '/wp-json/apermo-score-cards/v1';
		this.nonce = this.config.restNonce;

		this.state = {
			player1: null,
			player2: null,
			winnerId: null,
			ballsLeft: '',
			eightBallFoul: false,
			isSubmitting: false,
			error: null,
		};

		// Pre-fill if editing
		if ( this.editIndex !== null && this.games[ this.editIndex ] ) {
			const game = this.games[ this.editIndex ];
			this.state.player1 = game.player1;
			this.state.player2 = game.player2;
			this.state.winnerId = game.winnerId;
			this.state.ballsLeft = game.ballsLeft ?? '';
			this.state.eightBallFoul = game.eightBallFoul ?? false;
		}

		this.render();
		this.bindEvents();
	}

	render() {
		const isEditing = this.editIndex !== null;
		const title = isEditing
			? ( window.wp?.i18n?.__( 'Edit Game', 'apermo-score-cards' ) || 'Edit Game' )
			: ( window.wp?.i18n?.__( 'Add Game', 'apermo-score-cards' ) || 'Add Game' );

		const playerOptions = this.players
			.map( ( p ) => `<option value="${ p.id }">${ p.name }</option>` )
			.join( '' );

		this.container.innerHTML = `
			<div class="asc-pool-form">
				<h4 class="asc-pool-form__title">${ title }</h4>

				<div class="asc-pool-form__row">
					<div class="asc-pool-form__player-select">
						<label>${ window.wp?.i18n?.__( 'Player 1', 'apermo-score-cards' ) || 'Player 1' }</label>
						<select class="asc-pool-form__player1" ${ isEditing ? 'disabled' : '' }>
							<option value="">${ window.wp?.i18n?.__( 'Select player...', 'apermo-score-cards' ) || 'Select player...' }</option>
							${ playerOptions }
						</select>
					</div>
					<span class="asc-pool-form__vs">vs</span>
					<div class="asc-pool-form__player-select">
						<label>${ window.wp?.i18n?.__( 'Player 2', 'apermo-score-cards' ) || 'Player 2' }</label>
						<select class="asc-pool-form__player2" ${ isEditing ? 'disabled' : '' }>
							<option value="">${ window.wp?.i18n?.__( 'Select player...', 'apermo-score-cards' ) || 'Select player...' }</option>
							${ playerOptions }
						</select>
					</div>
				</div>

				<div class="asc-pool-form__winner-select">
					<label>${ window.wp?.i18n?.__( 'Winner', 'apermo-score-cards' ) || 'Winner' }</label>
					<div class="asc-pool-form__winner-options"></div>
				</div>

				<div class="asc-pool-form__optional">
					<div class="asc-pool-form__field">
						<label>${ window.wp?.i18n?.__( 'Balls left (optional)', 'apermo-score-cards' ) || 'Balls left (optional)' }</label>
						<input type="number" class="asc-pool-form__balls-left" min="0" max="7" value="${ this.state.ballsLeft }" />
					</div>
					<div class="asc-pool-form__checkbox">
						<input type="checkbox" id="asc-pool-foul-${ this.blockId }" class="asc-pool-form__foul" ${ this.state.eightBallFoul ? 'checked' : '' } />
						<label for="asc-pool-foul-${ this.blockId }">${ window.wp?.i18n?.__( '8-ball foul', 'apermo-score-cards' ) || '8-ball foul' }</label>
					</div>
				</div>

				<div class="asc-pool-form__actions">
					<button type="button" class="asc-pool-form__cancel-btn">
						${ window.wp?.i18n?.__( 'Cancel', 'apermo-score-cards' ) || 'Cancel' }
					</button>
					<button type="button" class="asc-pool-form__submit-btn" disabled>
						${ isEditing
							? ( window.wp?.i18n?.__( 'Update', 'apermo-score-cards' ) || 'Update' )
							: ( window.wp?.i18n?.__( 'Add Game', 'apermo-score-cards' ) || 'Add Game' )
						}
					</button>
				</div>

				<div class="asc-pool-form__message-container"></div>
			</div>
		`;

		// Set initial values
		if ( this.state.player1 ) {
			this.container.querySelector( '.asc-pool-form__player1' ).value = this.state.player1;
		}
		if ( this.state.player2 ) {
			this.container.querySelector( '.asc-pool-form__player2' ).value = this.state.player2;
		}

		this.updateWinnerOptions();
	}

	bindEvents() {
		const player1Select = this.container.querySelector( '.asc-pool-form__player1' );
		const player2Select = this.container.querySelector( '.asc-pool-form__player2' );
		const ballsLeftInput = this.container.querySelector( '.asc-pool-form__balls-left' );
		const foulCheckbox = this.container.querySelector( '.asc-pool-form__foul' );
		const cancelBtn = this.container.querySelector( '.asc-pool-form__cancel-btn' );
		const submitBtn = this.container.querySelector( '.asc-pool-form__submit-btn' );

		player1Select.addEventListener( 'change', ( e ) => {
			this.state.player1 = e.target.value ? parseInt( e.target.value, 10 ) : null;
			this.state.winnerId = null;
			this.updateWinnerOptions();
			this.updateSubmitButton();
		} );

		player2Select.addEventListener( 'change', ( e ) => {
			this.state.player2 = e.target.value ? parseInt( e.target.value, 10 ) : null;
			this.state.winnerId = null;
			this.updateWinnerOptions();
			this.updateSubmitButton();
		} );

		ballsLeftInput.addEventListener( 'change', ( e ) => {
			this.state.ballsLeft = e.target.value;
		} );

		foulCheckbox.addEventListener( 'change', ( e ) => {
			this.state.eightBallFoul = e.target.checked;
		} );

		cancelBtn.addEventListener( 'click', () => {
			this.onCancel();
		} );

		submitBtn.addEventListener( 'click', () => {
			this.submit();
		} );
	}

	updateWinnerOptions() {
		const container = this.container.querySelector( '.asc-pool-form__winner-options' );

		if ( ! this.state.player1 || ! this.state.player2 || this.state.player1 === this.state.player2 ) {
			container.innerHTML = `<p style="color: #666; font-style: italic;">${
				window.wp?.i18n?.__( 'Select two different players first', 'apermo-score-cards' ) || 'Select two different players first'
			}</p>`;
			return;
		}

		const player1 = this.players.find( ( p ) => p.id === this.state.player1 );
		const player2 = this.players.find( ( p ) => p.id === this.state.player2 );

		if ( ! player1 || ! player2 ) {
			return;
		}

		container.innerHTML = `
			<button type="button" class="asc-pool-form__winner-btn ${ this.state.winnerId === player1.id ? 'is-selected' : '' }" data-player-id="${ player1.id }">
				${ player1.avatarUrl ? `<img src="${ player1.avatarUrl }" alt="" />` : '' }
				<span>${ player1.name }</span>
			</button>
			<button type="button" class="asc-pool-form__winner-btn ${ this.state.winnerId === player2.id ? 'is-selected' : '' }" data-player-id="${ player2.id }">
				${ player2.avatarUrl ? `<img src="${ player2.avatarUrl }" alt="" />` : '' }
				<span>${ player2.name }</span>
			</button>
		`;

		// Bind winner button events
		container.querySelectorAll( '.asc-pool-form__winner-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				this.state.winnerId = parseInt( btn.dataset.playerId, 10 );
				container.querySelectorAll( '.asc-pool-form__winner-btn' ).forEach( ( b ) => {
					b.classList.toggle( 'is-selected', parseInt( b.dataset.playerId, 10 ) === this.state.winnerId );
				} );
				this.updateSubmitButton();
			} );
		} );
	}

	updateSubmitButton() {
		const submitBtn = this.container.querySelector( '.asc-pool-form__submit-btn' );
		const isValid = this.state.player1 && this.state.player2 &&
			this.state.player1 !== this.state.player2 && this.state.winnerId;
		submitBtn.disabled = ! isValid || this.state.isSubmitting;
	}

	showMessage( message, type = 'error' ) {
		const container = this.container.querySelector( '.asc-pool-form__message-container' );
		container.innerHTML = `<div class="asc-pool-form__message asc-pool-form__message--${ type }">${ message }</div>`;
	}

	async submit() {
		if ( this.state.isSubmitting ) {
			return;
		}

		this.state.isSubmitting = true;
		this.updateSubmitButton();

		const newGame = {
			player1: this.state.player1,
			player2: this.state.player2,
			winnerId: this.state.winnerId,
		};

		if ( this.state.ballsLeft !== '' && this.state.ballsLeft !== null ) {
			newGame.ballsLeft = parseInt( this.state.ballsLeft, 10 );
		}

		if ( this.state.eightBallFoul ) {
			newGame.eightBallFoul = true;
		}

		// Update games array
		let updatedGames;
		if ( this.editIndex !== null ) {
			updatedGames = [ ...this.games ];
			updatedGames[ this.editIndex ] = newGame;
		} else {
			updatedGames = [ ...this.games, newGame ];
		}

		// Calculate positions for evening summary
		const positions = this.calculatePositions( updatedGames );

		// Extract player IDs from players array
		const playerIds = this.players.map( ( p ) => p.id );

		try {
			const response = await fetch(
				`${ this.restUrl }/posts/${ this.postId }/games/${ this.blockId }`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.nonce,
					},
					body: JSON.stringify( {
						gameType: 'pool',
						playerIds,
						games: updatedGames,
						positions,
						status: 'in_progress',
					} ),
				}
			);

			if ( ! response.ok ) {
				const error = await response.json().catch( () => ( {} ) );
				throw new Error( error.message || 'Failed to save game' );
			}

			this.onSave();
		} catch ( error ) {
			console.error( 'Failed to save pool game:', error );
			this.showMessage( error.message || 'Failed to save game. Please try again.' );
			this.state.isSubmitting = false;
			this.updateSubmitButton();
		}
	}

	/**
	 * Calculate positions based on points with tiebreakers.
	 * Scoring: 1 point per game + 2 bonus for winning (win = 3pts, loss = 1pt).
	 */
	calculatePositions( games ) {
		// Build stats
		const stats = {};
		this.players.forEach( ( p ) => {
			stats[ p.id ] = { wins: 0, losses: 0, points: 0, headToHead: {} };
		} );

		games.forEach( ( g ) => {
			const winnerId = g.winnerId;
			const loserId = g.winnerId === g.player1 ? g.player2 : g.player1;

			if ( stats[ winnerId ] ) {
				stats[ winnerId ].wins++;
				stats[ winnerId ].points += 3; // 1 point for playing + 2 for winning = 3.
				if ( ! stats[ winnerId ].headToHead[ loserId ] ) {
					stats[ winnerId ].headToHead[ loserId ] = { wins: 0, losses: 0 };
				}
				stats[ winnerId ].headToHead[ loserId ].wins++;
			}

			if ( stats[ loserId ] ) {
				stats[ loserId ].losses++;
				stats[ loserId ].points += 1; // 1 point for playing.
				if ( ! stats[ loserId ].headToHead[ winnerId ] ) {
					stats[ loserId ].headToHead[ winnerId ] = { wins: 0, losses: 0 };
				}
				stats[ loserId ].headToHead[ winnerId ].losses++;
			}
		} );

		// Sort players
		const sorted = Object.entries( stats ).sort( ( [ aId, a ], [ bId, b ] ) => {
			// 1. Points descending.
			if ( a.points !== b.points ) {
				return b.points - a.points;
			}

			// 2. Win%
			const aTotal = a.wins + a.losses;
			const bTotal = b.wins + b.losses;
			const aPct = aTotal > 0 ? a.wins / aTotal : 0;
			const bPct = bTotal > 0 ? b.wins / bTotal : 0;
			if ( aPct !== bPct ) {
				return bPct - aPct;
			}

			// 3. H2H
			const h2h = a.headToHead[ bId ];
			if ( h2h ) {
				const diff = h2h.wins - h2h.losses;
				if ( diff !== 0 ) {
					return -diff;
				}
			}

			return 0;
		} );

		// Assign positions with tie handling
		const positions = {};
		let currentPos = 1;
		let prevKey = null;

		sorted.forEach( ( [ playerId, s ], index ) => {
			const total = s.wins + s.losses;
			const pct = total > 0 ? Math.round( ( s.wins / total ) * 10000 ) : 0;
			const key = `${ s.points }-${ pct }`;

			if ( key !== prevKey ) {
				currentPos = index + 1;
				prevKey = key;
			}

			positions[ playerId ] = currentPos;
		} );

		return positions;
	}
}

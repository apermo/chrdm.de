/**
 * Darts Score Form - Frontend Component
 *
 * Vanilla JavaScript form for entering darts scores on the frontend.
 */

export default class DartsScoreForm {
	constructor( container, blockData ) {
		this.container = container;
		this.postId = blockData.postId;
		this.blockId = blockData.blockId;
		this.startingScore = parseInt( blockData.startingScore, 10 ) || 501;
		this.playerIds = JSON.parse( blockData.playerIds || '[]' );
		this.players = JSON.parse( blockData.players || '[]' );
		this.existingGame = blockData.game ? JSON.parse( blockData.game ) : null;

		this.render();
		this.bindEvents();
	}

	render() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };
		const existingScores = this.existingGame?.scores || {};
		const existingRound = this.existingGame?.finishedRound || '';
		const isEditing = !! this.existingGame;

		this.container.innerHTML = `
			<form class="asc-darts-form">
				${ isEditing ? `<h4 class="asc-darts-form__title">${ __( 'Edit Results', 'apermo-score-cards' ) }</h4>` : '' }
				<table class="asc-darts-form__table">
					<thead>
						<tr>
							<th>${ __( 'Player', 'apermo-score-cards' ) }</th>
							<th>${ __( 'Remaining Score', 'apermo-score-cards' ) }</th>
						</tr>
					</thead>
					<tbody>
						${ this.players.map( ( player ) => {
							const existingScore = existingScores[ player.id ]?.finalScore ?? '';
							return `
							<tr class="asc-darts-form__row" data-player-id="${ player.id }">
								<td class="asc-darts-form__player">
									${ player.avatarUrl ? `<img src="${ player.avatarUrl }" alt="" class="asc-darts-form__avatar" />` : '' }
									<span>${ this.escapeHtml( player.name ) }</span>
								</td>
								<td class="asc-darts-form__score">
									<input
										type="number"
										name="score-${ player.id }"
										min="0"
										max="${ this.startingScore }"
										placeholder="0-${ this.startingScore }"
										class="asc-darts-form__input"
										value="${ existingScore }"
										required
									/>
									<span class="asc-darts-form__error" hidden></span>
								</td>
							</tr>
						`; } ).join( '' ) }
					</tbody>
				</table>

				<div class="asc-darts-form__round-input">
					<label for="asc-game-round">${ __( 'Finished after round', 'apermo-score-cards' ) }</label>
					<input
						type="number"
						id="asc-game-round"
						name="game-round"
						min="1"
						placeholder="${ __( 'Optional', 'apermo-score-cards' ) }"
						class="asc-darts-form__input"
						value="${ existingRound }"
					/>
				</div>

				<p class="asc-darts-form__help">
					${ __( 'Enter 0 for players who finished. The winner is determined by lowest remaining score.', 'apermo-score-cards' ) }
				</p>

				<div class="asc-darts-form__actions">
					<button type="submit" class="asc-darts-form__submit">
						${ isEditing ? __( 'Update Results', 'apermo-score-cards' ) : __( 'Save Results', 'apermo-score-cards' ) }
					</button>
				</div>

				<div class="asc-darts-form__message" hidden></div>
			</form>
		`;
	}

	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	bindEvents() {
		const form = this.container.querySelector( 'form' );
		form.addEventListener( 'submit', ( e ) => this.handleSubmit( e ) );
	}

	async handleSubmit( e ) {
		e.preventDefault();

		const form = e.target;
		const submitBtn = form.querySelector( '.asc-darts-form__submit' );
		const messageEl = form.querySelector( '.asc-darts-form__message' );

		// Get the global round number
		const gameRoundInput = form.querySelector( 'input[name="game-round"]' );
		const gameRound = gameRoundInput.value.trim() ? parseInt( gameRoundInput.value.trim(), 10 ) : null;

		// Collect and validate scores
		const formattedScores = {};
		let hasError = false;

		this.playerIds.forEach( ( playerId ) => {
			const scoreInput = form.querySelector( `input[name="score-${ playerId }"]` );
			const errorEl = scoreInput.parentElement.querySelector( '.asc-darts-form__error' );

			const scoreValue = scoreInput.value.trim();

			// Validate
			if ( scoreValue === '' ) {
				errorEl.textContent = window.wp?.i18n?.__( 'Score is required', 'apermo-score-cards' ) || 'Score is required';
				errorEl.hidden = false;
				hasError = true;
				return;
			}

			const score = parseInt( scoreValue, 10 );
			if ( isNaN( score ) || score < 0 || score > this.startingScore ) {
				errorEl.textContent = `Score must be between 0 and ${ this.startingScore }`;
				errorEl.hidden = false;
				hasError = true;
				return;
			}

			errorEl.hidden = true;

			formattedScores[ playerId ] = {
				finalScore: score,
			};
		} );

		if ( hasError ) {
			return;
		}

		// Calculate positions and winners (lowest score wins)
		const { positions, winnerIds } = this.calculatePositions( formattedScores );
		const finalScores = {};
		Object.entries( formattedScores ).forEach( ( [ id, data ] ) => {
			finalScores[ id ] = data.finalScore;
		} );

		// Disable form during submission
		submitBtn.disabled = true;
		submitBtn.textContent = window.wp?.i18n?.__( 'Saving...', 'apermo-score-cards' ) || 'Saving...';

		try {
			const response = await this.saveGame( {
				gameType: 'darts',
				playerIds: this.playerIds,
				status: 'completed',
				scores: formattedScores,
				finalScores,
				positions,
				finishedRound: gameRound,
				winnerIds,
				winnerId: winnerIds[ 0 ] || null,
			} );

			// Reload page to show results
			window.location.reload();
		} catch ( error ) {
			console.error( 'Failed to save game:', error );
			messageEl.textContent = error.message || 'Failed to save. Please try again.';
			messageEl.className = 'asc-darts-form__message asc-darts-form__message--error';
			messageEl.hidden = false;

			submitBtn.disabled = false;
			submitBtn.textContent = window.wp?.i18n?.__( 'Save Results', 'apermo-score-cards' ) || 'Save Results';
		}
	}

	calculatePositions( scores ) {
		const entries = Object.entries( scores );

		// Sort by score (lowest first for darts)
		entries.sort( ( a, b ) => a[ 1 ].finalScore - b[ 1 ].finalScore );

		const positions = {};
		let currentPosition = 1;
		let previousScore = null;

		entries.forEach( ( [ id, data ], index ) => {
			if ( data.finalScore !== previousScore ) {
				currentPosition = index + 1;
				previousScore = data.finalScore;
			}
			positions[ id ] = currentPosition;
		} );

		// Winners are all players in position 1
		const winnerIds = Object.entries( positions )
			.filter( ( [ , pos ] ) => pos === 1 )
			.map( ( [ id ] ) => parseInt( id, 10 ) );

		return { positions, winnerIds };
	}

	async saveGame( gameData ) {
		const config = window.apermoScoreCards || {};
		const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
		const nonce = config.restNonce;

		const response = await fetch(
			`${ restUrl }/posts/${ this.postId }/games/${ this.blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( gameData ),
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Request failed' );
		}

		return response.json();
	}
}

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

		this.scores = {};
		this.playerIds.forEach( ( id ) => {
			this.scores[ id ] = { finalScore: '', finishedRound: '' };
		} );

		this.render();
		this.bindEvents();
	}

	render() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };

		this.container.innerHTML = `
			<form class="asc-darts-form">
				<table class="asc-darts-form__table">
					<thead>
						<tr>
							<th>${ __( 'Player', 'apermo-score-cards' ) }</th>
							<th>${ __( 'Final Score', 'apermo-score-cards' ) }</th>
							<th>${ __( 'Finished Round', 'apermo-score-cards' ) }</th>
						</tr>
					</thead>
					<tbody>
						${ this.players.map( ( player ) => `
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
										required
									/>
									<span class="asc-darts-form__error" hidden></span>
								</td>
								<td class="asc-darts-form__round">
									<input
										type="number"
										name="round-${ player.id }"
										min="1"
										placeholder="${ __( 'Optional', 'apermo-score-cards' ) }"
										class="asc-darts-form__input"
									/>
								</td>
							</tr>
						` ).join( '' ) }
					</tbody>
				</table>

				<div class="asc-darts-form__actions">
					<button type="submit" class="asc-darts-form__submit">
						${ __( 'Save Results', 'apermo-score-cards' ) }
					</button>
				</div>

				<p class="asc-darts-form__help">
					${ __( 'Enter 0 for players who finished. Winners are determined by who reached 0 first (lowest round number).', 'apermo-score-cards' ) }
				</p>

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

		// Collect and validate scores
		const formattedScores = {};
		let hasError = false;

		this.playerIds.forEach( ( playerId ) => {
			const scoreInput = form.querySelector( `input[name="score-${ playerId }"]` );
			const roundInput = form.querySelector( `input[name="round-${ playerId }"]` );
			const errorEl = scoreInput.parentElement.querySelector( '.asc-darts-form__error' );

			const scoreValue = scoreInput.value.trim();
			const roundValue = roundInput.value.trim();

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
				finishedRound: roundValue ? parseInt( roundValue, 10 ) : null,
			};
		} );

		if ( hasError ) {
			return;
		}

		// Determine winners
		const winnerIds = this.determineWinners( formattedScores );
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
				rounds: [],
				scores: formattedScores,
				finalScores,
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

	determineWinners( scores ) {
		const entries = Object.entries( scores );

		// Find players who finished (score = 0)
		const finishers = entries.filter( ( [ , data ] ) => data.finalScore === 0 );

		if ( finishers.length > 0 ) {
			// Sort by round (lower is better)
			finishers.sort( ( a, b ) => {
				const roundA = a[ 1 ].finishedRound || Infinity;
				const roundB = b[ 1 ].finishedRound || Infinity;
				return roundA - roundB;
			} );

			// Winners are those with the lowest round
			const bestRound = finishers[ 0 ][ 1 ].finishedRound || Infinity;
			return finishers
				.filter( ( [ , data ] ) => ( data.finishedRound || Infinity ) === bestRound )
				.map( ( [ id ] ) => parseInt( id, 10 ) );
		}

		// No one finished - lowest score wins
		const minScore = Math.min( ...entries.map( ( [ , data ] ) => data.finalScore ) );
		return entries
			.filter( ( [ , data ] ) => data.finalScore === minScore )
			.map( ( [ id ] ) => parseInt( id, 10 ) );
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

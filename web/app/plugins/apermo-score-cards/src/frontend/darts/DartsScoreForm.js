/**
 * Darts Score Form - Frontend Component
 *
 * Form for entering darts scores on the frontend.
 * Extends BaseGameForm for shared functionality.
 */

import {
	BaseGameForm,
	__,
	escapeHtml,
	calculatePositionsLowerWins,
	getWinnerIds,
	parseJsonAttr,
} from '../base';

export default class DartsScoreForm extends BaseGameForm {
	/**
	 * Create a new DartsScoreForm.
	 *
	 * @param {HTMLElement} container DOM element to render the form into.
	 * @param {Object}      blockData Block data from data attributes.
	 */
	constructor( container, blockData ) {
		// Parse options from block data
		const options = {
			postId: blockData.postId,
			blockId: blockData.blockId,
			players: parseJsonAttr( blockData.players, [] ),
			onSave: () => window.location.reload(),
		};

		super( container, options );

		this.startingScore = parseInt( blockData.startingScore, 10 ) || 501;
		this.playerIds = parseJsonAttr( blockData.playerIds, [] );
		this.existingGame = parseJsonAttr( blockData.game, null );
		this.isEditing = !! this.existingGame;

		this.render();
	}

	// =========================================================================
	// Abstract method implementations
	// =========================================================================

	getGameType() {
		return 'darts';
	}

	getCssPrefix() {
		return 'asc-darts-form';
	}

	getFormTitle() {
		return this.isEditing ? __( 'Edit Results' ) : '';
	}

	getSubmitText() {
		return this.isEditing ? __( 'Update Results' ) : __( 'Save Results' );
	}

	showCancelButton() {
		return false;
	}

	renderFormContent() {
		const existingScores = this.existingGame?.scores || {};
		const existingRound = this.existingGame?.finishedRound || '';

		return `
			<table class="asc-darts-form__table">
				<thead>
					<tr>
						<th>${ __( 'Player' ) }</th>
						<th>${ __( 'Remaining Score' ) }</th>
					</tr>
				</thead>
				<tbody>
					${ this.players
						.map( ( player ) => {
							const existingScore =
								existingScores[ player.id ]?.finalScore ?? '';
							return `
						<tr class="asc-darts-form__row" data-player-id="${ player.id }">
							<td>
								<div class="asc-darts-form__player">
									${
										player.avatarUrl
											? `<img src="${ escapeHtml(
													player.avatarUrl
											  ) }" alt="" class="asc-darts-form__avatar" />`
											: ''
									}
									<span>${ escapeHtml( player.name ) }</span>
								</div>
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
					`;
						} )
						.join( '' ) }
				</tbody>
			</table>

			<div class="asc-darts-form__round-input">
				<label for="asc-game-round">${ __( 'Finished after round' ) }</label>
				<input
					type="number"
					id="asc-game-round"
					name="game-round"
					min="1"
					placeholder="${ __( 'Optional' ) }"
					class="asc-darts-form__input"
					value="${ existingRound }"
				/>
			</div>

			<p class="asc-darts-form__help">
				${ __(
					'Enter 0 for players who finished. The winner is determined by lowest remaining score.'
				) }
			</p>
		`;
	}

	validateForm() {
		const form = this.container.querySelector( 'form' );
		let hasError = false;

		this.playerIds.forEach( ( playerId ) => {
			const scoreInput = form.querySelector(
				`input[name="score-${ playerId }"]`
			);
			const errorEl = scoreInput.parentElement.querySelector(
				'.asc-darts-form__error'
			);
			const scoreValue = scoreInput.value.trim();

			// Clear previous validation
			scoreInput.classList.remove( 'has-error' );
			errorEl.hidden = true;

			// Validate required
			if ( scoreValue === '' ) {
				errorEl.textContent = __( 'Score is required' );
				errorEl.hidden = false;
				scoreInput.classList.add( 'has-error' );
				hasError = true;
				return;
			}

			// Validate range
			const score = parseInt( scoreValue, 10 );
			if ( isNaN( score ) || score < 0 || score > this.startingScore ) {
				errorEl.textContent = `${ __(
					'Score must be between 0 and'
				) } ${ this.startingScore }`;
				errorEl.hidden = false;
				scoreInput.classList.add( 'has-error' );
				hasError = true;
			}
		} );

		return ! hasError;
	}

	getFormData() {
		const form = this.container.querySelector( 'form' );

		// Get round number
		const gameRoundInput = form.querySelector( 'input[name="game-round"]' );
		const gameRound = gameRoundInput.value.trim()
			? parseInt( gameRoundInput.value.trim(), 10 )
			: null;

		// Collect scores
		const formattedScores = {};
		const finalScores = {};

		this.playerIds.forEach( ( playerId ) => {
			const scoreInput = form.querySelector(
				`input[name="score-${ playerId }"]`
			);
			const score = parseInt( scoreInput.value.trim(), 10 );

			formattedScores[ playerId ] = { finalScore: score };
			finalScores[ playerId ] = score;
		} );

		// Calculate positions (lower is better for darts)
		const positions = calculatePositionsLowerWins( finalScores );
		const winnerIds = getWinnerIds( positions );

		return {
			gameType: 'darts',
			playerIds: this.playerIds,
			status: 'completed',
			scores: formattedScores,
			finalScores,
			positions,
			finishedRound: gameRound,
			winnerIds,
			winnerId: winnerIds[ 0 ] || null,
		};
	}
}

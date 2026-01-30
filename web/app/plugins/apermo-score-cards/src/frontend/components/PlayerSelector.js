/**
 * Frontend Player Selector Component
 *
 * Reusable vanilla JavaScript component for selecting players.
 */

export default class PlayerSelector {
	constructor( container, options = {} ) {
		this.container = container;
		this.postId = options.postId;
		this.blockId = options.blockId;
		this.selectedPlayerIds = options.selectedPlayerIds || [];
		this.lockedPlayerIds = options.lockedPlayerIds || []; // Players that cannot be removed
		this.minPlayers = options.minPlayers || 2;
		this.maxPlayers = options.maxPlayers || 10;
		this.onSave = options.onSave || ( () => window.location.reload() );
		this.onCancel = options.onCancel || null;

		this.players = [];
		this.isLoading = true;

		this.render();
		this.fetchPlayers();
	}

	async fetchPlayers() {
		const config = window.apermoScoreCards || {};
		const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';

		try {
			const response = await fetch( `${ restUrl }/players`, {
				headers: {
					'X-WP-Nonce': config.restNonce,
				},
			} );

			if ( ! response.ok ) {
				throw new Error( 'Failed to fetch players' );
			}

			this.players = await response.json();
			this.isLoading = false;
			this.render();
		} catch ( error ) {
			console.error( 'Failed to fetch players:', error );
			this.isLoading = false;
			this.render();
		}
	}

	render() {
		const { __ } = window.wp?.i18n || { __: ( s ) => s };

		if ( this.isLoading ) {
			this.container.innerHTML = `
				<div class="asc-player-selector asc-player-selector--loading">
					<span class="asc-player-selector__spinner"></span>
					<span>${ __( 'Loading players...', 'apermo-score-cards' ) }</span>
				</div>
			`;
			return;
		}

		if ( this.players.length === 0 ) {
			this.container.innerHTML = `
				<div class="asc-player-selector asc-player-selector--empty">
					<p>${ __( 'No players found.', 'apermo-score-cards' ) }</p>
				</div>
			`;
			return;
		}

		const selectedCount = this.selectedPlayerIds.length;
		const canSelectMore = selectedCount < this.maxPlayers;
		const canSave = selectedCount >= this.minPlayers;

		const hasLockedPlayers = this.lockedPlayerIds.length > 0;

		this.container.innerHTML = `
			<div class="asc-player-selector">
				<div class="asc-player-selector__header">
					<span class="asc-player-selector__title">${ __( 'Players', 'apermo-score-cards' ) }</span>
					<span class="asc-player-selector__count">
						${ selectedCount } / ${ this.minPlayers }-${ this.maxPlayers }
					</span>
				</div>
				${ hasLockedPlayers ? `
					<p class="asc-player-selector__locked-hint">
						${ __( 'Players with games cannot be removed.', 'apermo-score-cards' ) }
					</p>
				` : '' }
				<div class="asc-player-selector__list">
					${ this.players.map( ( player ) => {
						const isSelected = this.selectedPlayerIds.includes( player.id );
						const isLocked = this.lockedPlayerIds.includes( player.id );
						const isDisabled = ( ! isSelected && ! canSelectMore ) || ( isSelected && isLocked );
						return `
							<label class="asc-player-selector__item ${ isSelected ? 'is-selected' : '' } ${ isDisabled ? 'is-disabled' : '' } ${ isLocked ? 'is-locked' : '' }">
								<input
									type="checkbox"
									value="${ player.id }"
									${ isSelected ? 'checked' : '' }
									${ isDisabled ? 'disabled' : '' }
									class="asc-player-selector__checkbox"
								/>
								<span class="asc-player-selector__player">
									${ player.avatarUrl ? `<img src="${ player.avatarUrl }" alt="" class="asc-player-selector__avatar" />` : '' }
									<span class="asc-player-selector__name">${ this.escapeHtml( player.name ) }</span>
									${ isLocked ? `<span class="asc-player-selector__lock">🔒</span>` : '' }
								</span>
							</label>
						`;
					} ).join( '' ) }
				</div>
				<div class="asc-player-selector__actions">
					<button type="button" class="asc-player-selector__save" ${ ! canSave ? 'disabled' : '' }>
						${ __( 'Save Players', 'apermo-score-cards' ) }
					</button>
					<button type="button" class="asc-player-selector__cancel">
						${ __( 'Cancel', 'apermo-score-cards' ) }
					</button>
				</div>
				${ ! canSave ? `
					<p class="asc-player-selector__hint">
						${ __( 'Select at least', 'apermo-score-cards' ) } ${ this.minPlayers } ${ __( 'players', 'apermo-score-cards' ) }
					</p>
				` : '' }
				<div class="asc-player-selector__message" hidden></div>
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
		const checkboxes = this.container.querySelectorAll( '.asc-player-selector__checkbox' );
		const saveBtn = this.container.querySelector( '.asc-player-selector__save' );
		const cancelBtn = this.container.querySelector( '.asc-player-selector__cancel' );

		checkboxes.forEach( ( checkbox ) => {
			checkbox.addEventListener( 'change', ( e ) => {
				const playerId = parseInt( e.target.value, 10 );
				if ( e.target.checked ) {
					if ( ! this.selectedPlayerIds.includes( playerId ) ) {
						this.selectedPlayerIds.push( playerId );
					}
				} else {
					this.selectedPlayerIds = this.selectedPlayerIds.filter( ( id ) => id !== playerId );
				}
				this.render();
			} );
		} );

		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', () => this.save() );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => {
				this.container.hidden = true;
				this.container.innerHTML = '';
				if ( this.onCancel ) {
					this.onCancel();
				}
			} );
		}
	}

	async save() {
		const config = window.apermoScoreCards || {};
		const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
		const nonce = config.restNonce;

		const saveBtn = this.container.querySelector( '.asc-player-selector__save' );
		const messageEl = this.container.querySelector( '.asc-player-selector__message' );

		saveBtn.disabled = true;
		saveBtn.textContent = window.wp?.i18n?.__( 'Saving...', 'apermo-score-cards' ) || 'Saving...';

		try {
			const response = await fetch(
				`${ restUrl }/posts/${ this.postId }/blocks/${ this.blockId }/players`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( { playerIds: this.selectedPlayerIds } ),
				}
			);

			if ( ! response.ok ) {
				const error = await response.json().catch( () => ( {} ) );
				throw new Error( error.message || 'Failed to save' );
			}

			this.onSave( this.selectedPlayerIds );
		} catch ( error ) {
			console.error( 'Failed to save players:', error );
			messageEl.textContent = error.message || 'Failed to save. Please try again.';
			messageEl.className = 'asc-player-selector__message asc-player-selector__message--error';
			messageEl.hidden = false;

			saveBtn.disabled = false;
			saveBtn.textContent = window.wp?.i18n?.__( 'Save Players', 'apermo-score-cards' ) || 'Save Players';
		}
	}
}

/**
 * Evening Summary - Editor Component
 *
 * No configuration needed - automatically shows all games from the post.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { v4 as uuidv4 } from 'uuid';

export default function Edit( { attributes, setAttributes } ) {
	const { blockId } = attributes;
	const blockProps = useBlockProps();

	// Generate block ID if not set
	useEffect( () => {
		if ( ! blockId ) {
			setAttributes( { blockId: uuidv4() } );
		}
	}, [ blockId, setAttributes ] );

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="awards"
				label={ __( 'Evening Summary', 'apermo-score-cards' ) }
				instructions={ __(
					'This block automatically displays a summary of all score card games in this post and calculates the evening winner.',
					'apermo-score-cards'
				) }
			>
				<p className="asc-evening-summary__editor-note">
					{ __(
						'The summary will appear on the frontend once games have been played.',
						'apermo-score-cards'
					) }
				</p>
			</Placeholder>
		</div>
	);
}

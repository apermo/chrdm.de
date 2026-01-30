# CLAUDE.md - Apermo Score Cards

This file provides guidance for Claude Code when working with this plugin.

## Plugin Overview

WordPress Gutenberg blocks for card and board game score cards with automatic calculations. Players are WordPress users managed via the admin. Game data is stored as post meta on the post containing the score card block.

## Architecture

### Directory Structure

```
apermo-score-cards/
├── apermo-score-cards.php      # Main plugin file
├── includes/
│   ├── class-capabilities.php  # Roles, capabilities, permissions
│   ├── class-players.php       # Player management (WP users)
│   ├── class-games.php         # Game data (post meta)
│   ├── class-rest-api.php      # REST API endpoints
│   ├── class-block-bindings.php # Block Bindings API
│   └── class-blocks.php        # Block registration
├── src/
│   ├── index.js                # Main JS entry point
│   ├── editor.scss             # Editor styles
│   ├── style.scss              # Frontend styles
│   ├── stores/
│   │   └── index.js            # WordPress data store
│   ├── components/
│   │   ├── index.js            # Component exports
│   │   ├── PlayerSelector.js   # Player selection UI
│   │   └── ScoreTable.js       # Score display table
│   └── blocks/
│       └── [game-name]/        # Each game gets its own folder
│           ├── block.json      # Block metadata
│           ├── index.js        # Block registration
│           ├── edit.js         # Editor component
│           ├── save.js         # Save component
│           └── scoring.js      # Game-specific scoring logic
├── build/                      # Compiled assets (gitignored)
├── languages/                  # Translations
└── package.json                # NPM dependencies
```

### Data Storage

**Players**: All WordPress users are available as players. Manage via WordPress Users admin.

### Roles & Capabilities

**Custom Role**: `scorecard_maintainer` (Score Card Maintainer)
- Has `read` and `manage_scorecards` capabilities

**Custom Capability**: `manage_scorecards`
- Assigned to: Administrators, Editors, Score Card Maintainers
- Required to create/edit/delete game scores

**Time Window**: Users with `manage_scorecards` can only edit scores within **8 hours** of the parent post's last update (`post_modified`). After 8 hours, scores are locked.

**Permission Check Flow**:
1. User must have `manage_scorecards` capability
2. Parent post must have been modified within the last 8 hours
3. Both conditions must be true to edit scores (frontend)

**Games**: Stored as post meta with key `_asc_game_{blockId}`. Structure:
```json
{
  "blockId": "unique-block-id",
  "gameType": "wizard",
  "playerIds": [1, 2, 3],
  "status": "in_progress",
  "rounds": [
    { "1": { "bid": 0, "won": 0 }, "2": { "bid": 1, "won": 1 } }
  ],
  "finalScores": { "1": 150, "2": 120 },
  "winnerId": 1,
  "startedAt": "2024-01-01T12:00:00+00:00",
  "completedAt": null
}
```

### REST API Endpoints

Base: `/wp-json/apermo-score-cards/v1`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/players` | Get all players (public) |
| GET | `/posts/{id}/can-manage` | Check edit permissions |
| GET | `/posts/{id}/games/{blockId}` | Get game data |
| POST | `/posts/{id}/games/{blockId}` | Create/update game |
| DELETE | `/posts/{id}/games/{blockId}` | Delete game |
| POST | `/posts/{id}/games/{blockId}/rounds` | Add round |
| PUT | `/posts/{id}/games/{blockId}/rounds/{index}` | Update round |
| POST | `/posts/{id}/games/{blockId}/complete` | Complete game |

### Block Bindings

Source: `apermo-score-cards/game-data`

Allows binding block attributes to game data using dot notation:
- `finalScores.1` - Player 1's final score
- `winnerId` - Winner's user ID
- `status` - Game status

## Adding a New Game Block

1. Create folder: `src/blocks/[game-name]/`

2. Create `block.json`:
```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "apermo-score-cards/[game-name]",
  "title": "Game Name Score Card",
  "category": "widgets",
  "icon": "games",
  "description": "Score card for Game Name",
  "textdomain": "apermo-score-cards",
  "attributes": {
    "blockId": { "type": "string" },
    "playerIds": { "type": "array", "default": [] }
  },
  "usesContext": ["postId"],
  "providesContext": {
    "apermo-score-cards/blockId": "blockId"
  },
  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "style": "file:./style-index.css"
}
```

3. Create `index.js`:
```js
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';

registerBlockType( metadata.name, {
  edit: Edit,
  save,
} );
```

4. Create `edit.js` with game-specific editor UI

5. Create `save.js` for frontend output

6. Create `scoring.js` with game-specific calculation logic

7. Import in `src/index.js`:
```js
import './blocks/[game-name]';
```

8. Register game type in PHP (optional, for backend awareness):
```php
Blocks::register_game_type( 'game-name', [
  'name'        => __( 'Game Name', 'apermo-score-cards' ),
  'minPlayers'  => 3,
  'maxPlayers'  => 6,
] );
```

## Development Commands

```bash
# Install dependencies
npm install

# Start development build with watch
npm start

# Production build
npm run build

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css
```

## Coding Standards

### Commits

Use [Conventional Commits](https://www.conventionalcommits.org/). Prefix with `(apermo-score-cards)` scope.

```
feat(apermo-score-cards): add new game block
fix(apermo-score-cards): resolve scoring bug
```

**Important**: Never add `Co-Authored-By` lines to commits.

### General
- Every file must end with exactly one newline (no more, no less), unless technically required otherwise

### PHP
- PHP 8.3+ required
- Strict typing enabled
- WordPress Coding Standards
- Namespace: `Apermo\ScoreCards`

### JavaScript
- ES6+ with JSX
- WordPress scripts and components
- Functional components with hooks

### CSS
- BEM naming: `.asc-component__element--modifier`
- SCSS for source files
- Mobile-first responsive design

## Game Scoring Patterns

Each game implements its own scoring in `scoring.js`. Common patterns:

### Simple Points (e.g., Yahtzee)
```js
export function calculateTotal( rounds, playerId ) {
  return rounds.reduce( ( sum, round ) => sum + ( round[ playerId ] || 0 ), 0 );
}
```

### Bid-Based (e.g., Wizard)
```js
export function calculateRoundScore( bid, won ) {
  if ( bid === won ) {
    return 20 + ( won * 10 );
  }
  return -10 * Math.abs( bid - won );
}
```

### Running Total
```js
export function calculateRunningTotals( rounds, playerId ) {
  let total = 0;
  return rounds.map( ( round ) => {
    total += calculateRoundScore( round[ playerId ] );
    return total;
  } );
}
```

## Testing

Test the plugin by:
1. Activating in WordPress
2. Adding users as players
3. Creating a post with a score card block
4. Playing through a game
5. Verifying scores save and display correctly

## Troubleshooting

**Block not appearing**: Run `npm run build` and check for JS errors

**Players not loading**: Check REST API endpoint `/wp-json/apermo-score-cards/v1/players`

**Game not saving**: Verify user has `edit_post` capability for the post

**Styles missing**: Ensure `build/` directory exists with compiled CSS
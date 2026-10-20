# Changelog

All notable changes to the Wikit Semantics plugin will be documented in this file.


## [3.0.0] - 2026-10-20

Versions 3.x are dedicated to GLPI 12 (2.x: GLPI 11, 1.x: GLPI 10).

### Added
- GLPI 12.0 compatibility
- Knowledge Base generation: choice of the parent article in the GLPI 12 knowledge base tree, with a suggested parent (article linked to the ticket category, or parent of the articles already linked to tickets of the same category)
- Knowledge Base generation: preview of who will be able to read the article, as visibility is inherited from the parent articles in GLPI 12
- Generated Knowledge Base articles are linked to the ticket they come from
- Editor AI assistant in the GLPI 12 knowledge base editor: a magic wand button in the menu shown on a text selection corrects, formats or translates the selected text

### Changed
- Calls to Wikit Semantics use the GLPI HTTP client: GLPI proxy settings and proxy exclusions are applied (the plugin can be selected in the proxy exclusions), and private network addresses must be allowed in `GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS` (see README)
- Classes moved from `inc/` to `src/`, in the `GlpiPlugin\Wikitsemantics` namespace; existing history is migrated to the new itemtypes
- Configuration form and AI answer views rendered with Twig templates
- Database tables created and upgraded with GLPI migrations instead of SQL files
- Plugin URLs: no more `/public` prefix (deprecated in GLPI 12) and absolute paths based on the GLPI root URL
- Maximum GLPI version set to 12.0.x, consistent with the plugins directory compatibility
- Development tooling aligned with the Teclib plugin template: php-cs-fixer, rector, phpstan, twigcs, eslint, licence headers; development files are no longer shipped in the release archives
- Unit tests (PHPUnit, GLPI test framework): configuration, Wikit Semantics API calls and streaming (mocked HTTP client), Knowledge Base placement and creation, profile rights

### Fixed
- Wikit Semantics API errors (e.g. invalid API key) are now reported in streaming mode instead of displaying an empty answer, Knowledge Base article or Editor AI result
- Warnings logged by GLPI about closed output buffers and unexpected output when streaming AI responses

### Removed
- GLPI 11 support: use the 2.x versions with GLPI 11
- CSRF tokens in AJAX requests, no longer used by GLPI 12
- Manual prerequisites checks, replaced by the requirements declared to GLPI



## [2.1.4] - 2026-10-07

### Fixed
- AI suggestion button missing on followup, task and solution forms with GLPI 11.0.9 and later (answer forms are now lazy-loaded by GLPI)



## [2.1.3] - 2026-10-02

### Security
- Strengthened access control on the plugin configuration
- API URL must now be a valid HTTPS URL
- AI-generated content is sanitized before being displayed
- Knowledge Base generation now only uses followups and tasks the current user is allowed to see

### Removed
- Unused internal code



## [2.1.2] - 2026-10-02

### Fixed
- JavaScript no longer fails to load ("Identifier 'observer' has already been declared") when another plugin or custom script declares a global with the same name; the plugin script is now scoped and protected against double inclusion



## [2.1.1] - 2026-06-01

### Changed
- Plugin is no longer active for unauthenticated users
- Wikit Semantics profile permissions can now be configured for simplified interface profiles



## [2.1.0] - 2026-05-13

### Added
- Editor AI assistant for rich text editors with correction, formatting and translation actions
- Knowledge Base article generation from ticket content
- Dedicated Knowledge Base tab on tickets
- Streaming endpoints for ticket answers, Knowledge Base generation and Editor AI
- Quoted sources retrieval and display for generated answers
- Separate Application IDs for ticket answers, Knowledge Base generation and Editor AI
- Feature toggles for streaming mode, Knowledge Base generation and Editor AI
- Additional profile rights for Knowledge Base and Editor AI features
- Upgrade SQL script for 2.1.0 configuration fields

### Changed
- Configuration page reorganized into common settings, ticket answers, Knowledge Base and Editor AI sections
- AI API payloads now clean GLPI rich text content before sending queries
- Ticket answer generation now returns query IDs for source lookup
- JavaScript initialization improved for dynamically loaded GLPI forms and TinyMCE editors
- Translations updated for new configuration labels and Editor AI actions


---

## [2.0.0] - 2025-12-30

### Breaking Changes
- **GLPI 11.0.0+ Required**: Minimum GLPI version increased from 10.0.0 to 11.0.0
- **PHP 8.2+ Required**: Minimum PHP version increased from 8.0 to 8.2
- **Incompatible with GLPI 10.x**: This version will NOT work with GLPI 10.x

### Added
- PHP coding standard validation (`.phpcs.xml.dist`)
- Twig template validation (`.twig_cs.dist.php`)
- JavaScript extracted to dedicated file (`js/wikitsemantics.js`)
- Twig template infrastructure (`templates/`)
- `WikitSemanticsAnswerGenerator` JavaScript class for modular code organization

### Changed
- **Complete Twig Migration**: All HTML now rendered via Twig templates
  - Modal UI moved to `templates/answer_modal.html.twig`
  - Uses `TemplateRenderer::getInstance()` for rendering
- **JavaScript Externalization**: All inline JavaScript removed
  - Centralized in `js/wikitsemantics.js`
  - Loaded via `Html::script()` method
  - Configuration passed via JSON to `WikitSemanticsAnswerGenerator` class
- **Migrated to GLPI 11 HTTP exception system**
  - `BadRequestHttpException` for 400 errors
  - `NotFoundHttpException` for 404 errors
  - `AccessDeniedHttpException` for 403 errors
- Replaced deprecated `Plugin::getWebDir()` with relative paths (`../plugins/wikitsemantics`)
- Removed deprecated `csrf_compliant` hook (now default in GLPI 11)
- Replaced hook string literals with `Hooks::POST_ITEM_FORM` constant
- Updated logging from `Toolbox::logError/logWarning` to `Toolbox::logDebug`
- Removed `include('../../../inc/includes.php')` from all files (automatic bootstrap in GLPI 11)
- Replaced `exit()` with `return` in streaming endpoints
- Reduced `inc/generateanswer.class.php`

### Fixed
- Plugin URL paths now use relative paths for GLPI 11 compatibility
- SSE (Server-Sent Events) error handling improved
- Rights checking order optimized (before headers in streaming mode)
- AJAX mode "Add to ticket" button functionality (event delegation)

---

## [1.1.0] - 2024-12-22

### Added
- GLPI 11.x compatibility
- GLPI compliance: mandatory prerequisite and config check functions
- Streaming responses from Wikit API with SSE protocol option
- "Ask AI" button for TicketTask items
- More granular rights management (READ + UPDATE)
- Comprehensive error logging with Toolbox class

### Changed
- GLPI compliance: Migration class instead of raw SQL
- Secured hook with complete parameter validation
- Enhanced error handling without exposing sensitive data

### Fixed
- Input validation for ticket IDs (filter_var)
- SQL injection protection with session checks
- Hook parameter validation (is_object checks)
- TicketTask "Add to ticket" button CSS selector

### Security
- Input validation on all AJAX endpoints
- SQL injection prevention
- Enhanced hook security
- API key encryption

---

## Branching Strategy

- **Branch `1.x`**: GLPI 10.0.0 - 10.9.99 (PHP 8.0+) - Maintenance only
- **Branch `2.x`**: GLPI 11.0.0+ (PHP 8.2+) - Active development

The two branches are **NOT** inter-compatible.

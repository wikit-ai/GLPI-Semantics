# Changelog

All notable changes to the Wikit Semantics plugin will be documented in this file.


## [2.2.0] - 2026-11-23

Backport for GLPI 11 of the features and fixes of the version 3.1.0 (GLPI 12).

### Added
- Support of the Wikit Semantics workflow applications (`/semantics-workflow/` API), for the three features, without any configuration change. The type of an application cannot be read with an API key: the classic API is called first, then the workflow API when it fails with an HTTP error (other than authentication and rate limit errors), and the type that answers is kept in the GLPI cache for the next calls (24 hours). An application that changes of type is detected again (an empty answer of the workflow API leads to the classic API). Quoted sources are not available for workflow applications.
- Ticket answer suggestion: the whole public conversation of the ticket (description, followups, tasks and solutions, in chronological order, with their author and date) is sent to the application as context, instead of the description only. Private followups and tasks are never sent; the oldest messages are omitted beyond 20,000 characters.

### Changed
- Performance: the Editor AI configuration is added to the GLPI pages (meta tag) instead of being loaded with an AJAX request on every page; the settings it needs are kept in the GLPI cache (cleared when the configuration is saved), so no database query is added to the pages
- Performance: a single DOM observer for the answer buttons and the Knowledge Base tabs, no more polling while waiting for TinyMCE
- Performance: the configuration is no longer read from the database each time the configuration class is instantiated (e.g. by the GLPI history and search), and the Profile tab no longer writes the plugin rights each time it is displayed
- Logs: the expected failure of the first API tried while detecting the type of an application is logged as an information, not as a warning; debug entries (each successful API call) are only written in the GLPI debug mode
- Streaming: an HTTP error of Wikit Semantics is now reported to the user instead of an empty answer

### Fixed
- Other GLPI pages no longer wait for the end of an AI generation (session lock released before calling Wikit Semantics)
- Streaming: text no longer lost when an event is split between two network packets, nor when the generated text contains "STOP"
- Streaming: generations longer than 30 seconds are no longer interrupted (limit raised to 180 seconds)
- Answers and Editor AI results are inserted through the TinyMCE API: the textarea, the undo history and the required state of the field are updated
- Editor AI assistant without streaming mode: an error was displayed instead of the result
- Words with underscores (e.g. identifiers) no longer turned into italics in the streamed answers
- Bulleted lists sent to Wikit Semantics without empty lines between the items



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

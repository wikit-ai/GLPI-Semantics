# Changelog

All notable changes to the Wikit Semantics plugin will be documented in this file.


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
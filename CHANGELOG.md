Changelog
All notable changes to the Wikit Semantics plugin will be documented in this file.

## [1.1.1] - 2026-01-09

### Changed
- Simplified to AJAX-only mode for better stability and reliability

### Fixed
- "Add to ticket" button functionality across all item types (ITILFollowup, ITILSolution, TicketTask)

### Removed
- Streaming mode (SSE) due to CSRF token incompatibility and in favor of stable AJAX implementation
- `is_streaming_enabled` configuration option and database field
- `/ajax/generateanswer_stream.php` endpoint

---
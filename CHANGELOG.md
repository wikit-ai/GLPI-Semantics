# Release 1.1.0

## Added
- GLPI 11.x compatibility
- GLPI compliance: mandatory prerequisite and config check functions
- Streaming responses from Wikit API with SSE protocol option
- "Ask AI" button for TicketTask items
- More granular rights management (READ + UPDATE)
- Comprehensive error logging with Toolbox class

## Changed
- GLPI compliance: Migration class instead of raw SQL
- Secured hook with complete parameter validation
- Enhanced error handling without exposing sensitive data

## Fixed
- Input validation for ticket IDs (filter_var)
- SQL injection protection with session checks
- Hook parameter validation (is_object checks)
- TicketTask "Add to ticket" button CSS selector

## Security
- Input validation on all AJAX endpoints
- SQL injection prevention
- Enhanced hook security
- API key encryption
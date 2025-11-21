# Wikit Semantics Plugin for GLPI

## Overview

The **Wikit Semantics** plugin brings AI-powered semantic search and intelligent question-answering capabilities to your GLPI installation. By connecting your GLPI instance to the Wikit Semantics API, you can leverage advanced natural language processing to help users get answers quickly for tickets.

## What Does It Do?

This plugin enables users to find in natural language a answer to tickets, context-aware answers based on your organization's knowledge base. Instead of manually searching through tickets, documentation, or FAQ entries, users can simply find solutions and get immediate, relevant responses.

### Key Benefits

- **Faster Support Resolution**: Get instant answers to common questions without waiting for human intervention
- **Knowledge Base Enhancement**: Leverage AI to make your existing GLPI data more accessible and useful
- **Seamless Integration**: Works directly within your GLPI interface - no need to switch between applications

## Features

- Direct integration with Wikit Semantics API
- Secure API authentication with organization and application-level controls
- Easy configuration through GLPI's admin interface
- Connection testing to verify API setup
- Support for GLPI 10.0+

## Screenshots

### Configuration Interface

![screenshot of config form](screenshots/config.png)

### Tickets-Answering in Action

*Screenshot to be added - Example of asking a question and receiving an answer*

### Integration with GLPI Forms

![screenshot of a ticket form](screenshots/form.png)

![screenshot of a response](screenshots/response.png)

## Requirements

- GLPI version 10.0.0 or higher
- Active Wikit Semantics account with:
  - API URL
  - Organization ID
  - Application ID
  - API Key

## Quick Setup

1. Install the plugin in your GLPI plugins directory
2. Activate the plugin from the GLPI interface
3. Navigate to the plugin configuration page
4. Enter your Wikit Semantics API credentials
5. Test the connection to verify everything is working
6. Start using AI-powered semantic search in your GLPI instance

## About Wikit Semantics

Wikit Semantics provides enterprise-grade AI solutions for intelligent information retrieval and knowledge management. Visit [Wikit](https://wikit.ai) for more information.

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer

## License

This plugin is licensed under the Apache 2.0 License.

## Support

For issues, questions, or contributions, please visit our [GitHub repository](https://github.com/wikit-ai/GLPI-Semantics).

---


**Copyright**: © 2025 Wikit

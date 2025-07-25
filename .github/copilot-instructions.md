# Copilot Instructions - Besteller (Employee Equipment Ordering System)

# Code Organization Guidance

When implementing, consider Symfony's structure:
- **Entities**: Core domain models in `src/Entity/`
- **Controllers**: Separate admin and user-facing controllers
- **Services**: Email service, task generation logic, dependency resolution
- **Forms**: Type-specific form builders for complex task configurations
- **Templates**: Twig templates for both web UI and email HTML

Always use Clean Code principles:
- **Readability**: Clear variable names, consistent formatting
- **Modularity**: Small, focused methods and classes
- **Documentation**: Inline comments for complex logic, clear method descriptions. All Inline COmments have to be in German
- **Testing**: Write unit tests for critical logic, especially form validation and email generation
- **Error Handling**: Graceful handling of form errors, user feedback on submission issues
- **Security**: Ensure CSRF protection on forms, validate all user inputs
- **Performance**: Optimize database queries, especially for submission history
- **Localization**: All user-facing text in German, no multi-language support required
- **Use docker**: User docker compose for local development and deployment, ensuring consistency across environments. All Commands and deployments should happen via Docker and Docker Compose. Documentation should include Docker setup instructions.
- **Makefile**: Provide a Makefile for common tasks (e.g., build, test, deploy) to streamline development workflows.

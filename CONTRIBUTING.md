# Contributing to Verify Email Change Bundle

Thank you for your interest in contributing to the Verify Email Change Bundle! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)

## Code of Conduct

This project adheres to professional standards of open source contribution. Be respectful, constructive, and collaborative in all interactions.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title**: Descriptive summary of the issue
- **Environment details**: PHP version, Symfony version, bundle version
- **Steps to reproduce**: Minimal code example that demonstrates the issue
- **Expected behavior**: What you expected to happen
- **Actual behavior**: What actually happened
- **Stack trace**: If applicable, include the full error message

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **Use case**: Explain the problem you're trying to solve
- **Proposed solution**: Describe your suggested approach
- **Alternatives considered**: Other approaches you've thought about
- **Breaking changes**: Whether this would require breaking changes

### Pull Requests

We actively welcome your pull requests:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add or update tests as needed
5. Ensure all tests pass
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git

### Installation

1. Clone the repository:
```bash
git clone https://github.com/Makraz/verify-email-change-bundle.git
cd verify-email-change-bundle
```

2. Install dependencies:
```bash
composer install
```

3. Run tests to ensure everything works:
```bash
vendor/bin/phpunit
```

## Coding Standards

### PHP Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
- Use strict types declaration (`declare(strict_types=1);`) in all PHP files
- Type hint all parameters and return types
- Use readonly properties where applicable (PHP 8.1+)

### Code Quality

- Write self-documenting code with clear variable and method names
- Add PHPDoc blocks for complex logic or public APIs
- Keep methods focused and single-purpose
- Avoid deep nesting (max 3 levels)

### Security

- Never store tokens in plain text
- Use constant-time comparison for token validation
- Validate all user input
- Follow OWASP security guidelines
- Consider timing attack vulnerabilities

### Symfony Best Practices

- Follow [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)
- Use dependency injection
- Leverage Symfony's service container
- Use events for extensibility points
- Keep bundle configuration minimal but flexible

## Testing

### Running Tests

Run the complete test suite:
```bash
vendor/bin/phpunit
```

Run specific test file:
```bash
vendor/bin/phpunit tests/EmailChange/EmailChangeHelperTest.php
```

Run with coverage (requires Xdebug):
```bash
vendor/bin/phpunit --coverage-html coverage
```

### Writing Tests

- Write tests for all new features
- Update tests when modifying existing features
- Aim for high code coverage
- Use meaningful test names that describe the scenario
- Follow AAA pattern: Arrange, Act, Assert

Example test structure:
```php
public function testItGeneratesSignatureForValidRequest(): void
{
    // Arrange
    $user = $this->createUser();
    $newEmail = 'new@example.com';

    // Act
    $signature = $this->helper->generateSignature('verify_route', $user, $newEmail);

    // Assert
    $this->assertInstanceOf(EmailChangeSignature::class, $signature);
    $this->assertStringContainsString('verify_route', $signature->getSignedUrl());
}
```

### Test Coverage

- New features should have corresponding tests
- Bug fixes should include regression tests
- Aim for at least 80% code coverage
- Critical security features should have 100% coverage

## Pull Request Process

### Before Submitting

- [ ] Tests pass locally (`vendor/bin/phpunit`)
- [ ] Code follows PSR-12 standards
- [ ] New features include tests
- [ ] Documentation is updated if needed
- [ ] Commit messages are clear and descriptive
- [ ] No merge conflicts with main branch

### PR Description

Include in your PR description:

- **What**: Brief description of the changes
- **Why**: Explanation of the motivation
- **How**: Technical approach if non-obvious
- **Testing**: How you tested the changes
- **Breaking changes**: List any breaking changes
- **Related issues**: Link to related issues

### Review Process

1. Automated tests will run on your PR
2. Maintainers will review your code
3. Address any requested changes
4. Once approved, a maintainer will merge your PR

### Commit Messages

Write clear, concise commit messages:

```
Add email validation before signature generation

- Validate email format using Symfony validator
- Throw InvalidEmailException for invalid emails
- Add tests for email validation edge cases

Fixes #123
```

## Branch Naming

Use descriptive branch names:

- `feature/add-custom-email-validator`
- `bugfix/fix-expiration-check`
- `docs/update-installation-guide`
- `refactor/simplify-token-generation`

## Documentation

### Code Documentation

- Add PHPDoc blocks for public methods
- Document complex algorithms
- Explain security-critical code
- Include `@throws` tags for exceptions

### User Documentation

Update relevant documentation when:

- Adding new features
- Changing public APIs
- Modifying configuration options
- Fixing bugs that affect usage

Documentation files to consider:
- `README.md`: High-level overview and quick start
- `GETTING_STARTED.md`: Detailed setup instructions
- `CHANGELOG.md`: Version history and changes

## Versioning

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

## Security Vulnerabilities

If you discover a security vulnerability:

1. **DO NOT** open a public issue
2. Email security concerns to: hamza@makraz.com
3. Include detailed steps to reproduce
4. Allow time for a fix before public disclosure

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Questions?

If you have questions about contributing:

- Open a discussion on GitHub
- Check existing issues and pull requests
- Review the documentation

## Recognition

Contributors will be recognized in:
- GitHub contributors list
- Release notes for significant contributions
- CHANGELOG.md for notable features

Thank you for making this project better!

# Test Suite

This directory contains comprehensive tests for the Verify Email Change Bundle, inspired by the test structure of [SymfonyCasts/reset-password-bundle](https://github.com/SymfonyCasts/reset-password-bundle).

## Test Structure

The test suite follows a three-tier testing pyramid:

```
tests/
├── Unit/                           # Unit tests for individual classes
│   ├── EmailChangeHelperTest.php
│   ├── EmailChangeTokenGeneratorTest.php
│   ├── EmailChangeRequestTest.php
│   ├── EmailChangeSignatureTest.php
│   └── TokenComponentsTest.php
├── Integration/                    # Tests for service configuration and autowiring
│   └── EmailChangeServiceDefinitionTest.php
├── Functional/                     # End-to-end workflow tests
│   └── EmailChangeWorkflowTest.php
└── Fixtures/                       # Test data and mock implementations
    ├── Entity/
    │   └── TestUser.php
    └── EmailChangeRequestTestRepository.php
```

## Running Tests

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
vendor/bin/phpunit --testsuite "Unit Tests"

# Integration tests only
vendor/bin/phpunit --testsuite "Integration Tests"

# Functional tests only
vendor/bin/phpunit --testsuite "Functional Tests"
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/EmailChangeHelperTest.php
```

### Run with Detailed Output

```bash
vendor/bin/phpunit --testdox
```

### Generate Code Coverage

Requires Xdebug or PCOV to be installed:

```bash
vendor/bin/phpunit --coverage-html coverage
```

Then open `coverage/index.html` in your browser.

## Test Coverage

### Unit Tests (78 tests)

**EmailChangeHelperTest** - Tests the main service class:
- Signature generation with valid and invalid scenarios
- Token validation and user retrieval
- Email change confirmation
- Request cancellation
- Throttling and rate limiting
- Pending request management

**EmailChangeTokenGeneratorTest** - Tests cryptographic token generation:
- Token component generation (selector, token, hashed token)
- Proper length validation (20-char selector, 64-char token)
- Token uniqueness
- Constant-time comparison for security
- SHA-256 hashing verification

**EmailChangeRequestTest** - Tests the entity class:
- Property initialization
- User identifier creation and parsing
- Expiration checking
- Request ownership validation
- Immutability guarantees

**EmailChangeSignatureTest** - Tests the signature value object:
- URL and expiration storage
- Translation-ready expiration messages
- Proper time formatting
- Query parameter handling

**TokenComponentsTest** - Tests the token components value object:
- Constructor and getters
- Edge cases (empty strings, long strings)

### Integration Tests (8 tests)

**EmailChangeServiceDefinitionTest** - Tests dependency injection:
- Service autowiring capabilities
- Configuration parameter usage
- Service container compilation

### Functional Tests (32 tests)

**EmailChangeWorkflowTest** - Tests complete workflows:
- Full email change process (request → validate → confirm)
- Multiple concurrent user requests
- Request throttling and rate limiting
- Token expiration handling
- Invalid token/selector rejection
- Request cancellation
- Extra route parameters

## Test Fixtures

### TestUser

A simple implementation of `EmailChangeInterface` for testing purposes. Includes:
- Basic user properties (id, email)
- Email change trait usage
- Minimal implementation for test isolation

### EmailChangeRequestTestRepository

An in-memory repository implementation that:
- Stores requests in arrays (no database required)
- Supports user registration for testing
- Implements all repository interface methods
- Can be cleared between tests

## Writing New Tests

### Unit Test Example

```php
public function testYourNewFeature(): void
{
    // Arrange
    $user = new TestUser(1, 'test@example.com');

    // Act
    $result = $this->helper->yourMethod($user);

    // Assert
    $this->assertSame('expected', $result);
}
```

### Functional Test Example

```php
public function testCompleteWorkflow(): void
{
    $user = new TestUser(1, 'old@example.com');
    $this->repository->registerUser($user);

    // Generate signature
    $signature = $this->helper->generateSignature('route', $user, 'new@example.com');

    // Parse URL and create request
    parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);
    $request = Request::create('/verify', 'GET', $params);

    // Validate and confirm
    $validatedUser = $this->helper->validateTokenAndFetchUser($request);
    $oldEmail = $this->helper->confirmEmailChange($validatedUser);

    // Assert
    $this->assertSame('new@example.com', $user->getEmail());
}
```

## Best Practices

1. **Follow AAA Pattern**: Arrange, Act, Assert
2. **One Assertion Per Test**: Focus each test on a single behavior
3. **Use Descriptive Names**: Test names should describe what they test
4. **Test Edge Cases**: Include boundary conditions and error scenarios
5. **Mock External Dependencies**: Use mocks for services you don't control
6. **Clean State**: Use `setUp()` and `tearDown()` to ensure test isolation

## CI/CD Integration

Add to your CI pipeline (GitHub Actions example):

```yaml
- name: Run tests
  run: vendor/bin/phpunit --coverage-clover coverage.xml

- name: Upload coverage
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage.xml
```

## Debugging Tests

### Run Single Test

```bash
vendor/bin/phpunit --filter testGenerateSignatureCreatesValidSignature
```

### Stop on First Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Show Test Output

```bash
vendor/bin/phpunit --debug
```

## Contributing

When adding new features:

1. Write tests first (TDD approach recommended)
2. Ensure all tests pass
3. Aim for >80% code coverage
4. Update this README if adding new test suites

## Test Statistics

- **Total Tests**: 156
- **Total Assertions**: 360+
- **Test Suites**: 3 (Unit, Integration, Functional)
- **Test Files**: 8
- **Code Coverage**: Run `vendor/bin/phpunit --coverage-text` to see current coverage

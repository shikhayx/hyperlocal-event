# Contribution Guidelines for NearU

Thank you for your interest in contributing to **NearU** ("Discover What's Happening Around You")! We welcome contributions from the community and are excited to collaborate with you to improve our event discovery, ticketing, and management platform. Before you start, please read these guidelines to ensure a smooth contribution process.

> **IMPORTANT: Open a discussion or issue BEFORE submitting a PR for anything beyond trivial fixes, features, or improvements (typos, broken links, etc.).** PRs submitted without prior discussion will be closed if the PR description is left empty or the template is left unedited. We want to collaborate with you, but we need to align on the approach before code is written.

## Table of Contents

1. [How Can I Contribute?](#how-can-i-contribute)
   - [Reporting Bugs](#reporting-bugs)
   - [Suggesting Enhancements](#suggesting-enhancements)
   - [Pull Requests](#pull-requests)
2. [Development Setup](#development-setup)
   - [Style Guides](#style-guides)
      - [Coding Standards](#coding-standards)
      - [Commit Messages](#commit-messages)
   - [Translations](#translations)
      - [Backend](#backend)
      - [Frontend](#frontend)
   - [Database Changes](#database-changes)
3. [License & Third-Party Notices](#license--third-party-notices)

## How Can I Contribute?

### Reporting Bugs

If you find a bug, please report it by opening an issue in our issue tracker. Include as much detail as possible to help us diagnose and fix the issue:
- Steps to reproduce
- Expected vs actual behavior
- Environment details (Docker, OS, browser)

### Suggesting Enhancements

We welcome suggestions for new features or improvements to existing functionality. To suggest an enhancement, please open an issue and provide a detailed description of the proposed enhancement and its benefits.

### Pull Requests

We accept pull requests for bug fixes, new features, and improvements.

⚠️ Please open an issue or discussion before starting any significant work to ensure that your contribution aligns with the project's goals.

To submit a pull request:

1. Fork the repository to your account.
2. Create a new branch from `develop` for your changes (e.g., `feature/new-feature` or `bugfix/issue-123`).
3. Make your changes, ensuring that your code adheres to our coding standards.
4. Commit your changes with a descriptive commit message.
5. Push your changes to your fork.
6. Open a pull request against the `develop` branch.

Please ensure that your pull request includes:

- A clear description of the changes and the problem they address.
- Any relevant issue numbers (e.g., `Fixes #123`).
- Documentation updates, if applicable.
- Tests for new functionality or bug fixes, if applicable.
- Screenshots or screen recordings, if the changes are visual.

## Development Setup

To set up the development environment for NearU, follow the instructions in the main [README.md](README.md) or `./docker/development/README.md`.

### Style Guides

#### Coding Standards

Please ensure that your code is well-formatted and does not contain commented-out code or unnecessary whitespace. Use descriptive variable names that follow the conventions used in the existing codebase.

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards for PHP.
- Use ES6+ features for TypeScript / JavaScript and adhere to standard React best practices.
- For React components, follow Mantine UI conventions and modular SCSS styles.

#### Commit Messages

Please ensure that your commit messages are clear and descriptive.

### Translations

#### Backend

Please wrap all translatable user-facing strings in the `__()` helper function. For example:

```php
return [
    'welcome' => __('Welcome to NearU!'),
];
```

#### Translation Commands

To extract messages from the codebase, use the following command:

```bash
php artisan langscanner
```

This will update the translation files in the `backend/lang` directory.

#### Frontend

[Lingui](https://lingui.dev/) is used for frontend translations. Please wrap all user-facing strings in either the `t` macro or `<Trans>` component:

```jsx
import { t } from '@lingui/macro';
   
const MyComponent = () => {
    return <div>{t`Welcome to NearU!`}</div>;
};
```

#### Translation Commands

To extract messages from the codebase and compile translations, run:

```bash
yarn messages:extract && yarn messages:compile
```

### Database Changes

If you are making changes to the database schema, please update the migration files accordingly.

We use [Laravel Migrations](https://laravel.com/docs/master/migrations) to manage schema changes. Migration files should only contain schema changes and no logic.

To generate a new migration file, use:

```bash
php artisan make:migration create_XXX_table
```

After running the migration, update the Domain Objects with:

```bash  
php artisan generate-domain-objects
```

## License & Third-Party Notices

NearU is licensed under the **GNU Affero General Public License (AGPL) version 3 with Section 7(b) terms** — see [LICENCE](LICENCE).

NearU is built upon and derived from the Hi.Events open-source platform. All open-source acknowledgements, attribution obligations, and upstream credits are documented in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## AI / Bot Contributors

If you are an AI agent, bot, or using AI tools to generate your contribution:

- **Add 🤖 to the PR title** (e.g., `🤖 Fix typo in README`)
- **Add 🤖 to the end of each commit message**
- All other contribution guidelines above still apply — especially the requirement for prior discussion on non-trivial changes.

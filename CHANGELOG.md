# Changelog

All notable changes to this project will be documented in this file.

## [3.3.0] - 2026-01-06

### Added
- feat(generator): introduce schema mode with embedded schema hashing and metadata-based checks
- docs(deps): add wiki to  composer.json

### Changed
- Update TableClassGenerator.php
- update Generator.php, GlobalGenerator.php and TableClassGenerator.php
- now I'm waiting for your feedback and colaboration
- docs(Tbl::class): now I'm waiting for your feedback and colaboration.
- build(deps): update composer.json
- build(deps): update composer.json
- chore: release v3.2.0 - Smart Aliases & JOIN Constants
- docs: update CHANGELOG.md and README.md

## [3.2.0] - 2025-12-22

### Added
- feat: improve alias generation with consonant-first strategy
- feat: generate comprehensive database constants class with joins and aliases
- feat: add Spanish dictionary for table/column abbreviations
- feat: deprecate 'smart' strategy with backward compatibility
- feat: add JoinGenerator and OnJoinGenerator traits for FK handling
- feat(table-aliases): implement smart alias generation with length optimization
- Refactor README to streamline features and CI/CD section
- Update README with additional foreign key example
- docs: update CHANGELOG.md and README.md for new features and configuration changes

### Fixed
- fix: clean up generator code and update .gitignore
- Fix command to generate constants in README
- Fix data path resolution and remove debug method
- Fix copyright comment formatting in GlobalGenerator
- Fix copyright comment formatting in TableClassGenerator
- Fix formatting for foreign keys comment section

### Changed
- chore: update default config - remove smart strategy, adjust lengths
- refactor: reorganize TableClassGenerator with section-based output
- update TableAliasGenerator.php
- update NamingResolver.php
- update ConnectionResolver.php
- Implement getEnumConstants method in Generator
- Refactor TableClassGenerator with naming constants
- update tbl-class, Generator.php and NamingResolver.php
- Enhance tbl-class help output and usage examples
- Refine description and update PHPUnit version
- Revise README for Tbl::class v3
- Update copyright regeneration comments in GlobalGenerator
- Update copyright comments in TableClassGenerator.php
- Update README with installation commands
- Remove Project Structure section from README
- Revise database constants documentation in README
- Change abbreviation language from 'pt' to 'en'

## [3.1.0] - 2025-12-19

### Added
- feat: enhance naming strategies and improve constant generation in tbl-class
- feat: add Portuguese dictionary and naming resolver for table abbreviations

### Changed
- chore: move ConnectionResolver.php to src/Resolvers update tbl-class
- Update README and composer.json for enhanced documentation and dependency management

## [3.1.0] - 2025-12-18

### Added
- Add GlobalGenerator and TableClassGenerator for schema constant generation
- Add class existence check in showInstructions method
- feat: final v3.0.0 release preparation

### Changed
- Enhance tbl-class functionality with global mode support and improved error handling
- Escape variables in Config.php
- Implement ensureGitignore to manage .gitignore file
- Update database configuration defaults and comments
- Simplify database connection configuration
- Refactor get method in Config.php
- Update Config.php
- Revise config file prompts for database settings
- Update installation command for tbl-class
- docs: update CHANGELOG.md

## [3.0.1] - 2025-12-17

### Fixed
- fix: update script commands in composer.json for consistency and clarity
- fix: correct class name check for Logger in tbl-class-logs
- fix: correct script commands in composer.json for proper execution

## [3.0.0] - 2025-12-17

### Added
- feat: update README and Generator class for improved constant generation and logging
- feat: add tbl-class and tbl-class-logs scripts for generating constants and viewing logs
- feat: add Config, ConnectionResolver, and Logger classes for database configuration and connection handling
- feat: update README and composer.json for improved clarity and support for SQLite
- Add GitHub Actions workflow for PHP Composer
- feat: add LICENSE file with MIT License details

### Changed
- chore: update .gitignore
- build(deps): update composer.json: project pachage eril\tbl-class
- chore: delete tbl-class-generate
- Update download badge link in README.md
- Update README.md
- Merge branch 'main' of https://github.com/erilshackle/php-tbl-schema-sync


## [2.0.0] - 2025-12-17

### Added
- feat: Refatorar para Tooling Exclusivo (--dev) e Remover TblInitializer

### Changed
- Correct JSON code block formatting in README
- docs: create CHANGELOG.md

## [1.0.0] - 2025-12-16

### Added
- feat: Initial setup for tbl-schema-sync package

### Changed
- docs: update README.md


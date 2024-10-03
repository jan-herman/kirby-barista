# Changelog

## [1.6.0] - 2024-10-04
### Added
- JanHerman\Barista\Snippet class

### Changed
- snippet loader implementation (extend default snippet class)


## [1.5.0] - 2024-10-04
### Fixed
- support for .latte snippets (snippets weren't loaded unless defined in a plugin)


## [1.4.1] - 2024-09-21
### Fixed
- $field->toHtml() method doesn't escape non-breakable spaces any more


## [1.4.0] - 2024-08-13
### Added
- $barista->getEngine() method


## [1.3.1] - 2024-02-04
### Fixed
- Template's render function wasn't passing data to it's parent. Causing errors in non latte templates.


## [1.3.0] - 2024-01-19
### Added
- 'jan-herman.barista.init:after' hook to expose `$barista->latte`
- support for callable in path aliases
    - i.e. '@snippets' => function ($name) { ... } where $name is the rest of the string after the alias and the following  slash
- option to omit '.latte' extension when using path alias

### Removed
- `addFilter` & `addFunction` methods


## [1.2.0] - 2023-09-12
### Changed
- Template.php simplification


## [1.1.1] - 2023-09-19
### Fixed
- version mismatch in composer.json


## [1.1.0] - 2023-09-19
### Added
- Merx compatibility

### Removed
- $kirby & $barista atributes from Template class

### Fixed
- missing type definitions


## [1.0.3] - 2023-08-12
### Added
- error handling to render & renderToString methods


## [1.0.2] - 2023-08-12
### Fixed
- version mismatch in composer.json


## [1.0.1] - 2023-08-12
### Changed
- refactoring
- default for tempDirectory changed from cache/temp to cache/barista


## [1.0.0] - 2023-02-20
### Added
- Initial release

# Changelog

## [1.12.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.11.0...v1.12.0) (2025-10-10)


### Features

* Add global SVG optimization settings with UI controls ([114f784](https://github.com/LindemannRock/craft-icon-manager/commit/114f784755de22b92852b2d1320ea62fd785aa22))

## [1.11.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.10.1...v1.11.0) (2025-10-10)


### Features

* **logging:** Add structured context support to static icon set classes ([08da80d](https://github.com/LindemannRock/craft-icon-manager/commit/08da80d3d20be64b506eb6131f3e9fb3acac6e99))
* **logging:** Add structured logging to MaterialIcons and FontAwesome handlers ([6c0a974](https://github.com/LindemannRock/craft-icon-manager/commit/6c0a9745e1549e4b0bb9f33e459d4d5ac6c6cff7))


### Bug Fixes

* Initialize logging handle in IconsService ([2806b38](https://github.com/LindemannRock/craft-icon-manager/commit/2806b38e3e4a10f2da42b2800b829bea6ded4541))

## [1.10.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.10.0...v1.10.1) (2025-10-09)


### Bug Fixes

* logging to use LoggingLibrary trait consistently ([55b73af](https://github.com/LindemannRock/craft-icon-manager/commit/55b73af79fc44b79f986f0b35963077caffd1a28))

## [1.10.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.9.0...v1.10.0) (2025-10-09)


### Features

* add viewLogs and editSettings permissions to Icon Manager ([a300cc9](https://github.com/LindemannRock/craft-icon-manager/commit/a300cc94f85c9aaa9ed2c9ab281ddec1fdc11a6d))
* add WebFont support with proper font loading and icon type filtering ([ec1f9f2](https://github.com/LindemannRock/craft-icon-manager/commit/ec1f9f2a0b0077983b5b3cf4c244d38af61a7bd5))
* implement Material Icons support with style filtering and performance optimizations ([0c58d7e](https://github.com/LindemannRock/craft-icon-manager/commit/0c58d7e1d1acf2fe9b45c68321e01c3b4c1c3b5c))
* Implement robust SVG optimization with php-svg-optimizer library ([0d78f60](https://github.com/LindemannRock/craft-icon-manager/commit/0d78f60c136a04bfa56961dd9053f64069435143))
* implement virtual scrolling for icon picker grid ([ed08ef7](https://github.com/LindemannRock/craft-icon-manager/commit/ed08ef727b88633005e75188e10843c103e101ca))
* Improve SVGO optimization with smart issue detection and presets ([64a7f24](https://github.com/LindemannRock/craft-icon-manager/commit/64a7f24f436ab62bff754cf2cc8bc18da74b5a37))


### Bug Fixes

* logging configuration to use correct log levels ([fa554a7](https://github.com/LindemannRock/craft-icon-manager/commit/fa554a7bbc832d51749042e12ef5487ab7a91f05))
* replace inline icon embedding with single batch AJAX request ([58f7256](https://github.com/LindemannRock/craft-icon-manager/commit/58f72562574b8ac8eeb6010feee3db51df61603d))

## [1.9.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.8.0...v1.9.0) (2025-10-02)


### Features

* add refresh all icons button to utilities page ([1d22674](https://github.com/LindemannRock/craft-icon-manager/commit/1d226743f25168b44ebc618ba8a5198ed0be57aa))

## [1.8.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.7.1...v1.8.0) (2025-10-01)


### Features

* Convert Icon Manager to custom file-based cache storage ([f071d03](https://github.com/LindemannRock/craft-icon-manager/commit/f071d03956b37a62b43108d99192e8b2b6ae8e80))
* Redesign Icon Manager utilities page and improve cache UX ([ab4811d](https://github.com/LindemannRock/craft-icon-manager/commit/ab4811d918ae8fcff5f5f5bda8e4eeda54488756))


### Bug Fixes

* update PHP requirement from ^8.0.2 to ^8.2 in composer.json ([327b028](https://github.com/LindemannRock/craft-icon-manager/commit/327b028abfeb0780c9c51a08f70f469a92ba9e8d))

## [1.7.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.7.0...v1.7.1) (2025-09-25)


### Miscellaneous Chores

* **docs:** correct formatting and update log level settings in README ([eceae47](https://github.com/LindemannRock/craft-icon-manager/commit/eceae471237b9880148352fa3fce4ebbdae083ee))

## [1.7.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.6...v1.7.0) (2025-09-25)


### Features

* add Clear Icon Cache utility template and update rendering path ([6abf77f](https://github.com/LindemannRock/craft-icon-manager/commit/6abf77fb6db30bd6c3af593f39ba280a01f3e89a))


### Bug Fixes

* disable log viewer on Servd edge servers ([b8ac3c7](https://github.com/LindemannRock/craft-icon-manager/commit/b8ac3c79b68e0683360b3c163026940c359391d9))

## [1.6.6](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.5...v1.6.6) (2025-09-24)


### Bug Fixes

* disable log viewer on Servd edge servers ([3e6a9a0](https://github.com/LindemannRock/craft-icon-manager/commit/3e6a9a06cb959b3fa8cb065734b62eead9779d5c))

## [1.6.5](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.4...v1.6.5) (2025-09-24)


### Bug Fixes

* enable log viewer for all environments ([2e98842](https://github.com/LindemannRock/craft-icon-manager/commit/2e9884263cc6cebf90ebde0709742526779cf9f6))

## [1.6.4](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.3...v1.6.4) (2025-09-24)


### Bug Fixes

* update log viewer disable condition for Servd environment ([718c391](https://github.com/LindemannRock/craft-icon-manager/commit/718c39131d698d7531c447f3ddc1cbd8a21ec1ba))

## [1.6.3](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.2...v1.6.3) (2025-09-24)


### Bug Fixes

* disable log viewer on Servd environment ([fd3b66d](https://github.com/LindemannRock/craft-icon-manager/commit/fd3b66dd7ab8b66fc9b34c8553e386eaa0c28b96))

## [1.6.2](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.1...v1.6.2) (2025-09-24)


### Bug Fixes

* prevent duplicate log warnings for console requests in Settings model ([2d7613a](https://github.com/LindemannRock/craft-icon-manager/commit/2d7613ae2a990db9c4130f502c9c3d8d0d50c2be))

## [1.6.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.6.0...v1.6.1) (2025-09-24)


### Bug Fixes

* update repository name and links in README and composer.json ([c9f1a8c](https://github.com/LindemannRock/craft-icon-manager/commit/c9f1a8c6164212768e0831f52037a9fcd13941f6))

## [1.6.0](https://github.com/LindemannRock/icon-manager/compare/v1.5.0...v1.6.0) (2025-09-24)


### Features

* integrate logging library and enhance settings validation ([ddeea93](https://github.com/LindemannRock/icon-manager/commit/ddeea93f879d31154c4bbc88f915fc22168f10ef))

## [1.5.0](https://github.com/LindemannRock/icon-manager/compare/v1.4.1...v1.5.0) (2025-09-22)


### Features

* Add comprehensive logging system with web interface ([76ca4da](https://github.com/LindemannRock/icon-manager/commit/76ca4dae9b084088e115355dd244be0d57391162))
* Add translation strings for icon manager plugin ([e3b36a7](https://github.com/LindemannRock/icon-manager/commit/e3b36a7217951713ee3e1cea271b4eaaf7e7ccde))
* implement comprehensive logging system for Icon Manager ([06e6b9d](https://github.com/LindemannRock/icon-manager/commit/06e6b9d3e4c1dbe41db82ced56b1b956d4b64a11))


### Bug Fixes

* update .gitignore to properly exclude logs and add backup files ([69bae12](https://github.com/LindemannRock/icon-manager/commit/69bae12f1fcfc75c5ea2235ed532f4000832d79e))

## [1.4.1](https://github.com/LindemannRock/icon-manager/compare/v1.4.0...v1.4.1) (2025-09-15)


### Bug Fixes

* update copyright notice and format in LICENSE file ([6e97c76](https://github.com/LindemannRock/icon-manager/commit/6e97c766f8528b67b98a7693bfd8637dc1301d71))

## [1.4.0](https://github.com/LindemannRock/icon-manager/compare/v1.3.0...v1.4.0) (2025-09-14)


### Features

* add plugin credit component to icon sets and settings pages ([3f6b68a](https://github.com/LindemannRock/icon-manager/commit/3f6b68a92f87a3b2bca93a315eae5f881c28126f))

## [1.3.0](https://github.com/LindemannRock/icon-manager/compare/v1.2.0...v1.3.0) (2025-09-12)


### Features

* add eager loading support and fix custom label site isolation ([88a5207](https://github.com/LindemannRock/icon-manager/commit/88a520777fec519d1db17b205275b5b4f2c56e51))

## [1.2.0](https://github.com/LindemannRock/icon-manager/compare/v1.1.4...v1.2.0) (2025-09-12)


### Features

* add eager loading support to IconManagerField ([5e793e6](https://github.com/LindemannRock/icon-manager/commit/5e793e6501270727641812191d48d89020bb2463))

## [1.1.4](https://github.com/LindemannRock/icon-manager/compare/v1.1.3...v1.1.4) (2025-09-10)


### Bug Fixes

* update requirements for Craft CMS and PHP versions in README ([62a0be2](https://github.com/LindemannRock/icon-manager/commit/62a0be27102a85712fa97b50fb1f74d75a0e5d16))

## [1.1.3](https://github.com/LindemannRock/icon-manager/compare/v1.1.2...v1.1.3) (2025-09-10)


### Bug Fixes

* update display name and icon method in ClearIconCache utility ([583dbf0](https://github.com/LindemannRock/icon-manager/commit/583dbf043c6b43b80a67f88c33ec498c62f0ad4c))

## [1.1.2](https://github.com/LindemannRock/icon-manager/compare/v1.1.1...v1.1.2) (2025-09-01)


### Bug Fixes

* include essential Control Panel JavaScript assets ([0616bba](https://github.com/LindemannRock/icon-manager/commit/0616bbab30a9ee406552e4b166b249131c2efca0))

## [1.1.1](https://github.com/LindemannRock/icon-manager/compare/v1.1.0...v1.1.1) (2025-09-01)


### Bug Fixes

* update license to MIT in composer.json and LICENSE.md ([56e5c45](https://github.com/LindemannRock/icon-manager/commit/56e5c4512f4ba30a0f551bcf8985cf85e7df6870))

## [1.1.0](https://github.com/LindemannRock/icon-manager/compare/v1.0.1...v1.1.0) (2025-09-01)


### Features

* add comprehensive configuration and metadata documentation ([248c3cb](https://github.com/LindemannRock/icon-manager/commit/248c3cb46c0e6f38ad0b8cf6daa5ac533c7d41ae))

## [1.0.1](https://github.com/LindemannRock/icon-manager/compare/v1.0.0...v1.0.1) (2025-09-01)


### Bug Fixes

* update documentation and changelog URLs to use 'main' branch ([bff8aec](https://github.com/LindemannRock/icon-manager/commit/bff8aecd5a4998c25f05d393f71406485a36430a))

## 1.0.0 (2025-09-01)


### Features

* initial Icon Manager plugin implementation ([14195c3](https://github.com/LindemannRock/icon-manager/commit/14195c3ceb9f06f37b2811e1bdb63f41f7f6a2f4))

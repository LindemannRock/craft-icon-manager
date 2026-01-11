# Changelog

## [5.9.4](https://github.com/LindemannRock/craft-icon-manager/compare/v5.9.3...v5.9.4) (2026-01-11)


### Bug Fixes

* set pluginName in iconmanager_settings to not null with default value 'Icon Manager' ([f5e4641](https://github.com/LindemannRock/craft-icon-manager/commit/f5e46418295ae7f8b5ee3710bce1ba9f1acf5085))

## [5.9.3](https://github.com/LindemannRock/craft-icon-manager/compare/v5.9.2...v5.9.3) (2026-01-11)


### Bug Fixes

* set default pluginName in iconmanager_settings to 'Icon Manager' ([75d4744](https://github.com/LindemannRock/craft-icon-manager/commit/75d4744db6c94613cd9c142733bd85ab819240ef))

## [5.9.2](https://github.com/LindemannRock/craft-icon-manager/compare/v5.9.1...v5.9.2) (2026-01-11)


### Bug Fixes

* update pluginName type and improve displayName method in ClearIconCache utility ([944b9ea](https://github.com/LindemannRock/craft-icon-manager/commit/944b9eac135f1ce19b005c0026128b34295d26fd))

## [5.9.1](https://github.com/LindemannRock/craft-icon-manager/compare/v5.9.0...v5.9.1) (2026-01-11)


### Bug Fixes

* simplify displayName method in ClearIconCache utility ([13303e5](https://github.com/LindemannRock/craft-icon-manager/commit/13303e5972da19cdcf3c8735aef90ddf96609d80))

## [5.9.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.8.0...v5.9.0) (2026-01-08)


### Features

* **icon-manager:** Add granular user permissions system ([51d80e8](https://github.com/LindemannRock/craft-icon-manager/commit/51d80e8af9405bd2de18868127e58f6d8817a675))
* streamline user permissions checks for icon sets access ([3340342](https://github.com/LindemannRock/craft-icon-manager/commit/33403424462424aa1ac4697e57d2e5f3db52b940))
* update user permissions labels to include dynamic names ([a67d874](https://github.com/LindemannRock/craft-icon-manager/commit/a67d87486bb3eddd54255e12f83672f764c0d7be))


### Bug Fixes

* refactor quick actions visibility logic in index.twig ([0ded170](https://github.com/LindemannRock/craft-icon-manager/commit/0ded170412ebe7009c8f2f3faf8585008707e787))
* update success message for settings save action ([76843a0](https://github.com/LindemannRock/craft-icon-manager/commit/76843a03d9f28c5f9d0f5cd76889ffb4ae3c3b7f))

## [5.8.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.7.1...v5.8.0) (2026-01-06)


### Features

* migrate to shared base plugin ([5ac5983](https://github.com/LindemannRock/craft-icon-manager/commit/5ac5983f2572aeb7c706966ea54692a2009c7309))

## [5.7.1](https://github.com/LindemannRock/craft-icon-manager/compare/v5.7.0...v5.7.1) (2026-01-04)


### Bug Fixes

* add missing PHP SVG Optimizer settings to installation migration ([356a658](https://github.com/LindemannRock/craft-icon-manager/commit/356a658c6b984e303c40d9ed4b1113f5415f2aff))

## [5.7.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.6.0...v5.7.0) (2025-12-19)


### Features

* add color coding for icon types in the icon sets index ([033dca0](https://github.com/LindemannRock/craft-icon-manager/commit/033dca051494d1adc51b7df9b81f965704e9354f))
* enhance cache duration settings with human-readable format and validation ([e17addb](https://github.com/LindemannRock/craft-icon-manager/commit/e17addb25f59381af265a021983fdb6dbef726ad))


### Bug Fixes

* improve display name handling by trimming whitespace in cache options ([dcba4b8](https://github.com/LindemannRock/craft-icon-manager/commit/dcba4b8dee069cdb3ffd0a58d6b6913b7b30b00e))

## [5.6.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.5.0...v5.6.0) (2025-12-16)


### Features

* add cache storage method configuration for icon management ([ad66105](https://github.com/LindemannRock/craft-icon-manager/commit/ad66105582290f5ef3f1d505899c6ee98d0b69e6))
* add cache storage method configuration to icon settings ([9aa99af](https://github.com/LindemannRock/craft-icon-manager/commit/9aa99af66cef9625850c500e1fa0e57f6309021b))
* implement Redis cache support for icon management and enhance cache clearing functionality ([6f89d1d](https://github.com/LindemannRock/craft-icon-manager/commit/6f89d1d4d52932b0b557063833373f7e468485d7))
* update caching configuration to support file and Redis storage methods ([6433373](https://github.com/LindemannRock/craft-icon-manager/commit/64333736d7291ee3d848cb53696f08ca45767a2d))


### Bug Fixes

* refine cache status display for Redis storage method ([bd5d1c9](https://github.com/LindemannRock/craft-icon-manager/commit/bd5d1c9e55f717f606c8132dae904b57075a2ed8))

## [5.5.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.4.0...v5.5.0) (2025-12-04)


### Features

* add [@since](https://github.com/since) annotations to various classes and files for version tracking ([70c6db6](https://github.com/LindemannRock/craft-icon-manager/commit/70c6db6c9a40d5b35c6169713234d0e9e564fc7f))
* add Info Box component template ([f30b6d8](https://github.com/LindemannRock/craft-icon-manager/commit/f30b6d8ce6ed4e5cd84eb2e45193109dc03a7b4e))
* Add PHPStan and EasyCodingStandard support ([39626d0](https://github.com/LindemannRock/craft-icon-manager/commit/39626d0c975eedb2e5586b0cc07c0ca32611cf0e))
* Refactor and enhance various components for improved functionality and clarity ([1da2e6b](https://github.com/LindemannRock/craft-icon-manager/commit/1da2e6b85b25a154d76f7f30b11d7c3e568d4248))
* update titles in settings templates for clarity ([c049862](https://github.com/LindemannRock/craft-icon-manager/commit/c049862990e4c78c435c0a545c8e013a88f1e666))

## [5.4.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.3.0...v5.4.0) (2025-11-15)


### Features

* add MIT License file ([7cb19b7](https://github.com/LindemannRock/craft-icon-manager/commit/7cb19b7f8c1111005b78800349c2c557edf062ae))

## [5.3.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.2.0...v5.3.0) (2025-11-14)


### Features

* add Twig extension for plugin name helpers and enhance display name methods ([f248e4d](https://github.com/LindemannRock/craft-icon-manager/commit/f248e4d8ec6a1181ec475ea070d3c9ae25f3b0b2))
* enhance SVG backup and optimization features with includeSubfolders option ([3a2607f](https://github.com/LindemannRock/craft-icon-manager/commit/3a2607f213a82cbf1bbc32d052c8aa3d1c2ea186))
* improve SVG optimization feedback and cleanup backup handling ([e79e7d8](https://github.com/LindemannRock/craft-icon-manager/commit/e79e7d83e77da1c61b7c0bef4939675680874c53))
* **svg-optimizer:** add user-controlled optimization rules and reorganize settings UI ([7c1179a](https://github.com/LindemannRock/craft-icon-manager/commit/7c1179a8545349ee9297e2acd26b67f2b1c6e7d8))
* update overview heading to include dynamic plugin name ([94e668e](https://github.com/LindemannRock/craft-icon-manager/commit/94e668edc293979175a5b3963bd944ff1573f8bf))


### Bug Fixes

* update php-svg-optimizer version to ^7.3 for compatibility ([65c107c](https://github.com/LindemannRock/craft-icon-manager/commit/65c107c59b26b9db324b22e40ec25949d8cddfaf))

## [5.2.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.1.2...v5.2.0) (2025-11-06)


### Features

* enhance settings handling and add hidden input for section in templates ([26e3f1e](https://github.com/LindemannRock/craft-icon-manager/commit/26e3f1ea5d2c5fd6cae1d0f2a577c2ff0e518cc8))
* implement unique handle generation and validation for icon sets ([16169dc](https://github.com/LindemannRock/craft-icon-manager/commit/16169dc9bbebe95363670726a1679bc04a5e97c2))


### Bug Fixes

* add custom setter for enabledIconTypes to normalize boolean values ([5d29ac1](https://github.com/LindemannRock/craft-icon-manager/commit/5d29ac19fd7dbc3dba03b8f2a6a7c69b8beb0bc4))
* update logging documentation and configuration examples for improved clarity and best practices ([f0f5c96](https://github.com/LindemannRock/craft-icon-manager/commit/f0f5c96e92e75071fee8abed886813f07a288c41))

## [5.1.2](https://github.com/LindemannRock/craft-icon-manager/compare/v5.1.1...v5.1.2) (2025-10-26)


### Bug Fixes

* improve config attribute override detection in Settings model ([0c6d665](https://github.com/LindemannRock/craft-icon-manager/commit/0c6d665cea2190b13aadb36d2176f4ee8d108c9e))

## [5.1.1](https://github.com/LindemannRock/craft-icon-manager/compare/v5.1.0...v5.1.1) (2025-10-26)


### Bug Fixes

* reorganize configuration settings and update log level instructions ([2eae2e1](https://github.com/LindemannRock/craft-icon-manager/commit/2eae2e13c408f66591e232d6650c09981abcc564))

## [5.1.0](https://github.com/LindemannRock/craft-icon-manager/compare/v5.0.2...v5.1.0) (2025-10-26)


### Features

* integrate logging functionality across IconManager and related components ([fe8f8ec](https://github.com/LindemannRock/craft-icon-manager/commit/fe8f8ecd6f9867fe88c8c80397d8f579fcdc3b66))


### Bug Fixes

* settings management for Icon Manager plugin ([842681a](https://github.com/LindemannRock/craft-icon-manager/commit/842681a6bb4c5ee570b8f4de0d3bf025b27c2384))

## [5.0.2](https://github.com/LindemannRock/craft-icon-manager/compare/v5.0.1...v5.0.2) (2025-10-22)


### Bug Fixes

* update icon sets retrieval to filter by enabled types from settings ([8a5136d](https://github.com/LindemannRock/craft-icon-manager/commit/8a5136d4a60f310120e1cce8c4aecfeb7574ca66))
* update icon sets table layout and enhance bulk actions functionality ([e8e454c](https://github.com/LindemannRock/craft-icon-manager/commit/e8e454cff96e60ed70284f3b8af9a80feccba700))
* update logging configuration to use error level by default and set items per page ([ab77756](https://github.com/LindemannRock/craft-icon-manager/commit/ab7775645a4fc5613a9b15443951c79a74da60fc))

## [5.0.1](https://github.com/LindemannRock/craft-icon-manager/compare/v5.0.0...v5.0.1) (2025-10-20)


### Miscellaneous Chores

* update logging library dependency to version 5.0 and enhance README with additional badges ([9ca3cc1](https://github.com/LindemannRock/craft-icon-manager/commit/9ca3cc1b4ec7c4892b3376f83d3f1e18a9462d7e))

## [5.0.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.19.0...v5.0.0) (2025-10-20)


### Miscellaneous Chores

* bump version scheme to match Craft 5 ([fcf3353](https://github.com/LindemannRock/craft-icon-manager/commit/fcf3353578e9a48b2aaa8e9a7a671befaffa952d))

## [1.19.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.18.3...v1.19.0) (2025-10-19)


### Features

* add modern index layout with filtering, search, sorting, pagination and bulk actions to icon sets ([ba54863](https://github.com/LindemannRock/craft-icon-manager/commit/ba5486374d3611374092dc71db1dfc1b116bc997))


### Bug Fixes

* remove unnecessary whitespace in icon set edit template ([f0b9e9d](https://github.com/LindemannRock/craft-icon-manager/commit/f0b9e9d2c5aeee222b0011117d44f31c8176b004))
* set selected subnav item for icon sets edit template ([3fe45e1](https://github.com/LindemannRock/craft-icon-manager/commit/3fe45e12e851a52755a27391f552aa0884634e1b))

## [1.18.3](https://github.com/LindemannRock/craft-icon-manager/compare/v1.18.2...v1.18.3) (2025-10-17)


### Bug Fixes

* use settings for plugin name in logging configuration ([1c80df9](https://github.com/LindemannRock/craft-icon-manager/commit/1c80df924b679fc3d2425e4fefd6cab08a542a99))

## [1.18.2](https://github.com/LindemannRock/craft-icon-manager/compare/v1.18.1...v1.18.2) (2025-10-16)


### Bug Fixes

* update installation instructions for Composer and DDEV ([aea61a1](https://github.com/LindemannRock/craft-icon-manager/commit/aea61a1afaad99ea8883a0e2154053cbdce3c9a9))

## [1.18.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.18.0...v1.18.1) (2025-10-16)


### Bug Fixes

* remove logging-library repository configuration from composer.json ([21673b9](https://github.com/LindemannRock/craft-icon-manager/commit/21673b98d39b976093996106fa595bf4f2713287))

## [1.18.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.17.2...v1.18.0) (2025-10-16)


### Features

* Update logging configuration and add detailed logging documentation ([a9a55dc](https://github.com/LindemannRock/craft-icon-manager/commit/a9a55dc68645d37c8d55783564af9b6a775dafef))

## [1.17.2](https://github.com/LindemannRock/craft-icon-manager/compare/v1.17.1...v1.17.2) (2025-10-16)


### Bug Fixes

* Update display name retrieval for Icon Manager field ([f02b431](https://github.com/LindemannRock/craft-icon-manager/commit/f02b4310a360268444a12e58b1d4a90ea7dd592e))

## [1.17.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.17.0...v1.17.1) (2025-10-15)


### Bug Fixes

* Improve error logging for database settings loading and validation ([dbbe29c](https://github.com/LindemannRock/craft-icon-manager/commit/dbbe29cfc6cb04b57ceb35fa66f21945eb54ae63))

## [1.17.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.16.0...v1.17.0) (2025-10-11)


### Features

* Implement dynamic multi-language metadata support ([35c7637](https://github.com/LindemannRock/craft-icon-manager/commit/35c76379ed27a752ff59c932ffd4d3df66e5b573))


### Bug Fixes

* Implement proper multi-site support for custom labels and metadata ([a9a4d25](https://github.com/LindemannRock/craft-icon-manager/commit/a9a4d25f471bd052fe83f509ee27cd5a49714dcb))

## [1.16.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.15.0...v1.16.0) (2025-10-11)


### Features

* Enhance documentation with detailed configuration and usage examples ([b203fe7](https://github.com/LindemannRock/craft-icon-manager/commit/b203fe7e61c650804672beb5b2abd51f6949ace0))

## [1.15.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.14.1...v1.15.0) (2025-10-11)


### Features

* Add granular scan control settings for SVG optimization ([26bbea2](https://github.com/LindemannRock/craft-icon-manager/commit/26bbea2c5945cb81345d94013281ea3c37414a03))

## [1.14.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.14.0...v1.14.1) (2025-10-11)


### Bug Fixes

* Add plugin credit to preview and optimization tabs ([b7463ae](https://github.com/LindemannRock/craft-icon-manager/commit/b7463aea0111d13a69326cf2c002d6303054ad20))

## [1.14.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.13.2...v1.14.0) (2025-10-10)


### Features

* Add icon preview tab and improve SVG optimization ([b3a0d84](https://github.com/LindemannRock/craft-icon-manager/commit/b3a0d84520871479f72db50c9dc96a3c4fe205e3))

## [1.13.2](https://github.com/LindemannRock/craft-icon-manager/compare/v1.13.1...v1.13.2) (2025-10-10)


### Bug Fixes

* Preserve legal/license comments in SVG optimization ([c44c198](https://github.com/LindemannRock/craft-icon-manager/commit/c44c1981b0983b392e27818bef7e191672774d16))

## [1.13.1](https://github.com/LindemannRock/craft-icon-manager/compare/v1.13.0...v1.13.1) (2025-10-10)


### Bug Fixes

* Improve optimization page layout and formatting ([432ccbe](https://github.com/LindemannRock/craft-icon-manager/commit/432ccbe7ac501f6e9340ea0c2157cd90abcfde86))

## [1.13.0](https://github.com/LindemannRock/craft-icon-manager/compare/v1.12.0...v1.13.0) (2025-10-10)


### Features

* Enhance issue display logic and improve UI for optimization tab ([20af172](https://github.com/LindemannRock/craft-icon-manager/commit/20af172060247f062b9c4fedd0d977821a17df9f))

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

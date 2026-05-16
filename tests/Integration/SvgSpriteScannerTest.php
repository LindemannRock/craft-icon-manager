<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use lindemannrock\iconmanager\iconsets\SvgSprite;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Pins SvgSprite::getIcons() — the SVG-sprite icon-set scanner that
 * IconsService::refreshIconsForSet() delegates to for `type=svg-sprite`. A
 * regression here either drops icons site-wide or returns symbols under
 * unexpected names, breaking saved field values that reference them.
 */
final class SvgSpriteScannerTest extends TestCase
{
    /**
     * Happy path: every <symbol> with an id becomes an Icon, label is
     * titleized from kebab-case, symbolId + viewBox preserved in metadata,
     * and Icon::$value pins the ORIGINAL id (rendering needs the id from
     * the sprite, not the prefix-stripped display name).
     */
    public function testExtractsSymbolsFromSpriteFile(): void
    {
        $root = $this->seedTempIconRoot();
        file_put_contents($root . '/sprite.svg', <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <symbol id="alpha-one" viewBox="0 0 24 24"><path d="M1 1"/></symbol>
                <symbol id="beta_two" viewBox="0 0 16 16"><path d="M2 2"/></symbol>
                <symbol id="gamma" viewBox="0 0 32 32"><path d="M3 3"/></symbol>
            </svg>
            SVG);

        $icons = SvgSprite::getIcons($this->buildIconSet(['spriteFile' => 'sprite.svg']));

        $this->assertCount(3, $icons);

        $byName = [];
        foreach ($icons as $icon) {
            $byName[$icon->name] = $icon;
        }

        $this->assertSame(['alpha-one', 'beta_two', 'gamma'], array_keys($byName));

        $this->assertSame('Alpha One', $byName['alpha-one']->label);
        $this->assertSame('Beta Two', $byName['beta_two']->label);
        $this->assertSame('Gamma', $byName['gamma']->label);

        $this->assertSame(Icon::TYPE_SPRITE, $byName['alpha-one']->type);
        $this->assertSame('alpha-one', $byName['alpha-one']->value);
        $this->assertSame('alpha-one', $byName['alpha-one']->metadata['symbolId']);
        $this->assertSame('0 0 24 24', $byName['alpha-one']->metadata['viewBox']);
        $this->assertSame('sprite.svg', $byName['alpha-one']->metadata['spriteFile']);
    }

    /**
     * Prefix configuration strips the prefix from Icon::$name (so saved field
     * values stay short) but leaves Icon::$value + metadata['symbolId']
     * untouched (so render lookup still hits the sprite by its original id).
     * Symbols whose ids don't match the prefix pass through unchanged.
     */
    public function testStripsConfiguredPrefixFromIconNameOnly(): void
    {
        $root = $this->seedTempIconRoot();
        file_put_contents($root . '/prefixed.svg', <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <symbol id="ic-home" viewBox="0 0 24 24"><path d="M0"/></symbol>
                <symbol id="ic-search" viewBox="0 0 24 24"><path d="M0"/></symbol>
                <symbol id="other-thing" viewBox="0 0 24 24"><path d="M0"/></symbol>
            </svg>
            SVG);

        $icons = SvgSprite::getIcons($this->buildIconSet([
            'spriteFile' => 'prefixed.svg',
            'prefix' => 'ic-',
        ]));

        $this->assertCount(3, $icons);

        $byName = [];
        foreach ($icons as $icon) {
            $byName[$icon->name] = $icon;
        }

        $this->assertArrayHasKey('home', $byName);
        $this->assertArrayHasKey('search', $byName);
        $this->assertArrayHasKey('other-thing', $byName);

        $this->assertSame('ic-home', $byName['home']->value);
        $this->assertSame('ic-home', $byName['home']->metadata['symbolId']);
        $this->assertSame('other-thing', $byName['other-thing']->value);
    }

    /**
     * Symbols without an id are skipped silently — extractSymbolsFromSprite()
     * continues, so a malformed sprite still yields its other usable icons
     * (rather than failing the whole set).
     */
    public function testSkipsSymbolsWithoutAnIdAttribute(): void
    {
        $root = $this->seedTempIconRoot();
        file_put_contents($root . '/mixed.svg', <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg">
                <symbol id="valid-one" viewBox="0 0 24 24"><path d="M0"/></symbol>
                <symbol viewBox="0 0 24 24"><path d="M0"/></symbol>
                <symbol id="valid-two" viewBox="0 0 24 24"><path d="M0"/></symbol>
            </svg>
            SVG);

        $icons = SvgSprite::getIcons($this->buildIconSet(['spriteFile' => 'mixed.svg']));

        $this->assertCount(2, $icons);
        $names = array_map(fn(Icon $icon) => $icon->name, $icons);
        $this->assertSame(['valid-one', 'valid-two'], $names);
    }

    /**
     * Missing sprite file returns [] (logged as an error). A typo in the
     * Settings UI shouldn't crash field rendering — every code path
     * downstream of getIcons() handles an empty array.
     */
    public function testReturnsEmptyArrayWhenSpriteFileMissing(): void
    {
        $this->seedTempIconRoot();

        $icons = SvgSprite::getIcons($this->buildIconSet(['spriteFile' => 'does-not-exist.svg']));

        $this->assertSame([], $icons);
    }

    /**
     * Build an unsaved IconSet wired with the given type-settings. SvgSprite
     * doesn't query the DB — it only reads $iconSet->settings + handle + id
     * — so a synthetic id avoids touching `iconmanager_iconsets`. Handle
     * carries the marker prefix anyway so any accidental DB write would be
     * caught by tearDown's purge.
     */
    private function buildIconSet(array $settings): IconSet
    {
        $iconSet = new IconSet();
        $iconSet->id = 999991;
        $iconSet->handle = self::MARKER_PREFIX . 'sprite';
        $iconSet->type = 'svg-sprite';
        $iconSet->settings = $settings;

        return $iconSet;
    }
}

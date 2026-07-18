<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use craft\base\Field;
use craft\elements\Entry;
use craft\events\DefineFieldKeywordsEvent;
use lindemannrock\iconmanager\fields\IconManagerField;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\tests\TestCase;
use yii\base\Event;

/**
 * Locks Icon Manager's own Craft field keyword contract.
 */
final class IconManagerFieldKeywordTest extends TestCase
{
    public function testSingleIconKeywordsUseStableUserMeaningfulParts(): void
    {
        $field = new IconManagerField(['handle' => 'icon']);
        $keywords = $field->getSearchKeywords($this->icon(), new Entry());

        self::assertSame('Home icon home hero-home primary dashboard navigation', $keywords);
        self::assertStringNotContainsString('42', $keywords);
        self::assertStringNotContainsString(Icon::TYPE_FONT, $keywords);
    }

    public function testMultipleIconKeywordsUseEachIcon(): void
    {
        $field = new IconManagerField(['handle' => 'icons', 'allowMultiple' => true]);
        $keywords = $field->getSearchKeywords([
            $this->icon(),
            $this->icon([
                'name' => 'settings',
                'label' => 'Settings icon',
                'value' => 'hero-settings',
                'iconSetHandle' => 'secondary',
                'keywords' => ['configuration'],
            ]),
        ], new Entry());

        self::assertSame(
            'Home icon home hero-home primary dashboard navigation Settings icon settings hero-settings secondary configuration',
            $keywords,
        );
    }

    public function testCraftDefineKeywordsEventCanOverrideFieldKeywords(): void
    {
        $handler = static function(DefineFieldKeywordsEvent $event): void {
            $event->keywords = 'event override keywords';
            $event->handled = true;
        };

        Event::on(IconManagerField::class, Field::EVENT_DEFINE_KEYWORDS, $handler);

        try {
            $field = new IconManagerField(['handle' => 'icon']);

            self::assertSame('event override keywords', $field->getSearchKeywords($this->icon(), new Entry()));
        } finally {
            Event::off(IconManagerField::class, Field::EVENT_DEFINE_KEYWORDS, $handler);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function icon(array $overrides = []): Icon
    {
        return new Icon(array_merge([
            'id' => 42,
            'iconSetId' => 7,
            'iconSetHandle' => 'primary',
            'type' => Icon::TYPE_FONT,
            'name' => 'home',
            'label' => 'Home icon',
            'value' => 'hero-home',
            'keywords' => ['dashboard', 'navigation', 'home'],
        ], $overrides));
    }
}

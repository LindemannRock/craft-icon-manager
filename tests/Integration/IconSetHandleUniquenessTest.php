<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use Craft;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\records\IconSetRecord;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Pins icon set handle normalization and duplicate handling.
 *
 * @since 5.15.0
 */
final class IconSetHandleUniquenessTest extends TestCase
{
    private const HANDLE_PREFIX = 'im-test-';

    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteHandleRows();
    }

    protected function tearDown(): void
    {
        $this->deleteHandleRows();
        parent::tearDown();
    }

    public function testNewDuplicateIconSetHandleAutoSuffixesWithHyphen(): void
    {
        $this->saveSeedIconSet(self::HANDLE_PREFIX . 'icons');

        $iconSet = $this->makeIconSet('Icons', self::HANDLE_PREFIX . 'icons');

        self::assertTrue(IconManager::getInstance()->iconSets->saveIconSet($iconSet), implode(', ', $iconSet->getFirstErrors()));
        self::assertSame(self::HANDLE_PREFIX . 'icons-1', $iconSet->handle);
        self::assertNotSame(self::HANDLE_PREFIX . 'icons1', $iconSet->handle);
    }

    public function testExistingIconSetDuplicateHandleRejects(): void
    {
        $this->saveSeedIconSet(self::HANDLE_PREFIX . 'icons-one');
        $iconSet = $this->saveSeedIconSet(self::HANDLE_PREFIX . 'icons-two');

        $iconSet->handle = self::HANDLE_PREFIX . 'icons-one';

        self::assertFalse(IconManager::getInstance()->iconSets->saveIconSet($iconSet));
        self::assertSame('Handle must be unique.', $iconSet->getFirstError('handle'));
    }

    public function testIconSetHandleNormalizesToKebabSlug(): void
    {
        $iconSet = $this->makeIconSet('Icons', 'IM Test Mixed Case');

        self::assertTrue(IconManager::getInstance()->iconSets->saveIconSet($iconSet), implode(', ', $iconSet->getFirstErrors()));
        self::assertSame('im-test-mixed-case', $iconSet->handle);
    }

    private function makeIconSet(string $name, string $handle): IconSet
    {
        $iconSet = new IconSet();
        $iconSet->name = $name;
        $iconSet->handle = $handle;
        $iconSet->type = 'custom';
        $iconSet->enabled = true;
        $iconSet->sortOrder = 0;
        $iconSet->settings = [];

        return $iconSet;
    }

    private function saveSeedIconSet(string $handle): IconSet
    {
        $iconSet = $this->makeIconSet($handle, $handle);

        self::assertTrue(IconManager::getInstance()->iconSets->saveIconSet($iconSet), implode(', ', $iconSet->getFirstErrors()));
        $this->trackSeededSetId((int)$iconSet->id);

        return $iconSet;
    }

    private function deleteHandleRows(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete(IconSetRecord::tableName(), ['like', 'handle', self::HANDLE_PREFIX])
            ->execute();
    }
}

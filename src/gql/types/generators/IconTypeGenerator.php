<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\iconmanager\gql\types\generators;

use Craft;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;
use lindemannrock\iconmanager\gql\types\IconType;

/**
 * Icon GraphQL type generator
 *
 * @author    LindemannRock
 * @package   IconManager
 * @since     5.11.0
 */
class IconTypeGenerator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        return [static::generateType($context)];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        $typeName = 'IconManager_Icon';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new IconType([
            'name' => $typeName,
            'description' => 'Icon data from the Icon Manager plugin.',
            'fields' => function() use ($typeName) {
                $fields = [
                    'name' => [
                        'name' => 'name',
                        'type' => Type::string(),
                        'description' => 'The icon identifier/filename.',
                    ],
                    'label' => [
                        'name' => 'label',
                        'type' => Type::string(),
                        'description' => 'The display label (resolved per site language).',
                    ],
                    'type' => [
                        'name' => 'type',
                        'type' => Type::string(),
                        'description' => 'The icon type: svg, sprite, or font.',
                    ],
                    'value' => [
                        'name' => 'value',
                        'type' => Type::string(),
                        'description' => 'The icon value (CSS class, sprite ID, or filename).',
                    ],
                    'iconSetHandle' => [
                        'name' => 'iconSetHandle',
                        'type' => Type::string(),
                        'description' => 'The handle of the icon set this icon belongs to.',
                    ],
                    'customLabel' => [
                        'name' => 'customLabel',
                        'type' => Type::string(),
                        'description' => 'User-defined custom label for the icon.',
                    ],
                    'svg' => [
                        'name' => 'svg',
                        'type' => Type::string(),
                        'description' => 'Inline SVG markup (only for SVG-type icons).',
                    ],
                    'content' => [
                        'name' => 'content',
                        'type' => Type::string(),
                        'description' => 'Rendered HTML content (SVG markup, font icon span, or sprite reference).',
                    ],
                ];

                return Craft::$app->getGql()->prepareFieldDefinitions($fields, $typeName);
            },
        ]));
    }
}

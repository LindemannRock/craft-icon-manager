<?php
/**
 * Icon Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\iconmanager\gql\types;

use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\iconmanager\models\Icon;

/**
 * Icon GraphQL type
 *
 * @author    LindemannRock
 * @package   IconManager
 * @since     5.11.0
 */
class IconType extends ObjectType
{
    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        /** @var Icon $source */
        $fieldName = $resolveInfo->fieldName;

        return match ($fieldName) {
            'name' => $source->name,
            'label' => $source->getDisplayLabel(),
            'type' => $source->type,
            'value' => $source->value,
            'iconSetHandle' => $source->iconSetHandle,
            'customLabel' => $source->customLabel,
            'svg' => $source->type === Icon::TYPE_SVG ? $source->getSvg() : null,
            'content' => $source->getContent()?->__toString(),
            default => null,
        };
    }
}

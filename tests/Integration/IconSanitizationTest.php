<?php
/**
 * LindemannRock Icon Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\iconmanager\tests\Integration;

use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\models\Icon;
use lindemannrock\iconmanager\models\IconSet;
use lindemannrock\iconmanager\tests\TestCase;

/**
 * Pins Icon::getSvg() output through the sanitizer. The pre-fix regex-based
 * sanitizer caught <script> and event handlers but missed <foreignObject>
 * (which can embed HTML/JS in the XHTML namespace), xlink:href javascript:
 * URIs, and CSS url(javascript:...). The fix delegates to Craft's
 * Html::sanitizeSvg() — a single check pinning every vector closes the
 * regression door.
 */
final class IconSanitizationTest extends TestCase
{
    public function testGetSvgStripsScriptForeignObjectAndJavascriptUris(): void
    {
        $root = $this->seedTempIconRoot();

        // Packs every XSS vector the previous sanitizer missed, plus one
        // legitimate element (<rect>) we expect to survive — a survival
        // assertion catches the opposite regression of "sanitizer nukes
        // everything and we just don't notice."
        $maliciousSvg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24">
                <script type="text/javascript">alert('script')</script>
                <foreignObject width="100" height="100">
                    <body xmlns="http://www.w3.org/1999/xhtml">
                        <script>alert('foreignObject')</script>
                    </body>
                </foreignObject>
                <a xlink:href="javascript:alert('xlink')"><circle r="5"/></a>
                <style>.x { background: url(javascript:alert('css')); }</style>
                <rect width="10" height="10" onclick="alert('onclick')"/>
            </svg>
            SVG;

        file_put_contents($root . '/evil.svg', $maliciousSvg);

        $icon = new Icon();
        $icon->type = Icon::TYPE_SVG;
        $icon->path = 'evil.svg';

        $sanitized = $icon->getSvg();

        $this->assertNotNull($sanitized, 'Sanitizer should return a non-null result for a valid SVG.');
        $this->assertStringNotContainsStringIgnoringCase('<script', $sanitized, 'script tags must be stripped');
        $this->assertStringNotContainsStringIgnoringCase('<foreignObject', $sanitized, 'foreignObject must be stripped (HTML/JS namespace bridge)');
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $sanitized, 'javascript: URIs must be stripped from href, xlink:href, and CSS url()');
        $this->assertStringNotContainsStringIgnoringCase('onclick', $sanitized, 'event handler attributes must be stripped');

        // Sanity check: legitimate SVG primitives survive sanitization.
        $this->assertStringContainsStringIgnoringCase('<rect', $sanitized, 'legitimate <rect> element should survive');
    }

    /**
     * Pins SvgOptimizerService::getSvgPreview() through the same sanitizer.
     * Pre-fix, the optimization tab rendered raw file_get_contents() via
     * `{{ svgPreview|raw }}` — a malicious SVG on disk would execute its
     * payload in the CP for any admin viewing the Optimization preview.
     */
    public function testGetSvgPreviewStripsScriptForeignObjectAndJavascriptUris(): void
    {
        $root = $this->seedTempIconRoot();

        $maliciousSvg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24">
                <script>alert('preview-script')</script>
                <foreignObject width="100" height="100">
                    <body xmlns="http://www.w3.org/1999/xhtml"><script>alert('preview-foreignObject')</script></body>
                </foreignObject>
                <a xlink:href="javascript:alert('preview-xlink')"><circle r="5"/></a>
                <style>.x { background: url(javascript:alert('preview-css')); }</style>
                <rect width="10" height="10" onclick="alert('preview-onclick')"/>
            </svg>
            SVG;

        file_put_contents($root . '/evil.svg', $maliciousSvg);

        $iconSet = new IconSet();
        $iconSet->type = 'svg-folder';
        $iconSet->settings = ['folder' => ''];

        $sanitized = IconManager::getInstance()->svgOptimizer->getSvgPreview($iconSet, 'evil.svg');

        $this->assertNotNull($sanitized, 'getSvgPreview should return the sanitized SVG.');
        $this->assertStringNotContainsStringIgnoringCase('<script', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('<foreignObject', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $sanitized);
        $this->assertStringContainsStringIgnoringCase('<rect', $sanitized);
    }
}

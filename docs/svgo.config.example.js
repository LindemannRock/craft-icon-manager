/**
 * Example SVGO Configuration for Icon Manager
 *
 * Place this file as `svgo.config.js` in your project root to customize
 * SVGO optimization behavior when using the Icon Manager CLI optimization.
 *
 * Usage:
 *   ./craft icon-manager/optimize --set=ID --engine=svgo
 *
 * @see https://github.com/svg/svgo
 */

export default {
    plugins: [
        {
            name: 'preset-default',
            params: {
                overrides: {
                    // Don't convert colors - this can cause issues with dynamic styling
                    convertColors: false,

                    // Don't merge paths - can affect rendering and animations
                    mergePaths: false,

                    // Keep inline styles - allows CSS customization
                    inlineStyles: {
                        onlyMatchedOnce: false,
                    },

                    // Don't remove viewBox - needed for responsive SVGs
                    removeViewBox: false,
                },
            },
        },

        // Remove width and height attributes, keep viewBox
        // This makes SVGs responsive and easier to style with CSS
        'removeDimensions',

        // Remove empty containers that don't affect rendering
        'removeEmptyContainers',

        // Remove editor metadata (Figma, Sketch, Illustrator, etc.)
        // This reduces file size without affecting appearance
        'removeEditorsNSData',

        // Remove hidden elements
        'removeHiddenElems',

        // Remove empty text elements
        'removeEmptyText',

        // Clean up IDs (optional - uncomment if needed)
        // {
        //     name: 'cleanupIds',
        //     params: {
        //         minify: true,
        //         preserve: [],
        //     },
        // },
    ],

    // Optional: Multipass optimization (runs optimization multiple times)
    // multipass: true,
};

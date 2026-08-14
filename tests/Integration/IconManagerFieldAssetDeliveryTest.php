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
use craft\db\Connection;
use craft\web\AssetBundle;
use craft\web\AssetManager;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use lindemannrock\iconmanager\IconManager;
use lindemannrock\iconmanager\tests\TestCase;
use lindemannrock\iconmanager\web\assets\field\IconManagerFieldAsset;

/**
 * Covers static asset delivery for Icon Manager fields.
 *
 * @since 5.18.0
 */
final class IconManagerFieldAssetDeliveryTest extends TestCase
{
    public function testComposerAliasResolvesInstalledFieldAssets(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        $sourcePath = Craft::getAlias('@lindemannrock/iconmanager');
        $distPath = Craft::getAlias('@lindemannrock/iconmanager/web/assets/field/dist');
        $composerInfo = Craft::$app->getPlugins()->getComposerPluginInfo('icon-manager');

        self::assertIsArray($composerInfo);
        self::assertSame('lindemannrock/craft-icon-manager', $composerInfo['packageName']);
        self::assertSame(realpath($packageRoot . '/src'), realpath($sourcePath));
        self::assertSame(realpath($sourcePath), realpath($composerInfo['aliases']['@lindemannrock/iconmanager']));
        self::assertSame(realpath($packageRoot . '/src/web/assets/field/dist'), realpath($distPath));
        self::assertFileExists($distPath . '/IconManagerField.js');
    }

    public function testBundleInitializesWithoutPluginDatabaseOrPublicationState(): void
    {
        $originalAlias = Craft::getAlias('@lindemannrock/iconmanager');
        $originalAssetManager = Craft::$app->getAssetManager();
        $originalDb = Craft::$app->getDb();
        $originalPlugin = IconManager::$plugin;
        $aliasRoot = sys_get_temp_dir() . '/icon-manager-field-assets-' . bin2hex(random_bytes(8));
        $offlineDb = new Connection([
            'dsn' => 'unsupported:icon-manager-field-asset-test',
        ]);
        $assetManager = new class() extends AssetManager {
            /** @var list<string> */
            public array $publicationPaths = [];

            /**
             * @inheritdoc
             */
            public function publish($path, $options = []): array
            {
                $this->publicationPaths[] = Craft::getAlias($path);
                throw new \RuntimeException('Runtime asset publication is unavailable.');
            }
        };

        try {
            Craft::setAlias('@lindemannrock/iconmanager', $aliasRoot);
            Craft::$app->set('assetManager', $assetManager);
            Craft::$app->set('db', $offlineDb);
            IconManager::$plugin = null;

            $bundle = new IconManagerFieldAsset();

            self::assertFalse($offlineDb->getIsActive());
            self::assertSame($aliasRoot . '/web/assets/field/dist', $bundle->sourcePath);
            self::assertSame(['IconManagerField.js'], $bundle->js);
            self::assertSame([], $bundle->css);
            self::assertSame([CpAsset::class], $bundle->depends);
            self::assertSame([], $bundle->jsOptions);
            self::assertSame([], $bundle->cssOptions);
            self::assertSame([], $bundle->publishOptions);
            self::assertSame([], $assetManager->publicationPaths);
        } finally {
            IconManager::$plugin = $originalPlugin;
            Craft::$app->set('db', $originalDb);
            Craft::$app->set('assetManager', $originalAssetManager);
            Craft::setAlias('@lindemannrock/iconmanager', $originalAlias);
        }
    }

    public function testRegisteredBundleUsesConfiguredBaseUrlOnceAfterDependency(): void
    {
        $originalAssetManager = Craft::$app->getAssetManager();
        $originalView = Craft::$app->getView();
        $distPath = Craft::getAlias('@lindemannrock/iconmanager/web/assets/field/dist');
        $publicationRoot = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'asset_publication_');
        $baseUrl = 'https://cdn.example.test/icon-manager-field';
        $dependencyBaseUrl = 'https://cdn.example.test/craft-cp';
        $assetManager = new class([ 'basePath' => $publicationRoot, 'baseUrl' => '/unavailable-runtime-assets', 'appendTimestamp' => false, 'blockedPath' => $distPath, 'bundles' => [ IconManagerFieldAsset::class => [ 'basePath' => $distPath, 'baseUrl' => $baseUrl, ], CpAsset::class => [ 'class' => AssetBundle::class, 'basePath' => $distPath, 'baseUrl' => $dependencyBaseUrl, 'js' => ['cp-runtime.js'], ], ], ]) extends AssetManager {
            public string $blockedPath;

            /** @var list<string> */
            public array $publicationPaths = [];

            /**
             * @inheritdoc
             */
            public function publish($path, $options = []): array
            {
                $resolvedPath = Craft::getAlias($path);
                $this->publicationPaths[] = $resolvedPath;
                if ($resolvedPath === $this->blockedPath) {
                    throw new \RuntimeException('Runtime publication of the field package is unavailable.');
                }

                return parent::publish($path, $options);
            }
        };
        $view = new View();

        try {
            Craft::$app->set('assetManager', $assetManager);
            Craft::$app->set('view', $view);

            $firstBundle = $view->registerAssetBundle(IconManagerFieldAsset::class);
            $secondBundle = $view->registerAssetBundle(IconManagerFieldAsset::class);

            self::assertSame($firstBundle, $secondBundle);
            self::assertSame([IconManagerFieldAsset::class, CpAsset::class], array_keys($view->assetBundles));
            self::assertSame($distPath, $firstBundle->sourcePath);
            self::assertSame($distPath, $firstBundle->basePath);
            self::assertSame($baseUrl, $firstBundle->baseUrl);
            self::assertSame(['IconManagerField.js'], $firstBundle->js);
            self::assertSame([], $firstBundle->css);
            self::assertSame([CpAsset::class], $firstBundle->depends);
            self::assertSame([], $firstBundle->jsOptions);
            self::assertSame([], $firstBundle->cssOptions);

            $headHtml = $view->getHeadHtml(false);
            $bodyHtml = $view->getBodyHtml();
            $dependencyUrl = $dependencyBaseUrl . '/cp-runtime.js';
            $fieldUrl = $baseUrl . '/IconManagerField.js';

            self::assertStringNotContainsString($dependencyUrl, $headHtml);
            self::assertStringNotContainsString($fieldUrl, $headHtml);
            self::assertSame(1, substr_count($bodyHtml, $dependencyUrl), $bodyHtml);
            self::assertSame(1, substr_count($bodyHtml, $fieldUrl), $bodyHtml);
            self::assertLessThan(strpos($bodyHtml, $fieldUrl), strpos($bodyHtml, $dependencyUrl));
            self::assertSame([], $assetManager->publicationPaths);
        } finally {
            $view->clear();
            Craft::$app->set('view', $originalView);
            Craft::$app->set('assetManager', $originalAssetManager);
        }
    }

    public function testCustomerArchivesIncludePackagedAssets(): void
    {
        $expected = [
            'src/icon.svg',
            'src/web/assets/field/dist/IconManagerField.js',
        ];
        $packageRoot = dirname(__DIR__, 2);
        $archiveRoot = $this->createTrackedTempDirectory(self::MARKER_PREFIX . 'asset_archives_');

        foreach ($expected as $path) {
            self::assertFileExists($packageRoot . '/' . $path, $path);
        }

        [$exitCode, $output, $error] = $this->runProcess([
            'git',
            '-c',
            'safe.directory=' . $packageRoot,
            'ls-files',
            '--error-unmatch',
            '--',
            ...$expected,
        ], $packageRoot);
        self::assertSame(0, $exitCode, $error);
        foreach ($expected as $path) {
            self::assertStringContainsString($path, $output);
        }

        $gitArchivePath = $archiveRoot . '/git-package.tar';
        [$exitCode, , $error] = $this->runProcess([
            'git',
            '-c',
            'safe.directory=' . $packageRoot,
            'archive',
            '--format=tar',
            '--output=' . $gitArchivePath,
            'HEAD',
        ], $packageRoot);
        self::assertSame(0, $exitCode, $error);

        [$exitCode, , $error] = $this->runProcess([
            'composer',
            'archive',
            '--format=tar',
            '--dir=' . $archiveRoot,
            '--file=customer-package',
            '--no-interaction',
            '--no-scripts',
        ], $packageRoot);
        self::assertSame(0, $exitCode, $error);

        $gitArchive = new \PharData($gitArchivePath);
        $composerArchive = new \PharData($archiveRoot . '/customer-package.tar');
        foreach ($expected as $path) {
            self::assertTrue(isset($gitArchive[$path]), "Git archive is missing {$path}");
            self::assertTrue(isset($composerArchive[$path]), "Composer archive is missing {$path}");
        }
    }

    /**
     * @param list<string> $command
     * @return array{int, string, string}
     */
    private function runProcess(array $command, string $workingDirectory): array
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory,
        );

        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            proc_close($process),
            is_string($output) ? $output : '',
            is_string($error) ? $error : '',
        ];
    }
}

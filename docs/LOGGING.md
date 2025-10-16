# Icon Manager Logging

Icon Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized, structured logging across all LindemannRock plugins.

## Log Levels

- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

## Configuration

### Control Panel

1. Navigate to **Settings → Icon Manager → General**
2. Scroll to **Logging Settings**
3. Select desired log level from dropdown
4. Click **Save**

### Config File

```php
// config/icon-manager.php
return [
    'logLevel' => 'error', // error, warning, info, or debug
];
```

**Note:** Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

## Log Files

- **Location**: `storage/logs/icon-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at Icon Manager → Logs

## What's Logged

The plugin logs meaningful events using context arrays for structured data. All logs include user context when available.

### Cache Operations (CacheController)

- **[INFO]** `Starting icon cache clearing operation` - Cache clearing initiated
- **[INFO]** `Icon cache cleared successfully` - Cache cleared successfully
  - Context: `totalCaches` (number of caches cleared), `cacheStats` (detailed cache statistics)
- **[ERROR]** `Failed to clear icon cache` - Cache clearing failure
  - Context: `error` (exception message)

### Icon Set Operations (IconSetsController)

- **[ERROR]** `Could not save optimized SVGs` - SVG optimization save failed
  - Context: `error` (exception message)

### Utilities Operations (UtilitiesController)

- **[ERROR]** `Failed to refresh icon set` - Individual icon set refresh failure
  - Context: `iconSetName`, `error` (exception message)
- **[ERROR]** `Failed to refresh all icons` - Bulk icon refresh failure
  - Context: `error` (exception message)
- **[ERROR]** `Failed to scan SVGs` - SVG scanning operation failed
  - Context: `error` (exception message)

### Icon Rendering (IconsController)

- **[WARNING]** `Icon render failed - icon set not found or disabled` - Icon set unavailable for rendering
  - Context: `iconSetHandle`
- **[WARNING]** `Icon render failed - icon not found` - Specific icon not found in set
  - Context: `iconSetHandle`, `iconName`
- **[WARNING]** `Icon data request failed - icon set not found` - Icon set unavailable for data request
  - Context: `iconSetHandle`
- **[WARNING]** `Icon data request failed - icon not found` - Icon not found in data request
  - Context: `iconSetHandle`, `iconName`
- **[WARNING]** `Field not found` - Icon field not found by ID
  - Context: `fieldId`

### Icon Loading (IconsService)

- **[WARNING]** `Slow icon loading detected for icon set` - Icon set loading took >1 second
  - Context: `iconSetId`, `duration` (seconds, rounded to 3 decimals)
- **[WARNING]** `Folder path does not exist` - Icon folder path invalid
  - Context: `folderPath`
- **[INFO]** `Found SVG files` - SVG file discovery completed
  - Context: `count` (number of files), `folderPath`
- **[INFO]** `Cleared memory cache for icon sets` - Memory cache cleared
  - Context: `cacheCount` (number of caches cleared)

### Icon Model (Icon)

#### File Operations
- **[WARNING]** `Failed to parse JSON label file` - JSON metadata file parsing failed
  - Context: `jsonPath`
- **[WARNING]** `Icon file not found: {iconName}` - Icon file missing from filesystem
  - Context: `iconId`, `expectedPath`
- **[ERROR]** `Failed to read icon file: {iconName}` - Icon file read error
  - Context: `iconId`, `filePath`
- **[WARNING]** `Icon file is empty: {iconName}` - Icon file exists but contains no content
  - Context: `iconId`, `filePath`

#### SVG Processing
- **[WARNING]** `SVG content removed during sanitization: {iconName}` - Sanitization removed all SVG content
  - Context: `iconId`, `originalLength`
- **[DEBUG]** `WebFont icon details` - WebFont icon processing details
  - Context: `iconName`, `unicode`
- **[WARNING]** `SVG content is empty for icon: {iconName}` - SVG content empty after processing
  - Context: `iconId`, `iconSet`
- **[ERROR]** `Failed to parse SVG content for icon: {iconName}` - SVG XML parsing failed
  - Context: `iconId`, `iconSet`
- **[WARNING]** `No SVG element found in parsed content for icon: {iconName}` - SVG element missing
  - Context: `iconId`, `iconSet`

## Log Management

### Via Control Panel

1. Navigate to **Icon Manager → Logs**
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

### Via Command Line

**View today's log**:

```bash
tail -f storage/logs/icon-manager-$(date +%Y-%m-%d).log
```

**View specific date**:

```bash
cat storage/logs/icon-manager-2025-01-15.log
```

**Search across all logs**:

```bash
grep "Icon render" storage/logs/icon-manager-*.log
```

**Filter by log level**:

```bash
grep "\[ERROR\]" storage/logs/icon-manager-*.log
```

## Log Format

Each log entry follows structured JSON format with context data:

```json
{
  "timestamp": "2025-01-15 14:30:45",
  "level": "WARNING",
  "message": "Icon render failed - icon not found",
  "context": {
    "iconSetHandle": "heroicons",
    "iconName": "arrow-right",
    "userId": 1
  },
  "category": "lindemannrock\\iconmanager\\controllers\\IconsController"
}
```

## Using the Logging Trait

All services and controllers in Icon Manager use the `LoggingTrait` from the LindemannRock Logging Library:

```php
use lindemannrock\logginglibrary\traits\LoggingTrait;

class MyService extends Component
{
    use LoggingTrait;

    public function myMethod()
    {
        // Info level - general operations
        $this->logInfo('Operation started', ['param' => $value]);

        // Warning level - important but non-critical
        $this->logWarning('Missing data', ['key' => $missingKey]);

        // Error level - failures and exceptions
        $this->logError('Operation failed', ['error' => $e->getMessage()]);

        // Debug level - detailed information
        $this->logDebug('Processing item', ['item' => $itemData]);
    }
}
```

## Performance Considerations

- **Error/Warning levels**: Minimal performance impact, suitable for production
- **Info level**: Moderate logging, useful for tracking operations
- **Debug level**: Extensive logging, use only in development (requires devMode)
  - Includes performance metrics
  - Logs WebFont icon details
  - Tracks icon loading operations

## Requirements

Icon Manager logging requires:

- **lindemannrock/logginglibrary** plugin (installed automatically as dependency)
- Write permissions on `storage/logs` directory
- Craft CMS 5.x or later

## Troubleshooting

If logs aren't appearing:

1. **Check permissions**: Verify `storage/logs` directory is writable
2. **Verify library**: Ensure LindemannRock Logging Library is installed and enabled
3. **Check log level**: Confirm log level allows the messages you're looking for
4. **devMode for debug**: Debug level requires `devMode` enabled in `config/general.php`
5. **Check CP interface**: Use Icon Manager → Logs to verify log files exist

## Common Scenarios

### Missing Icon Issues

When icons fail to render or display:

```bash
grep "Icon.*not found\|Icon file" storage/logs/icon-manager-*.log
```

Look for:
- `Icon file not found` - File missing from filesystem, check icon set path
- `Icon render failed - icon not found` - Icon doesn't exist in the specified set
- `Icon render failed - icon set not found or disabled` - Icon set unavailable or disabled
- `Icon file is empty` - File exists but has no content

Common causes:
- Incorrect icon set path configuration
- File permissions issues
- Icons not refreshed after adding new files
- Icon set disabled in settings

### SVG Processing Issues

Debug SVG content and parsing problems:

```bash
grep "SVG" storage/logs/icon-manager-*.log
```

Common issues:
- `SVG content removed during sanitization` - Sanitizer removed malicious/invalid content
- `Failed to parse SVG content` - Invalid XML structure
- `No SVG element found` - File doesn't contain valid SVG element
- `SVG content is empty` - Empty SVG after processing

These usually indicate:
- Corrupted or invalid SVG files
- Security issues in SVG content
- Malformed XML structure

### Performance Issues

Monitor slow icon operations:

```bash
grep "Slow icon loading" storage/logs/icon-manager-*.log
```

When icon loading takes >1 second:
- Check icon set size (500+ icons may be slow)
- Verify caching is enabled
- Consider splitting large icon sets
- Check for file system performance issues
- Review icon file sizes (optimize if >10KB)

### Cache Clearing Issues

Track cache operations:

```bash
grep "cache" storage/logs/icon-manager-*.log
```

Look for:
- `Starting icon cache clearing operation` - Cache clear initiated
- `Icon cache cleared successfully` - Operation completed
- `Failed to clear icon cache` - Cache clearing failed
- `Cleared memory cache for icon sets` - Memory cache cleared

If cache clearing fails:
- Check write permissions on cache directories
- Verify cache path configuration
- Check for file system errors

### Icon Set Refresh Failures

When icon sets fail to refresh:

```bash
grep "Failed to refresh" storage/logs/icon-manager-*.log
```

Common causes:
- Database connection issues
- Invalid icon set configuration
- File system permission problems
- Corrupted icon files
- Missing directories

### Field Operations

Debug field-related issues:

```bash
grep "Field not found" storage/logs/icon-manager-*.log
```

This warning appears when:
- Field ID doesn't exist in database
- Field was deleted but still referenced
- Incorrect field ID passed to icon rendering

## Development Tips

### Enable Debug Logging

For detailed troubleshooting during development:

```php
// config/icon-manager.php
return [
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

This provides:
- WebFont icon processing details
- Icon loading performance metrics
- SVG file discovery counts
- Cache operation details

### Monitor Specific Operations

Track specific operations using grep:

```bash
# Monitor all icon rendering attempts
grep "Icon render" storage/logs/icon-manager-*.log

# Watch logs in real-time
tail -f storage/logs/icon-manager-$(date +%Y-%m-%d).log

# Check all errors
grep "\[ERROR\]" storage/logs/icon-manager-*.log

# Monitor performance issues
grep "Slow icon loading" storage/logs/icon-manager-*.log
```

### Performance Monitoring

Track icon loading performance:

```bash
# Find slow icon operations (>1 second)
grep "Slow icon loading" storage/logs/icon-manager-*.log

# Check cache statistics
grep "Icon cache cleared successfully" storage/logs/icon-manager-*.log
```

Review the `duration` context to identify:
- Which icon sets load slowly
- Whether caching is effective
- If optimization is needed

### File Operation Logging

Icon Manager focuses logging on actual errors to prevent log flooding:

- **Normal operations**: Not logged (prevents noise from routine icon rendering)
- **Errors only**: Logged when files are missing, empty, or fail to parse
- **Context included**: Icon ID, file path, and error details for debugging

This approach ensures logs remain useful for troubleshooting without being overwhelmed by routine operations.

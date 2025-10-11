# Icon Manager - Template Examples

Real-world examples for using Icon Manager in your Craft CMS templates.

## Table of Contents

- [Basic Icon Rendering](#basic-icon-rendering)
- [Navigation Menus](#navigation-menus)
- [Feature Cards](#feature-cards)
- [Social Media Links](#social-media-links)
- [Button Components](#button-components)
- [Icon Grid Gallery](#icon-grid-gallery)
- [Dynamic Icon Selection](#dynamic-icon-selection)
- [Multi-Site Icons](#multi-site-icons)
- [Conditional Rendering](#conditional-rendering)
- [Icon with Tailwind CSS](#icon-with-tailwind-css)

## Basic Icon Rendering

### Simple Icon
```twig
{# Single icon field #}
{{ entry.iconField.render() }}

{# With size #}
{{ entry.iconField.render({width: 24, height: 24}) }}

{# With custom class #}
{{ entry.iconField.render({class: 'text-blue-500', width: 32, height: 32}) }}
```

### Multiple Icons Field
```twig
{% if entry.iconsField %}
    <div class="icon-list">
        {% for icon in entry.iconsField %}
            {{ icon.render({width: 20, height: 20, class: 'inline-icon'}) }}
        {% endfor %}
    </div>
{% endif %}
```

## Navigation Menus

### Main Navigation with Icons
```twig
<nav class="main-nav">
    {% for item in craft.entries.section('navigation').all() %}
        <a href="{{ item.url }}" class="nav-item">
            {% if item.navIcon %}
                {{ item.navIcon.render({width: 20, height: 20, class: 'nav-icon'}) }}
            {% endif %}
            <span>{{ item.title }}</span>
        </a>
    {% endfor %}
</nav>

<style>
.nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
}
.nav-icon {
    flex-shrink: 0;
}
</style>
```

### Sidebar Menu with Active States
```twig
<aside class="sidebar">
    {% set currentUrl = craft.app.request.url %}
    {% for item in craft.entries.section('sidebarMenu').all() %}
        {% set isActive = currentUrl starts with item.url %}
        <a href="{{ item.url }}" class="sidebar-item {{ isActive ? 'active' : '' }}">
            {% if item.menuIcon %}
                {{ item.menuIcon.render({
                    width: 24,
                    height: 24,
                    class: isActive ? 'icon-active' : 'icon-default'
                }) }}
            {% endif %}
            <span>{{ item.title }}</span>
        </a>
    {% endfor %}
</aside>

<style>
.sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    color: #6b7280;
}
.sidebar-item.active {
    color: #3b82f6;
    background: #eff6ff;
}
.icon-default { color: #9ca3af; }
.icon-active { color: #3b82f6; }
</style>
```

## Feature Cards

### Features Grid with Icons
```twig
<div class="features-grid">
    {% for feature in entry.features.all() %}
        <div class="feature-card">
            {% if feature.featureIcon %}
                <div class="feature-icon">
                    {{ feature.featureIcon.render({width: 48, height: 48}) }}
                </div>
            {% endif %}
            <h3>{{ feature.title }}</h3>
            <p>{{ feature.description }}</p>
        </div>
    {% endfor %}
</div>

<style>
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
}
.feature-card {
    padding: 32px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    text-align: center;
}
.feature-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    padding: 12px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-radius: 12px;
    color: #3b82f6;
}
</style>
```

## Social Media Links

### Social Icons Footer
```twig
<footer class="site-footer">
    <div class="social-links">
        {% for social in entry.socialLinks.all() %}
            <a href="{{ social.url }}"
               target="_blank"
               rel="noopener noreferrer"
               title="{{ social.platform }}"
               class="social-icon">
                {% if social.platformIcon %}
                    {{ social.platformIcon.render({width: 24, height: 24}) }}
                {% endif %}
            </a>
        {% endfor %}
    </div>
</footer>

<style>
.social-links {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.social-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f3f4f6;
    color: #374151;
    transition: all 0.2s;
}
.social-icon:hover {
    background: #3b82f6;
    color: white;
    transform: translateY(-2px);
}
</style>
```

## Button Components

### Icon Buttons
```twig
{# Primary button with icon #}
<button class="btn-primary">
    {% set downloadIcon = craft.iconManager.getIcon('lucide', 'download') %}
    {{ downloadIcon.render({width: 20, height: 20}) }}
    <span>Download PDF</span>
</button>

{# Icon-only button #}
<button class="btn-icon" aria-label="Settings">
    {% set settingsIcon = craft.iconManager.getIcon('lucide', 'settings') %}
    {{ settingsIcon.render({width: 20, height: 20}) }}
</button>

<style>
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #3b82f6;
    color: white;
    border-radius: 6px;
}
.btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 6px;
    background: transparent;
    color: #6b7280;
    border: 1px solid #e5e7eb;
}
.btn-icon:hover {
    background: #f3f4f6;
    color: #374151;
}
</style>
```

### Loading States
```twig
<button class="btn-submit" id="submit-btn">
    <span class="btn-content">
        {% set sendIcon = craft.iconManager.getIcon('lucide', 'send') %}
        {{ sendIcon.render({width: 20, height: 20}) }}
        <span>Send Message</span>
    </span>
    <span class="btn-loading" style="display: none;">
        {% set loaderIcon = craft.iconManager.getIcon('lucide', 'loader-2') %}
        {{ loaderIcon.render({width: 20, height: 20, class: 'spinner'}) }}
        <span>Sending...</span>
    </span>
</button>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spinner {
    animation: spin 1s linear infinite;
}
</style>

<script>
document.getElementById('submit-btn').addEventListener('click', function() {
    this.querySelector('.btn-content').style.display = 'none';
    this.querySelector('.btn-loading').style.display = 'flex';
});
</script>
```

## Icon Grid Gallery

### Browse All Icons
```twig
{# Display all icons from a set #}
{% set icons = craft.iconManager.getIcons('lucide') %}

<div class="icon-gallery">
    <h2>Available Icons ({{ icons|length }})</h2>

    <div class="icon-grid">
        {% for icon in icons %}
            <div class="icon-item" title="{{ icon.name }}">
                {{ icon.render({width: 32, height: 32}) }}
                <span class="icon-name">{{ icon.name }}</span>
            </div>
        {% endfor %}
    </div>
</div>

<style>
.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 16px;
}
.icon-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    text-align: center;
    transition: all 0.2s;
}
.icon-item:hover {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}
.icon-name {
    margin-top: 8px;
    font-size: 11px;
    color: #6b7280;
}
</style>
```

## Dynamic Icon Selection

### Icon Picker Based on Field Value
```twig
{# Entry has a select field 'status' with options: success, warning, error #}
{% set statusIcons = {
    'success': 'check-circle',
    'warning': 'alert-triangle',
    'error': 'x-circle'
} %}

{% set iconName = statusIcons[entry.status] ?? 'info' %}
{% set statusIcon = craft.iconManager.getIcon('lucide', iconName) %}

<div class="status-badge status-{{ entry.status }}">
    {{ statusIcon.render({width: 20, height: 20}) }}
    <span>{{ entry.status|title }}</span>
</div>

<style>
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}
.status-success { background: #d1fae5; color: #065f46; }
.status-warning { background: #fef3c7; color: #78350f; }
.status-error { background: #fee2e2; color: #991b1b; }
</style>
```

### Platform-Specific Icons
```twig
{# Detect user platform and show appropriate icon #}
{% set userAgent = craft.app.request.userAgent|lower %}
{% set platform = 'desktop' %}

{% if 'iphone' in userAgent or 'ipad' in userAgent %}
    {% set platform = 'apple' %}
{% elseif 'android' in userAgent %}
    {% set platform = 'android' %}
{% elseif 'windows' in userAgent %}
    {% set platform = 'windows' %}
{% endif %}

{% set platformIcon = craft.iconManager.getIcon('lucide', platform) %}
{{ platformIcon.render({width: 24, height: 24}) }}
```

## Multi-Site Icons

### Language-Specific Icons with Custom Labels
```twig
{# Icon with site-specific custom label #}
{% for icon in entry.productFeatures %}
    <div class="feature">
        {{ icon.render({width: 32, height: 32, class: 'feature-icon'}) }}
        <h4>{{ icon.displayLabel }}</h4>
        {# displayLabel automatically resolves:
           1. Site-specific custom label (if set)
           2. General custom label
           3. JSON metadata label for current site language
           4. Fallback to icon name #}
    </div>
{% endfor %}
```

### RTL Support
```twig
{# Flip icons for RTL languages #}
{% set isRtl = currentSite.language in ['ar', 'he', 'fa'] %}

<button class="nav-btn">
    {% set arrowIcon = craft.iconManager.getIcon('lucide', 'arrow-right') %}
    {{ arrowIcon.render({
        width: 20,
        height: 20,
        style: isRtl ? 'transform: scaleX(-1);' : ''
    }) }}
    <span>{{ 'Next'|t }}</span>
</button>
```

## Conditional Rendering

### Show Icon Only If Set
```twig
{# Safely handle optional icons #}
{% if entry.alertIcon and entry.alertIcon.exists() %}
    <div class="alert">
        {{ entry.alertIcon.render({width: 24, height: 24, class: 'alert-icon'}) }}
        <p>{{ entry.alertMessage }}</p>
    </div>
{% else %}
    <div class="alert">
        <p>{{ entry.alertMessage }}</p>
    </div>
{% endif %}
```

### Fallback Icons
```twig
{# Use fallback icon if primary icon doesn't exist #}
{% set primaryIcon = craft.iconManager.getIcon(entry.iconSet.handle, entry.iconName) %}
{% set fallbackIcon = craft.iconManager.getIcon('lucide', 'help-circle') %}

{{ (primaryIcon ?: fallbackIcon).render({width: 24, height: 24}) }}
```

### Icon Search Results
```twig
{# Search and display icons #}
{% set searchTerm = craft.app.request.getParam('search') %}

{% if searchTerm %}
    {% set results = craft.iconManager.searchIcons(searchTerm, ['lucide', 'fontAwesome']) %}

    <h3>Search Results for "{{ searchTerm }}" ({{ results|length }})</h3>

    <div class="search-results">
        {% for icon in results %}
            <div class="result-item">
                {{ icon.render({width: 32, height: 32}) }}
                <div class="result-info">
                    <strong>{{ icon.name }}</strong>
                    <span class="icon-set">{{ icon.iconSet.name }}</span>
                </div>
            </div>
        {% endfor %}
    </div>
{% endif %}
```

## Icon with Tailwind CSS

### Tailwind Utility Classes
```twig
{# Using Tailwind CSS classes #}
<div class="flex items-center gap-2">
    {{ entry.icon.render({
        width: 20,
        height: 20,
        class: 'text-blue-500 hover:text-blue-700 transition-colors'
    }) }}
    <span>{{ entry.title }}</span>
</div>

{# Responsive sizes with Tailwind #}
{{ entry.icon.render({
    class: 'w-6 h-6 sm:w-8 sm:h-8 md:w-10 md:h-10'
}) }}

{# Dynamic colors #}
{{ entry.icon.render({
    width: 24,
    height: 24,
    class: 'text-' ~ entry.colorScheme ~ '-500'
}) }}
```

### Icon with Tailwind Background
```twig
<div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100">
    {{ entry.serviceIcon.render({
        width: 24,
        height: 24,
        class: 'text-blue-600'
    }) }}
</div>
```

## Advanced Examples

### Icon Matrix Block
```twig
{# Matrix field with icon + text blocks #}
{% for block in entry.contentBlocks.all() %}
    {% switch block.type %}
        {% case 'iconText' %}
            <div class="icon-text-block">
                {% if block.blockIcon %}
                    <div class="block-icon">
                        {{ block.blockIcon.render({width: 40, height: 40}) }}
                    </div>
                {% endif %}
                <div class="block-content">
                    <h3>{{ block.heading }}</h3>
                    <p>{{ block.text }}</p>
                </div>
            </div>
        {% case 'iconList' %}
            <ul class="icon-list">
                {% for item in block.listItems.all() %}
                    <li>
                        {{ item.icon.render({width: 16, height: 16}) }}
                        {{ item.text }}
                    </li>
                {% endfor %}
            </ul>
    {% endswitch %}
{% endfor %}
```

### Animated Icons
```twig
{# Rotating loader #}
{% set loader = craft.iconManager.getIcon('lucide', 'loader-2') %}
{{ loader.render({width: 24, height: 24, class: 'animate-spin'}) }}

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>

{# Pulsing notification icon #}
<div class="notification-icon">
    {% set bellIcon = craft.iconManager.getIcon('lucide', 'bell') %}
    {{ bellIcon.render({width: 20, height: 20}) }}
    <span class="pulse-dot"></span>
</div>

<style>
.notification-icon {
    position: relative;
    display: inline-block;
}
.pulse-dot {
    position: absolute;
    top: 0;
    right: 0;
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.1); }
}
</style>
```

### Icon Toggle States
```twig
{# Heart icon with filled/outline states #}
<button class="favorite-btn" data-favorited="{{ entry.isFavorited }}"  onclick="toggleFavorite(this)">
    <span class="icon-outline">
        {% set heartOutline = craft.iconManager.getIcon('lucide', 'heart') %}
        {{ heartOutline.render({width: 24, height: 24}) }}
    </span>
    <span class="icon-filled" style="display: none;">
        {% set heartFilled = craft.iconManager.getIcon('lucide', 'heart-filled') %}
        {{ heartFilled.render({width: 24, height: 24, class: 'text-red-500'}) }}
    </span>
</button>

<script>
function toggleFavorite(btn) {
    const isFavorited = btn.dataset.favorited === 'true';
    btn.querySelector('.icon-outline').style.display = isFavorited ? 'inline' : 'none';
    btn.querySelector('.icon-filled').style.display = isFavorited ? 'none' : 'inline';
    btn.dataset.favorited = !isFavorited;

    // Your AJAX call to save state here
}
</script>
```

### Accessibility Best Practices
```twig
{# Decorative icon (hidden from screen readers) #}
<button>
    {{ entry.icon.render({
        width: 20,
        height: 20,
        'aria-hidden': 'true'
    }) }}
    <span>Click Me</span>
</button>

{# Icon with accessible label #}
<button aria-label="Close dialog">
    {% set closeIcon = craft.iconManager.getIcon('lucide', 'x') %}
    {{ closeIcon.render({
        width: 24,
        height: 24,
        'aria-hidden': 'true'
    }) }}
</button>

{# Icon as meaningful image #}
<div>
    {{ entry.statusIcon.render({
        width: 24,
        height: 24,
        role: 'img',
        'aria-label': 'Status: ' ~ entry.statusLabel
    }) }}
</div>
```

## Real-World Component Examples

### Alert/Notification Component
```twig
{% macro alert(type, message, icon) %}
    {% set alertStyles = {
        'success': 'bg-green-50 border-green-200 text-green-800',
        'warning': 'bg-yellow-50 border-yellow-200 text-yellow-800',
        'error': 'bg-red-50 border-red-200 text-red-800',
        'info': 'bg-blue-50 border-blue-200 text-blue-800'
    } %}

    <div class="alert {{ alertStyles[type] }} border rounded-lg p-4 flex items-start gap-3">
        <div class="flex-shrink-0 mt-0.5">
            {{ icon.render({width: 20, height: 20}) }}
        </div>
        <div class="flex-1">
            {{ message }}
        </div>
    </div>
{% endmacro %}

{# Usage #}
{% import _self as components %}

{% set successIcon = craft.iconManager.getIcon('lucide', 'check-circle') %}
{{ components.alert('success', 'Your changes have been saved!', successIcon) }}

{% set errorIcon = craft.iconManager.getIcon('lucide', 'alert-circle') %}
{{ components.alert('error', 'Something went wrong.', errorIcon) }}
```

### Card with Icon Header
```twig
<article class="service-card">
    <div class="card-header">
        {% if entry.serviceIcon %}
            <div class="service-icon-wrapper">
                {{ entry.serviceIcon.render({width: 32, height: 32}) }}
            </div>
        {% endif %}
    </div>
    <div class="card-body">
        <h3>{{ entry.title }}</h3>
        <p>{{ entry.description }}</p>
        <a href="{{ entry.url }}" class="card-link">
            Learn More
            {% set arrowIcon = craft.iconManager.getIcon('lucide', 'arrow-right') %}
            {{ arrowIcon.render({width: 16, height: 16}) }}
        </a>
    </div>
</article>

<style>
.service-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}
.card-header {
    padding: 24px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}
.service-icon-wrapper {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 12px;
    color: #0284c7;
}
.card-body {
    padding: 24px;
}
.card-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0284c7;
}
</style>
```

### Timeline with Icons
```twig
<div class="timeline">
    {% for step in entry.processSteps.all() %}
        <div class="timeline-item">
            <div class="timeline-icon">
                {{ step.stepIcon.render({width: 24, height: 24}) }}
            </div>
            <div class="timeline-content">
                <h4>{{ step.title }}</h4>
                <p>{{ step.description }}</p>
            </div>
        </div>
    {% endfor %}
</div>

<style>
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 16px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.timeline-item {
    position: relative;
    margin-bottom: 32px;
}
.timeline-icon {
    position: absolute;
    left: -40px;
    width: 32px;
    height: 32px;
    background: white;
    border: 2px solid #3b82f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
}
</style>
```

## Performance Optimization Examples

### Lazy Loading Icons
```twig
{# Only load icons when visible #}
<div class="icon-container" data-icon-set="lucide" data-icon-name="image" data-lazy-icon>
    <div class="icon-placeholder" style="width: 48px; height: 48px; background: #f3f4f6;"></div>
</div>

<script>
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const container = entry.target;
            const iconSet = container.dataset.iconSet;
            const iconName = container.dataset.iconName;

            // Load icon via AJAX
            fetch(`/actions/icon-manager/icons/render?set=${iconSet}&name=${iconName}`)
                .then(r => r.text())
                .then(html => {
                    container.innerHTML = html;
                    observer.unobserve(container);
                });
        }
    });
});

document.querySelectorAll('[data-lazy-icon]').forEach(el => observer.observe(el));
</script>
```

### Caching Icon Renders
```twig
{# Cache icon-heavy pages #}
{% cache using key "product-features-#{entry.id}" for 1 day %}
    <div class="features">
        {% for feature in entry.features.all() %}
            <div class="feature">
                {{ feature.icon.render({width: 32, height: 32}) }}
                <h3>{{ feature.title }}</h3>
            </div>
        {% endfor %}
    </div>
{% endcache %}
```

## See Also

- [Configuration Guide](CONFIGURATION.md) - Complete configuration reference
- [Main README](../README.md) - Plugin overview and installation
- [Craft CMS Documentation](https://craftcms.com/docs) - Craft CMS guides

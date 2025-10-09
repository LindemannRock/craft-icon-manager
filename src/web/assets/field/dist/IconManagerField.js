/**
 * Icon Manager Field JavaScript
 */
(function() {
    if (typeof IconManager === 'undefined') {
        window.IconManager = {};
    }

    IconManager.IconPicker = function(fieldId, settings) {
        this.fieldId = fieldId;
        this.settings = settings || {};
        this.icons = []; // Will be loaded via AJAX
        this.showSearch = settings.showSearch !== false;
        this.showLabels = settings.showLabels !== false;
        this.iconSize = settings.iconSize || 'medium';
        this.iconsPerPage = settings.iconsPerPage || 100;
        this.allowMultiple = settings.allowMultiple === true;
        this.selectedIcons = []; // For multi-selection
        this.iconsLoaded = false; // Track if icons have been loaded
        this.iconsLoading = false; // Track if icons are currently loading

        // Virtual scrolling properties
        this.virtualScrollBatchSize = 50; // Render 50 icons at a time
        this.virtualScrollRenderedIcons = {}; // Track rendered icons per grid {iconSetHandle: count}
        this.virtualScrollObservers = {}; // Track intersection observers per grid

        this.init();
    };

    IconManager.IconPicker.prototype = {
        init: function() {
            this.$field = document.getElementById(this.fieldId);
            if (!this.$field) return;

            this.$input = this.$field.querySelector('input[type="hidden"]');
            this.$selectBtn = this.$field.querySelector('.icon-manager-select-btn');
            this.$clearBtn = this.$field.querySelector('.icon-manager-clear-btn');
            this.$picker = this.$field.querySelector('.icon-manager-picker');
            this.$cancelBtn = this.$picker.querySelector('.icon-manager-cancel-btn');
            this.$customLabelInput = this.$field.querySelector('.icon-manager-custom-label-input');

            // Load fonts for any saved icons on page init
            this.loadInitialFonts();

            this.bindEvents();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Load initial value
            this.currentValue = this.getCurrentValue();
            
            // Initialize selected icons for multi-selection
            if (this.allowMultiple) {
                this.selectedIcons = Array.isArray(this.currentValue) ? this.currentValue.slice() : [];
            }
            
            // Handle custom label input changes
            this.bindCustomLabelInputs();
            
            // Make the selected icon area clickable to open picker
            var $selected = this.$field.querySelector('.icon-manager-selected');
            if ($selected) {
                $selected.addEventListener('click', function(e) {
                    // Don't trigger if clicking buttons or inputs in the actions area
                    if (e.target.closest('.icon-manager-actions')) {
                        return;
                    }
                    // Don't trigger if clicking custom label input
                    if (e.target.classList.contains('icon-manager-custom-label-input')) {
                        return;
                    }
                    e.preventDefault();
                    self.togglePicker();
                });
                // Add pointer cursor to indicate clickability
                $selected.style.cursor = 'pointer';
            }

            // Select button (toggle picker)
            if (this.$selectBtn) {
                this.$selectBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.togglePicker();
                });
            }

            // Clear button
            if (this.$clearBtn) {
                this.$clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent triggering the selected area click
                    self.clearSelection();
                });
            }
            
            // Cancel button - restore previous value
            if (this.$cancelBtn) {
                this.$cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.cancelSelection();
                });
            }
            
            // Done button
            var $doneBtn = this.$field.querySelector('.icon-manager-done-btn');
            if ($doneBtn) {
                $doneBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.hidePicker();
                });
            }
            
            // Search input
            var $searchInput = this.$picker.querySelector('.icon-manager-search-input');
            if ($searchInput) {
                // Live search as you type
                $searchInput.addEventListener('input', function(e) {
                    self.filterIcons(e.target.value);
                });
                
                // Prevent closing picker when clicking search
                $searchInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
                
                // Clear search on Escape
                $searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Escape') {
                        $searchInput.value = '';
                        self.filterIcons('');
                    }
                });
            }
            
            // Tab switching
            var $tabs = this.$picker.querySelectorAll('.tab');
            $tabs.forEach(function($tab) {
                $tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent closing the picker
                    self.switchTab($tab.dataset.iconSet);
                });
            });
            
            // Dropdown tab switching
            var $tabSelect = this.$picker.querySelector('.icon-manager-tab-select');
            if ($tabSelect) {
                $tabSelect.addEventListener('change', function(e) {
                    self.switchTab(e.target.value);
                    self.updateDropdownCount(e.target.value);
                });
            }
            
            // Font Awesome Kit support temporarily disabled

            // Prevent all clicks inside the picker from bubbling
            this.$picker.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Click outside to close
            document.addEventListener('click', function(e) {
                if (!self.$field.contains(e.target)) {
                    self.hidePicker();
                }
            });
        },
        
        showPicker: function() {
            this.$picker.classList.remove('hidden');

            // Store the current value when opening picker
            this.savedValue = this.getCurrentValue();
            if (this.allowMultiple) {
                this.savedSelectedIcons = this.selectedIcons.slice();
            }

            // Load icons if not already loaded
            if (!this.iconsLoaded && !this.iconsLoading) {
                this.fetchIconsForField();
            } else {
                // Icons already loaded, just display them
                this.showIconsInPicker();
            }

            // Focus search input for better UX
            this.focusSearchInput();
        },

        showIconsInPicker: function() {
            // Hide tabs for empty icon sets
            this.hideEmptyIconSets();

            // If there's a current value, switch to its icon set
            var currentValue = this.getCurrentValue();
            var targetIconSet = null;

            if (this.allowMultiple && this.selectedIcons.length > 0) {
                targetIconSet = this.selectedIcons[0].iconSetHandle;
            } else if (currentValue && currentValue.iconSetHandle) {
                targetIconSet = currentValue.iconSetHandle;
            }

            if (targetIconSet) {
                this.switchTab(targetIconSet);
                this.updateDropdownCount(targetIconSet);
            } else {
                // Initialize dropdown count for first tab
                var $tabSelect = this.$picker.querySelector('.icon-manager-tab-select');
                if ($tabSelect && $tabSelect.value) {
                    this.updateDropdownCount($tabSelect.value);
                } else {
                    // Find first non-empty tab
                    var firstNonEmptySet = this.findFirstNonEmptyIconSet();
                    if (firstNonEmptySet) {
                        this.switchTab(firstNonEmptySet);
                    }
                }
            }

            this.loadIcons();
            this.updateIconCounts();
        },

        fetchIconsForField: function() {
            var self = this;
            this.iconsLoading = true;

            // Show loading state
            var $grids = this.$picker.querySelectorAll('.icon-manager-grid');
            $grids.forEach(function($grid) {
                var $gridInner = $grid.querySelector('.icon-manager-grid-inner');
                if ($gridInner) {
                    $gridInner.innerHTML = '<div class="icon-manager-loading">Loading icons...</div>';
                }
            });

            // Fetch all icons for this field in one batch request
            fetch(Craft.getCpUrl('icon-manager/icons/get-icons-for-field'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': Craft.csrfTokenValue,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    fieldId: this.settings.fieldId
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.icons) {
                    self.icons = data.icons;
                    self.iconsLoaded = true;
                    self.iconsLoading = false;

                    // Load required fonts/CSS for font-based icons (Material Icons, Font Awesome)
                    if (data.fonts && data.fonts.length > 0) {
                        self.loadFonts(data.fonts);
                    }

                    // Load required sprites for sprite icon sets
                    if (data.sprites && data.sprites.length > 0) {
                        self.loadSprites(data.sprites);
                    }

                    self.showIconsInPicker();
                } else {
                    console.error('Failed to load icons:', data.error || 'Unknown error');
                    self.iconsLoading = false;
                }
            })
            .catch(function(error) {
                console.error('Failed to load icons:', error);
                self.iconsLoading = false;
                // Show error state
                $grids.forEach(function($grid) {
                    var $gridInner = $grid.querySelector('.icon-manager-grid-inner');
                    if ($gridInner) {
                        $gridInner.innerHTML = '<div class="icon-manager-error">Failed to load icons. Please try again.</div>';
                    }
                });
            });
        },
        
        hidePicker: function() {
            this.$picker.classList.add('hidden');
        },
        
        togglePicker: function() {
            if (this.$picker.classList.contains('hidden')) {
                this.showPicker();
            } else {
                this.hidePicker();
            }
        },
        
        loadIcons: function(searchQuery) {
            var self = this;
            
            // Get all grid containers
            var $grids = this.$picker.querySelectorAll('.icon-manager-grid');
            
            // If searching, show all non-empty grids to display results from all sets
            if (searchQuery) {
                $grids.forEach(function($grid) {
                    var iconSetHandle = $grid.dataset.iconSet;
                    var hasAnyIconsInSet = self.icons.some(function(icon) {
                        return icon.iconSetHandle === iconSetHandle;
                    });
                    
                    if (hasAnyIconsInSet) {
                        $grid.classList.remove('hidden');
                    }
                });
            }
            
            $grids.forEach(function($grid) {
                var $gridInner = $grid.querySelector('.icon-manager-grid-inner');
                if (!$gridInner) return;
                
                var iconSetHandle = $grid.dataset.iconSet;
                
                // Check if this icon set has any icons at all
                var hasAnyIconsInSet = self.icons.some(function(icon) {
                    return icon.iconSetHandle === iconSetHandle;
                });
                
                // If no icons in this set at all, hide it completely
                if (!hasAnyIconsInSet) {
                    $grid.style.display = 'none';
                    return;
                }
                
                // Clear existing icons
                $gridInner.innerHTML = '';
                
                var iconsToShow = self.icons.filter(function(icon) {
                    // Filter by icon set
                    if (icon.iconSetHandle !== iconSetHandle) return false;
                    
                    // Filter by search query
                    if (searchQuery) {
                        var query = searchQuery.toLowerCase();
                        var matchesName = icon.name.toLowerCase().includes(query);
                        var matchesLabel = icon.label && icon.label.toLowerCase().includes(query);
                        var matchesKeywords = icon.keywords && Array.isArray(icon.keywords) && icon.keywords.some(function(keyword) {
                            return keyword && keyword.toLowerCase && keyword.toLowerCase().includes(query);
                        });
                        
                        return matchesName || matchesLabel || matchesKeywords;
                    }
                    
                    return true;
                });
                
                // Virtual scrolling: Only render initial batch of icons
                // Store all icons for this grid
                $grid.dataset.iconsToShow = JSON.stringify(iconsToShow);

                // Reset virtual scroll counter for this grid
                self.virtualScrollRenderedIcons[iconSetHandle] = 0;

                // Render initial batch
                self.renderIconBatch($gridInner, iconsToShow, iconSetHandle, self.virtualScrollBatchSize);

                // Setup intersection observer for infinite scroll if there are more icons
                if (iconsToShow.length > self.virtualScrollBatchSize) {
                    self.setupVirtualScrollObserver($gridInner, iconsToShow, iconSetHandle);
                }
                
                // Show/hide empty state
                if (iconsToShow.length === 0) {
                    if (searchQuery) {
                        // Hide the entire grid if no results when searching
                        $grid.style.display = 'none';
                    } else {
                        // Check if this is an empty icon set (no icons at all for this set)
                        var hasAnyIconsInSet = self.icons.some(function(icon) {
                            return icon.iconSetHandle === iconSetHandle;
                        });
                        
                        if (!hasAnyIconsInSet) {
                            // Don't show the tab at all if there are no icons
                            $grid.style.display = 'none';
                            // Also hide the tab
                            var $tab = self.$picker.querySelector('.tab[data-icon-set="' + iconSetHandle + '"]');
                            if ($tab && $tab.parentNode) {
                                $tab.parentNode.style.display = 'none';
                            }
                        } else {
                            $gridInner.innerHTML = '<div class="icon-manager-empty">' + Craft.t('icon-manager', 'No icons found') + '</div>';
                        }
                    }
                } else {
                    // Show the grid if it has results
                    $grid.style.display = '';
                }
            });
            
            // If searching and no results in any set, show a message
            if (searchQuery) {
                var hasAnyResults = false;
                var firstNonEmptyGrid = null;
                
                $grids.forEach(function($grid) {
                    var iconSetHandle = $grid.dataset.iconSet;
                    var hasIconsInSet = self.icons.some(function(icon) {
                        return icon.iconSetHandle === iconSetHandle;
                    });
                    
                    if (hasIconsInSet && $grid.style.display !== 'none') {
                        hasAnyResults = true;
                        if (!firstNonEmptyGrid) {
                            firstNonEmptyGrid = $grid;
                        }
                    }
                });
                
                if (!hasAnyResults && firstNonEmptyGrid) {
                    // Show no results message only in a grid that has icons
                    firstNonEmptyGrid.style.display = '';
                    var $gridInner = firstNonEmptyGrid.querySelector('.icon-manager-grid-inner');
                    $gridInner.innerHTML = '<div class="icon-manager-empty">' + Craft.t('icon-manager', 'No icons match your search') + '</div>';
                }
            }
        },
        
        filterIcons: function(searchQuery) {
            // Update searching state
            if (searchQuery) {
                this.$picker.classList.add('searching');
            } else {
                this.$picker.classList.remove('searching');
                // Restore tab view when clearing search
                var currentTab = this.$picker.querySelector('.tab.sel');
                var $tabSelect = this.$picker.querySelector('.icon-manager-tab-select');
                
                if (currentTab) {
                    this.switchTab(currentTab.dataset.iconSet);
                } else if ($tabSelect) {
                    // If using dropdown, switch to currently selected option
                    this.switchTab($tabSelect.value);
                }
            }
            
            this.loadIcons(searchQuery);
            this.updateIconCounts(searchQuery);
        },
        
        switchTab: function(iconSetHandle) {
            // Update tab states (if tabs are present)
            var $tabs = this.$picker.querySelectorAll('.tab');
            $tabs.forEach(function($tab) {
                if ($tab.dataset.iconSet === iconSetHandle) {
                    $tab.classList.add('sel');
                } else {
                    $tab.classList.remove('sel');
                }
            });
            
            // Update dropdown selection (if dropdown is present)
            var $tabSelect = this.$picker.querySelector('.icon-manager-tab-select');
            if ($tabSelect && $tabSelect.value !== iconSetHandle) {
                $tabSelect.value = iconSetHandle;
            }
            
            // Show/hide grids
            var $grids = this.$picker.querySelectorAll('.icon-manager-grid');
            $grids.forEach(function($grid) {
                if ($grid.dataset.iconSet === iconSetHandle) {
                    $grid.classList.remove('hidden');
                } else {
                    $grid.classList.add('hidden');
                }
            });
        },
        
        selectIcon: function(icon) {
            if (this.allowMultiple) {
                // Multi-selection logic
                var iconKey = icon.iconSetHandle + ':' + icon.name;
                var existingIndex = this.selectedIcons.findIndex(function(selectedIcon) {
                    return selectedIcon.iconSetHandle === icon.iconSetHandle && 
                           selectedIcon.name === icon.name;
                });
                
                if (existingIndex > -1) {
                    // Icon is already selected, remove it (toggle off)
                    this.selectedIcons.splice(existingIndex, 1);
                } else {
                    // Add icon to selection
                    this.selectedIcons.push({
                        iconSetHandle: icon.iconSetHandle,
                        name: icon.name,
                        type: icon.type,
                        value: icon.value
                    });
                }
                
                // Update hidden input with array of selected icons
                this.$input.value = JSON.stringify(this.selectedIcons);
                this.currentValue = this.selectedIcons;
                
                // Update the display
                this.updateMultipleDisplay();
                
                // Update selection states in the picker without reloading
                this.updateIconSelectionStates();
            } else {
                // Single selection logic (existing)
                this.currentValue = {
                    iconSetHandle: icon.iconSetHandle,
                    name: icon.name,
                    type: icon.type,
                    value: icon.value
                };
                
                // Update hidden input
                this.$input.value = JSON.stringify(this.currentValue);
                
                // Update display
                var $selected = this.$field.querySelector('.icon-manager-selected');
                var $placeholder = $selected.querySelector('.icon-manager-placeholder');
                var $selectedIcon = $selected.querySelector('.icon-manager-selected-icon');
                
                if ($placeholder) {
                    // Determine icon size based on field settings
                    var iconSize = 48; // default medium (SVGs)
                    var fontIconSize = 48; // default medium (fonts)
                    if (this.iconSize === 'small') {
                        iconSize = 32;
                        fontIconSize = 32;
                    } else if (this.iconSize === 'large') {
                        iconSize = 64;
                        fontIconSize = 54; // Font icons 10px smaller
                    }

                    // Replace placeholder with selected icon display
                    var html = '<div class="icon-manager-selected-icon">';
                    if (icon.content) {
                        // Use the actual SVG content or Font Awesome icon
                        var tempDiv = document.createElement('div');
                        tempDiv.innerHTML = icon.content;
                        var svg = tempDiv.querySelector('svg');
                        var fontIcon = tempDiv.querySelector('i');
                        var webFontIcon = tempDiv.querySelector('span.icon, span[class*="material-"]');

                        if (svg) {
                            svg.setAttribute('width', iconSize);
                            svg.setAttribute('height', iconSize);
                            html += tempDiv.innerHTML;
                        } else if (fontIcon && icon.isFontAwesome) {
                            // For Font Awesome icons, set font size
                            fontIcon.style.fontSize = fontIconSize + 'px';
                            html += tempDiv.innerHTML;
                        } else if (webFontIcon) {
                            // For WebFont and Material Icons, set font size
                            webFontIcon.style.fontSize = fontIconSize + 'px';
                            html += tempDiv.innerHTML;
                        } else {
                            html += icon.content;
                        }
                    } else {
                        // Fallback placeholder
                        html += '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="12" y="16" text-anchor="middle">' + icon.name.charAt(0).toUpperCase() + '</text></svg>';
                    }
                    if (this.showLabels) {
                        html += '<div class="icon-manager-selected-label">' + (icon.label || icon.name) + '</div>';
                    }
                    html += '</div>';
                    $placeholder.outerHTML = html;
                    
                } else if ($selectedIcon) {
                    // Use displayIcon to properly update with correct size and label
                    this.displayIcon(icon, $selected, $selectedIcon, null);
                }
                
                // Show clear button
                if (!this.$clearBtn) {
                    var $actions = $selected.querySelector('.icon-manager-actions');
                    var $clearBtn = document.createElement('button');
                    $clearBtn.type = 'button';
                    $clearBtn.className = 'btn icon-manager-clear-btn';
                    $clearBtn.textContent = Craft.t('icon-manager', 'Clear');
                    $actions.appendChild($clearBtn);
                    
                    var self = this;
                    $clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        self.clearSelection();
                    });
                    
                    this.$clearBtn = $clearBtn;
                }
                
                // Update button text
                this.$selectBtn.textContent = Craft.t('icon-manager', 'Edit Selection');
                
                // Update selection states in picker for single selection
                this.updateIconSelectionStates();
            }
            
            // Don't hide picker - let users browse and compare icons
        },
        
        clearSelection: function() {
            if (this.allowMultiple) {
                // Clear multiple selection
                this.selectedIcons = [];
                this.currentValue = [];
                this.$input.value = JSON.stringify([]);
                this.updateMultipleDisplay();
            } else {
                // Clear single selection
                this.currentValue = null;
                this.$input.value = '';
                
                // Update display
                var $selected = this.$field.querySelector('.icon-manager-selected');
                var $selectedIcon = $selected.querySelector('.icon-manager-selected-icon');

                if ($selectedIcon) {
                    // Determine icon size based on field settings
                    var iconSize = 48; // default medium
                    if (this.iconSize === 'small') {
                        iconSize = 32;
                    } else if (this.iconSize === 'large') {
                        iconSize = 64;
                    }

                    // Replace with placeholder
                    var html = '<div class="icon-manager-placeholder">';
                    html += '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>';
                    html += '<line x1="9" y1="9" x2="15" y2="15"></line>';
                    html += '<line x1="15" y1="9" x2="9" y2="15"></line>';
                    html += '</svg>';
                    html += '<div class="icon-manager-placeholder-text">' + Craft.t('icon-manager', 'No icon selected') + '</div>';
                    html += '</div>';
                    $selectedIcon.outerHTML = html;
                }

                // Update button text
                this.$selectBtn.textContent = Craft.t('icon-manager', 'Select Icons');
            }
            
            // Remove clear button
            if (this.$clearBtn) {
                this.$clearBtn.remove();
                this.$clearBtn = null;
            }
            
            // Update selection states without reloading
            if (this.$picker && !this.$picker.classList.contains('hidden')) {
                this.updateIconSelectionStates();
            }
        },
        
        updateMultipleDisplay: function() {
            var self = this;
            var $selected = this.$field.querySelector('.icon-manager-selected');
            var iconSize = 48; // default medium (SVGs)
            var fontIconSize = 48; // default medium (fonts)
            if (this.iconSize === 'small') {
                iconSize = 32;
                fontIconSize = 32;
            } else if (this.iconSize === 'large') {
                iconSize = 64;
                fontIconSize = 54; // Font icons 10px smaller
            }
            
            // Clear current display
            var $existingGrid = $selected.querySelector('.icon-manager-selected-icons-grid');
            var $existingPlaceholder = $selected.querySelector('.icon-manager-placeholder');
            
            if ($existingGrid) {
                $existingGrid.remove();
            }
            if ($existingPlaceholder) {
                $existingPlaceholder.remove();
            }
            
            if (this.selectedIcons.length === 0) {
                // Show placeholder
                var placeholderHtml = '<div class="icon-manager-placeholder">';
                placeholderHtml += '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                placeholderHtml += '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>';
                placeholderHtml += '<line x1="9" y1="9" x2="15" y2="15"></line>';
                placeholderHtml += '<line x1="15" y1="9" x2="9" y2="15"></line>';
                placeholderHtml += '</svg>';
                placeholderHtml += '<div class="icon-manager-placeholder-text">' + Craft.t('icon-manager', 'No icons selected') + '</div>';
                placeholderHtml += '</div>';
                
                // Insert before actions
                var $actions = $selected.querySelector('.icon-manager-actions');
                $actions.insertAdjacentHTML('beforebegin', placeholderHtml);
                
                // Update button text
                this.$selectBtn.textContent = Craft.t('icon-manager', 'Select Icons');
            } else {
                // Show selected icons grid
                var gridHtml = '<div class="icon-manager-selected-icons-grid">';
                
                this.selectedIcons.forEach(function(icon) {
                    gridHtml += '<div class="icon-manager-selected-icon">';
                    
                    // Load the icon (fetch if needed)
                    var iconData = self.icons.find(function(i) {
                        return i.iconSetHandle === icon.iconSetHandle && i.name === icon.name;
                    });
                    
                    if (iconData && iconData.content) {
                        var tempDiv = document.createElement('div');
                        tempDiv.innerHTML = iconData.content;
                        var svg = tempDiv.querySelector('svg');
                        var fontIcon = tempDiv.querySelector('i');
                        var webFontIcon = tempDiv.querySelector('span.icon, span[class*="material-"]');

                        if (svg) {
                            svg.setAttribute('width', iconSize);
                            svg.setAttribute('height', iconSize);
                        } else if (fontIcon) {
                            fontIcon.style.fontSize = fontIconSize + 'px';
                        } else if (webFontIcon) {
                            webFontIcon.style.fontSize = fontIconSize + 'px';
                        }
                        gridHtml += tempDiv.innerHTML;
                    } else {
                        // Fallback
                        gridHtml += '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="12" y="16" text-anchor="middle">' + icon.name.charAt(0).toUpperCase() + '</text></svg>';
                    }
                    
                    if (self.showLabels) {
                        gridHtml += '<div class="icon-manager-selected-label">' + (iconData ? (iconData.label || iconData.name) : icon.name) + '</div>';
                    }
                    
                    gridHtml += '</div>';
                });
                
                gridHtml += '</div>';
                
                // Insert before actions
                var $actions = $selected.querySelector('.icon-manager-actions');
                $actions.insertAdjacentHTML('beforebegin', gridHtml);
                
                // Update button text
                this.$selectBtn.textContent = Craft.t('icon-manager', 'Edit Selection');
                
                // Show clear button if it doesn't exist
                if (!this.$clearBtn) {
                    var $clearBtn = document.createElement('button');
                    $clearBtn.type = 'button';
                    $clearBtn.className = 'btn icon-manager-clear-btn';
                    $clearBtn.textContent = Craft.t('icon-manager', 'Clear');
                    $actions.appendChild($clearBtn);
                    
                    $clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        self.clearSelection();
                    });
                    
                    this.$clearBtn = $clearBtn;
                }
            }
            
            // Rebind custom label inputs after display update
            this.bindCustomLabelInputs();
        },
        
        updateIconSelectionStates: function() {
            var self = this;
            // Update all visible icon grid items to reflect current selection
            var $gridItems = this.$picker.querySelectorAll('.icon-manager-grid-item');
            
            $gridItems.forEach(function($item) {
                var iconData = JSON.parse($item.dataset.iconData);
                var isSelected = false;
                
                if (self.allowMultiple) {
                    // Check if this icon is in the selected icons array
                    isSelected = self.selectedIcons.some(function(selectedIcon) {
                        return selectedIcon.iconSetHandle === iconData.iconSetHandle && 
                               selectedIcon.name === iconData.name;
                    });
                } else {
                    // Single selection check
                    isSelected = self.currentValue && 
                               self.currentValue.iconSetHandle === iconData.iconSetHandle && 
                               self.currentValue.name === iconData.name;
                }
                
                // Update the visual selection state
                if (isSelected) {
                    $item.classList.add('selected');
                } else {
                    $item.classList.remove('selected');
                }
            });
        },
        
        getCurrentValue: function() {
            if (this.$input.value) {
                try {
                    return JSON.parse(this.$input.value);
                } catch (e) {
                    return null;
                }
            }
            return null;
        },
        
        updateIconCounts: function(searchQuery) {
            var self = this;
            var counts = {};
            
            // Count icons per set
            this.icons.forEach(function(icon) {
                if (!counts[icon.iconSetHandle]) {
                    counts[icon.iconSetHandle] = 0;
                }
                
                // Apply search filter
                if (searchQuery) {
                    var query = searchQuery.toLowerCase();
                    var matchesName = icon.name.toLowerCase().includes(query);
                    var matchesLabel = icon.label && icon.label.toLowerCase().includes(query);
                    var matchesKeywords = icon.keywords && Array.isArray(icon.keywords) && icon.keywords.some(function(keyword) {
                        return keyword && keyword.toLowerCase && keyword.toLowerCase().includes(query);
                    });
                    
                    if (matchesName || matchesLabel || matchesKeywords) {
                        counts[icon.iconSetHandle]++;
                    }
                } else {
                    counts[icon.iconSetHandle]++;
                }
            });
            
            // Update count displays for tabs with number formatting
            var $counts = this.$picker.querySelectorAll('.icon-count');
            $counts.forEach(function($count) {
                var iconSet = $count.dataset.iconSet;
                var count = counts[iconSet] || 0;
                $count.textContent = '(' + count.toLocaleString() + ')';
            });

            // Update dropdown count if present with number formatting
            var $dropdownCount = this.$picker.querySelector('.icon-count-dropdown');
            if ($dropdownCount) {
                var currentSet = $dropdownCount.dataset.iconSet;
                var count = counts[currentSet] || 0;
                $dropdownCount.textContent = '(' + count.toLocaleString() + ' icons)';
            }
            
            // If searching and current tab has no results, switch to first tab with results
            if (searchQuery) {
                var currentTab = this.$picker.querySelector('.tab.sel');
                var currentSet = currentTab ? currentTab.dataset.iconSet : null;
                
                if (currentSet && counts[currentSet] === 0) {
                    // Find first tab with results
                    for (var setHandle in counts) {
                        if (counts[setHandle] > 0) {
                            this.switchTab(setHandle);
                            break;
                        }
                    }
                }
            }
        },
        
        updateDropdownCount: function(iconSetHandle) {
            var $dropdownCount = this.$picker.querySelector('.icon-count-dropdown');
            if ($dropdownCount) {
                $dropdownCount.dataset.iconSet = iconSetHandle;
                // Count icons for this set
                var count = 0;
                this.icons.forEach(function(icon) {
                    if (icon.iconSetHandle === iconSetHandle) {
                        count++;
                    }
                });
                $dropdownCount.textContent = '(' + count + ' icons)';
            }
        },
        
        hideEmptyIconSets: function() {
            var self = this;
            var iconSetCounts = {};
            
            // Count icons per set
            this.icons.forEach(function(icon) {
                if (!iconSetCounts[icon.iconSetHandle]) {
                    iconSetCounts[icon.iconSetHandle] = 0;
                }
                iconSetCounts[icon.iconSetHandle]++;
            });
            
            // Hide tabs for empty sets
            var $tabs = this.$picker.querySelectorAll('.tab');
            $tabs.forEach(function($tab) {
                var iconSetHandle = $tab.dataset.iconSet;
                if (!iconSetCounts[iconSetHandle] || iconSetCounts[iconSetHandle] === 0) {
                    $tab.parentNode.style.display = 'none';
                } else {
                    $tab.parentNode.style.display = '';
                }
            });
            
            // Also update dropdown options if present
            var $tabSelect = this.$picker.querySelector('.icon-manager-tab-select');
            if ($tabSelect) {
                var options = $tabSelect.querySelectorAll('option');
                options.forEach(function(option) {
                    var iconSetHandle = option.value;
                    if (!iconSetCounts[iconSetHandle] || iconSetCounts[iconSetHandle] === 0) {
                        option.style.display = 'none';
                        option.disabled = true;
                    } else {
                        option.style.display = '';
                        option.disabled = false;
                    }
                });
            }
        },
        
        findFirstNonEmptyIconSet: function() {
            var self = this;
            var iconSetCounts = {};
            
            // Count icons per set
            this.icons.forEach(function(icon) {
                if (!iconSetCounts[icon.iconSetHandle]) {
                    iconSetCounts[icon.iconSetHandle] = 0;
                }
                iconSetCounts[icon.iconSetHandle]++;
            });
            
            // Find first non-empty set
            for (var handle in iconSetCounts) {
                if (iconSetCounts[handle] > 0) {
                    return handle;
                }
            }
            
            return null;
        },
        
        cancelSelection: function() {
            if (this.allowMultiple) {
                // Restore saved selected icons
                this.selectedIcons = this.savedSelectedIcons ? this.savedSelectedIcons.slice() : [];
                this.currentValue = this.selectedIcons;
                this.$input.value = JSON.stringify(this.selectedIcons);
                this.updateMultipleDisplay();
            } else {
                // Restore the saved value
                if (this.savedValue) {
                    this.currentValue = this.savedValue;
                    this.$input.value = JSON.stringify(this.savedValue);
                } else {
                    this.currentValue = null;
                    this.$input.value = '';
                }
                
                // Update the display to match saved value
                this.updateDisplay();
            }
            
            // Hide the picker
            this.hidePicker();
        },
        
        updateDisplay: function() {
            var self = this;
            var $selected = this.$field.querySelector('.icon-manager-selected');
            var $selectedIcon = $selected.querySelector('.icon-manager-selected-icon');
            var $placeholder = $selected.querySelector('.icon-manager-placeholder');
            var $actions = $selected.querySelector('.icon-manager-actions');
            
            if (this.currentValue) {
                // Need to fetch the icon data to display it
                var icon = this.icons.find(function(i) {
                    return i.iconSetHandle === self.currentValue.iconSetHandle && i.name === self.currentValue.name;
                });
                
                if (!icon) {
                    // Icon not in our list, need to fetch it
                    fetch(Craft.getCpUrl('icon-manager/icons/get-data'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': Craft.csrfTokenValue,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            iconSet: this.currentValue.iconSetHandle,
                            icon: this.currentValue.name
                        })
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success && data.icon) {
                            self.displayIcon(data.icon, $selected, $selectedIcon, $placeholder);
                        }
                    })
                    .catch(function(error) {
                        console.error('Failed to load icon:', error);
                    });
                } else {
                    // We have the icon data
                    this.displayIcon(icon, $selected, $selectedIcon, $placeholder);
                }
                
                // Update clear button
                var existingClearBtn = $actions.querySelector('.icon-manager-clear-btn');
                if (!existingClearBtn) {
                    var $clearBtn = document.createElement('button');
                    $clearBtn.type = 'button';
                    $clearBtn.className = 'btn icon-manager-clear-btn';
                    $clearBtn.textContent = Craft.t('icon-manager', 'Clear');
                    $actions.appendChild($clearBtn);
                    
                    $clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        self.clearSelection();
                    });
                    
                    this.$clearBtn = $clearBtn;
                } else {
                    this.$clearBtn = existingClearBtn;
                }
                
                // Update button text
                this.$selectBtn.textContent = Craft.t('icon-manager', 'Edit Selection');
            } else {
                // No value - show placeholder
                if ($selectedIcon) {
                    // Determine icon size based on field settings
                    var iconSize = 48; // default medium
                    if (this.iconSize === 'small') {
                        iconSize = 32;
                    } else if (this.iconSize === 'large') {
                        iconSize = 64;
                    }

                    var html = '<div class="icon-manager-placeholder">';
                    html += '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                    html += '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>';
                    html += '<line x1="9" y1="9" x2="15" y2="15"></line>';
                    html += '<line x1="15" y1="9" x2="9" y2="15"></line>';
                    html += '</svg>';
                    html += '<div class="icon-manager-placeholder-text">' + Craft.t('icon-manager', 'No icon selected') + '</div>';
                    html += '</div>';
                    $selectedIcon.outerHTML = html;
                }
                
                // Remove clear button
                if (this.$clearBtn) {
                    this.$clearBtn.remove();
                    this.$clearBtn = null;
                }
                
                // Update button text
                this.$selectBtn.textContent = Craft.t('icon-manager', 'Select Icons');
            }
        },
        
        bindCustomLabelInputs: function() {
            var self = this;
            
            // Handle single custom label input
            var $singleInput = this.$field.querySelector('.icon-manager-custom-label-input');
            if ($singleInput && !this.allowMultiple) {
                $singleInput.addEventListener('input', function(e) {
                    self.updateSingleCustomLabel(e.target.value);
                });
            }
            
            // Handle multiple custom label inputs
            var $multipleInputs = this.$field.querySelectorAll('[data-icon-index]');
            $multipleInputs.forEach(function($input) {
                var iconIndex = parseInt($input.dataset.iconIndex);
                
                // Initialize selectedIcons[iconIndex].customLabel with input value if not set
                if (self.selectedIcons && self.selectedIcons[iconIndex] && $input.value) {
                    if (!self.selectedIcons[iconIndex].hasOwnProperty('customLabel')) {
                        self.selectedIcons[iconIndex].customLabel = $input.value;
                    }
                }
                
                $input.addEventListener('input', function(e) {
                    self.updateMultipleCustomLabel(iconIndex, e.target.value);
                });
            });
        },
        
        updateSingleCustomLabel: function(customLabel) {
            // Update the current value with custom label
            if (this.currentValue) {
                this.currentValue.customLabel = customLabel;
                this.$input.value = JSON.stringify(this.currentValue);
            }
        },
        
        updateMultipleCustomLabel: function(iconIndex, customLabel) {
            // Update the specific icon's custom label
            if (this.allowMultiple && this.selectedIcons && this.selectedIcons[iconIndex]) {
                // Always update the custom label (preserve empty values too)
                this.selectedIcons[iconIndex].customLabel = customLabel;
                
                this.currentValue = this.selectedIcons;
                this.$input.value = JSON.stringify(this.selectedIcons);
            }
        },
        
        displayIcon: function(icon, $selected, $selectedIcon, $placeholder) {
            // Determine icon size based on field settings
            var iconSize = 48; // default medium (SVGs)
            var fontIconSize = 48; // default medium (fonts)
            if (this.iconSize === 'small') {
                iconSize = 32;
                fontIconSize = 32;
            } else if (this.iconSize === 'large') {
                iconSize = 64;
                fontIconSize = 54; // Font icons 10px smaller
            }

            var html = '<div class="icon-manager-selected-icon">';
            if (icon.content) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = icon.content;
                var svg = tempDiv.querySelector('svg');
                var fontIcon = tempDiv.querySelector('i');
                var webFontIcon = tempDiv.querySelector('span.icon, span[class*="material-"]');

                if (svg) {
                    svg.setAttribute('width', iconSize);
                    svg.setAttribute('height', iconSize);
                } else if (fontIcon) {
                    fontIcon.style.fontSize = fontIconSize + 'px';
                } else if (webFontIcon) {
                    webFontIcon.style.fontSize = fontIconSize + 'px';
                }
                html += tempDiv.innerHTML;
            } else {
                html += '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="12" y="16" text-anchor="middle">' + icon.name.charAt(0).toUpperCase() + '</text></svg>';
            }
            
            if (this.showLabels) {
                html += '<div class="icon-manager-selected-label">' + (icon.label || icon.name) + '</div>';
            }
            html += '</div>';
            
            if ($placeholder) {
                $placeholder.outerHTML = html;
            } else if ($selectedIcon) {
                $selectedIcon.outerHTML = html;
            }
        },

        /**
         * Render a batch of icons (for virtual scrolling)
         */
        renderIconBatch: function($gridInner, iconsToShow, iconSetHandle, count) {
            var self = this;
            var startIndex = this.virtualScrollRenderedIcons[iconSetHandle] || 0;
            var endIndex = Math.min(startIndex + count, iconsToShow.length);

            for (var i = startIndex; i < endIndex; i++) {
                var icon = iconsToShow[i];
                var $item = this.createIconElement(icon);
                $gridInner.appendChild($item);
            }

            // Update counter
            this.virtualScrollRenderedIcons[iconSetHandle] = endIndex;

            return endIndex < iconsToShow.length; // Returns true if there are more icons to render
        },

        /**
         * Create a single icon DOM element
         */
        createIconElement: function(icon) {
            var self = this;
            var $item = document.createElement('div');
            $item.className = 'icon-manager-grid-item';

            // Check if this is the currently selected icon
            var isSelected = false;
            if (this.allowMultiple) {
                // Check if this icon is in the selected icons array
                isSelected = this.selectedIcons.some(function(selectedIcon) {
                    return selectedIcon.iconSetHandle === icon.iconSetHandle &&
                           selectedIcon.name === icon.name;
                });
            } else {
                // Single selection check
                isSelected = this.currentValue &&
                           this.currentValue.iconSetHandle === icon.iconSetHandle &&
                           this.currentValue.name === icon.name;
            }

            if (isSelected) {
                $item.className += ' selected';
            }

            $item.dataset.iconData = JSON.stringify(icon);

            var $iconDiv = document.createElement('div');
            $iconDiv.className = 'icon-manager-grid-item-icon';

            // Determine icon size based on field settings
            var iconSize = 48; // default medium (SVGs)
            var fontIconSize = 48; // default medium (fonts)
            if (this.iconSize === 'small') {
                iconSize = 32;
                fontIconSize = 32;
            } else if (this.iconSize === 'large') {
                iconSize = 64;
                fontIconSize = 54; // Font icons 10px smaller
            }

            // Display the icon (already loaded in batch)
            if (icon.content) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = icon.content;
                var svg = tempDiv.querySelector('svg');
                var fontIcon = tempDiv.querySelector('i');
                var webFontIcon = tempDiv.querySelector('span.icon, span[class*="material-"]');

                if (svg) {
                    svg.setAttribute('width', iconSize);
                    svg.setAttribute('height', iconSize);
                } else if (fontIcon) {
                    fontIcon.style.fontSize = fontIconSize + 'px';
                } else if (webFontIcon) {
                    webFontIcon.style.fontSize = fontIconSize + 'px';
                }
                $iconDiv.innerHTML = tempDiv.innerHTML;
            } else {
                // Fallback placeholder if content somehow missing
                $iconDiv.innerHTML = '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><text x="12" y="16" text-anchor="middle" font-size="10">' + icon.name.charAt(0).toUpperCase() + '</text></svg>';
            }

            $item.appendChild($iconDiv);

            if (this.showLabels) {
                var $label = document.createElement('div');
                $label.className = 'icon-manager-grid-item-label';
                $label.textContent = icon.label || icon.name;
                $item.appendChild($label);
            }

            // Click to select
            $item.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent event from bubbling
                self.selectIcon(icon);
            });

            return $item;
        },

        /**
         * Setup intersection observer for virtual scrolling
         */
        setupVirtualScrollObserver: function($gridInner, iconsToShow, iconSetHandle) {
            var self = this;

            // Disconnect existing observer if any
            if (this.virtualScrollObservers[iconSetHandle]) {
                this.virtualScrollObservers[iconSetHandle].disconnect();
            }

            // Create a loading indicator
            var $loadingIndicator = document.createElement('div');
            $loadingIndicator.className = 'virtual-scroll-loading';
            $loadingIndicator.style.padding = '2rem';
            $loadingIndicator.style.textAlign = 'center';
            $loadingIndicator.style.color = '#6b7280';
            $loadingIndicator.style.fontSize = '0.875rem';
            $loadingIndicator.style.fontWeight = '500';
            $loadingIndicator.innerHTML = 'Loading more icons...';
            $gridInner.appendChild($loadingIndicator);

            // Create a sentinel element at the bottom
            var $sentinel = document.createElement('div');
            $sentinel.className = 'virtual-scroll-sentinel';
            $sentinel.style.height = '1px';
            $sentinel.style.width = '100%';
            $gridInner.appendChild($sentinel);

            // Create intersection observer
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        // User scrolled to bottom, load more icons
                        // Insert new icons BEFORE the loading indicator
                        var hasMore = self.renderIconBatchBeforeElement($gridInner, iconsToShow, iconSetHandle, self.virtualScrollBatchSize, $loadingIndicator);

                        // If no more icons, disconnect observer and remove sentinel + loading
                        if (!hasMore) {
                            observer.disconnect();
                            $sentinel.remove();
                            $loadingIndicator.remove();
                        }
                    }
                });
            }, {
                root: null, // Use viewport instead of container
                rootMargin: '200px', // Start loading 200px before reaching the bottom
                threshold: 0.1
            });

            observer.observe($sentinel);
            this.virtualScrollObservers[iconSetHandle] = observer;
        },

        /**
         * Render a batch of icons before a specific element (for virtual scrolling with loading indicator)
         */
        renderIconBatchBeforeElement: function($gridInner, iconsToShow, iconSetHandle, count, $beforeElement) {
            var self = this;
            var startIndex = this.virtualScrollRenderedIcons[iconSetHandle] || 0;
            var endIndex = Math.min(startIndex + count, iconsToShow.length);

            for (var i = startIndex; i < endIndex; i++) {
                var icon = iconsToShow[i];
                var $item = this.createIconElement(icon);
                $gridInner.insertBefore($item, $beforeElement);
            }

            // Update counter
            this.virtualScrollRenderedIcons[iconSetHandle] = endIndex;

            return endIndex < iconsToShow.length; // Returns true if there are more icons to render
        },

        /**
         * Load fonts/CSS required for font-based icons (Material Icons, Font Awesome)
         */
        loadFonts: function(fonts) {
            fonts.forEach(function(font) {
                if (font.type === 'remote' && font.url) {
                    // Check if this font CSS is already loaded
                    var existingLink = document.querySelector('link[href="' + font.url + '"]');
                    if (!existingLink) {
                        var link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = font.url;
                        link.crossOrigin = 'anonymous';
                        document.head.appendChild(link);
                    }
                } else if (font.type === 'inline' && font.css) {
                    // Inject inline CSS
                    var existingStyle = document.querySelector('style[data-icon-manager-inline]');
                    if (!existingStyle) {
                        var style = document.createElement('style');
                        style.setAttribute('data-icon-manager-inline', 'true');
                        style.textContent = font.css;
                        document.head.appendChild(style);
                    } else {
                        // Append to existing inline styles
                        existingStyle.textContent += '\n' + font.css;
                    }
                }
            });
        },

        /**
         * Load sprite SVG files and inject them into the DOM
         */
        loadSprites: function(sprites) {
            var self = this;
            sprites.forEach(function(sprite) {
                // Check if this sprite is already loaded
                var existingSprite = document.getElementById('icon-manager-sprite-' + sprite.name);
                if (!existingSprite) {
                    // Fetch the sprite SVG file
                    fetch(sprite.url)
                        .then(function(response) { return response.text(); })
                        .then(function(svgContent) {
                            self.injectSprite(sprite.name, svgContent);
                        })
                        .catch(function(error) {
                            console.error('Failed to load sprite:', sprite.name, error);
                        });
                }
            });
        },

        /**
         * Inject sprite SVG into the DOM (hidden)
         */
        injectSprite: function(spriteName, svgContent) {
            // Strip out any <style> tags to prevent CSS pollution
            svgContent = svgContent.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');

            var div = document.createElement('div');
            div.id = 'icon-manager-sprite-' + spriteName;
            div.style.display = 'none';
            div.innerHTML = svgContent;
            document.body.insertBefore(div, document.body.firstChild);

            console.log('Injected sprite:', spriteName);
        },

        /**
         * Focus the search input when picker opens
         */
        focusSearchInput: function() {
            var $searchInput = this.$picker.querySelector('.icon-manager-search-input');
            if ($searchInput) {
                // Use setTimeout to ensure the picker is fully visible first
                setTimeout(function() {
                    $searchInput.focus();
                }, 100);
            }
        },

        /**
         * Load fonts for saved icons on page init
         */
        loadInitialFonts: function() {
            // Don't load anything on page init - fonts/sprites will load when picker opens
            // This prevents downloading large font files (like Material Icons 3.7MB) on every page load
            // Sprites are already injected via the template for selected icons
            return;
        }
    };
})();
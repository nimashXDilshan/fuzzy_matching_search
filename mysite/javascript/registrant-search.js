/**
 * Registrant Search Component
 * 
 * Handles fuzzy search for customers and organizations during domain registration.
 * Features: debounced search, registrant type switching, result display with masking.
 */
class RegistrantSearch {
    constructor(options = {}) {
        this.options = {
            containerSelector: '#registrant-search-container',
            debounceMs: 400,
            minSearchLength: 3,
            maxResults: 30,
            apiBase: '/api/registration',
            ...options
        };

        this.state = {
            domainReasonId: null,
            registrantType: 'individual',
            allowedTypes: ['individual', 'organization'],
            searchTerm: '',
            results: [],
            isLoading: false,
            selectedResult: null,
            countryId: null,
            isTypeLocked: false
        };

        this.debounceTimer = null;
        this.container = null;

        this.init();
    }

    /**
     * Initialize the search component
     */
    init() {
        this.container = document.querySelector(this.options.containerSelector);

        if (!this.container) {
            console.error('RegistrantSearch: Container not found');
            return;
        }

        this.render();
        this.bindEvents();
        this.loadDomainReasons();
        this.loadCountries();
    }

    /**
     * Render the search component HTML
     */
    render() {
        this.container.innerHTML = `
            <div class="registrant-search">
                <div class="search-header">
                    <h3>Registrant Search</h3>
                </div>

                <div class="form-group">
                    <label for="domain-reason">Domain Reason</label>
                    <select id="domain-reason" class="form-control">
                        <option value="">Select domain reason...</option>
                    </select>
                </div>

                <div class="registrant-type-selector" id="type-selector">
                    <div class="type-option">
                        <input type="radio" name="registrant-type" id="type-individual" value="individual" checked>
                        <label for="type-individual">Individual</label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="registrant-type" id="type-organization" value="organization">
                        <label for="type-organization">Organization</label>
                    </div>
                    <span class="locked-badge hidden" id="locked-badge">Locked</span>
                </div>

                <div class="search-inputs-container">
                    <!-- Individual Search Inputs -->
                    <div id="individual-inputs" class="input-group-set">
                        <div class="form-group">
                            <label for="search-ind-name">Name</label>
                            <input type="text" id="search-ind-name" class="form-control search-field" data-field="name" placeholder="Full Name">
                        </div>
                        <div class="form-group">
                            <label for="search-ind-nic">NIC / Passport</label>
                            <input type="text" id="search-ind-nic" class="form-control search-field" data-field="nic" placeholder="NIC or Passport Number">
                        </div>
                        <div class="form-group">
                            <label for="search-ind-email">Primary Email</label>
                            <input type="email" id="search-ind-email" class="form-control search-field" data-field="email" placeholder="example@email.com">
                        </div>
                        <div class="form-group">
                            <label for="search-ind-phone">Primary Phone Number</label>
                            <input type="tel" id="search-ind-phone" class="form-control search-field" data-field="phone" placeholder="+94...">
                        </div>
                    </div>

                    <!-- Organization Search Inputs -->
                    <div id="organization-inputs" class="input-group-set hidden">
                        <div class="form-group">
                            <label for="search-org-name">Organization Name</label>
                            <input type="text" id="search-org-name" class="form-control search-field" data-field="name" placeholder="Company Name">
                        </div>
                        <div class="form-group">
                            <label for="search-org-br">Business Registration (BR) Number</label>
                            <input type="text" id="search-org-br" class="form-control search-field" data-field="brNumber" placeholder="BR Number">
                        </div>
                    </div>

                    <div class="search-actions">
                        <button type="button" id="search-btn" class="btn btn-primary btn-block">Search</button>
                    </div>
                </div>

                <div class="search-results" id="search-results">
                    <div class="results-header hidden" id="results-header">
                        <span id="results-count">0 results found</span>
                    </div>
                    <div class="results-list" id="results-list"></div>
                    <div class="loading-spinner hidden" id="loading-spinner">
                        <div class="spinner"></div>
                        <span>Searching...</span>
                    </div>
                    <div class="no-results hidden" id="no-results">
                        <p>No matching records found</p>
                    </div>
                    <div class="error-message hidden" id="error-message">
                        <p>Search failed. Please try again.</p>
                    </div>
                </div>

                <div class="create-new-option hidden" id="create-new-option">
                    <label>
                        <input type="checkbox" id="create-new-checkbox">
                        <span>Create new <span id="create-new-type">registrant</span> instead</span>
                    </label>
                </div>

                <div class="country-selector hidden" id="country-selector">
                    <label for="country-select">
                        Country <span class="required">*</span>
                    </label>
                    <select id="country-select" class="form-control" required>
                        <option value="">Select country...</option>
                    </select>
                    <span class="warning-text">Required for organization selection</span>
                </div>

                <div class="action-buttons">
                    <button type="button" id="cancel-btn" class="btn btn-secondary">Cancel</button>
                    <button type="button" id="continue-btn" class="btn btn-primary" disabled>
                        Continue →
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Domain reason change
        document.getElementById('domain-reason').addEventListener('change', (e) => {
            this.onDomainReasonChange(parseInt(e.target.value, 10));
        });

        // Registrant type change
        document.querySelectorAll('input[name="registrant-type"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.onTypeChange(e.target.value);
            });
        });

        // Search inputs with debounce
        const searchInputs = this.container.querySelectorAll('.search-field');
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                this.onSearchInput(e.target);
            });

            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.executeSearch();
                }
            });
        });

        // Search button click
        document.getElementById('search-btn').addEventListener('click', () => {
            this.executeSearch();
        });

        // Country selection
        document.getElementById('country-select').addEventListener('change', (e) => {
            this.state.countryId = parseInt(e.target.value, 10) || null;
            this.updateContinueButton();
        });

        // Create new checkbox
        document.getElementById('create-new-checkbox').addEventListener('change', (e) => {
            if (e.target.checked) {
                this.state.selectedResult = null;
                this.clearResultSelection();

                // Unlock country dropdown for new organization creation
                const countrySelect = document.getElementById('country-select');
                if (countrySelect) {
                    countrySelect.disabled = false;
                    countrySelect.classList.remove('locked');
                    countrySelect.style.backgroundColor = '';
                    countrySelect.style.cursor = '';
                    // Reset country selection for new org
                    countrySelect.value = '';
                    this.state.countryId = null;
                }
            }
            this.updateContinueButton();
        });

        // Continue button
        document.getElementById('continue-btn').addEventListener('click', () => {
            this.onContinue();
        });

        // Cancel button
        document.getElementById('cancel-btn').addEventListener('click', () => {
            this.onCancel();
        });
    }

    /**
     * Load domain reasons from API
     */
    async loadDomainReasons() {
        try {
            const response = await fetch(`${this.options.apiBase}/domain-reasons`);
            const data = await response.json();

            if (data.success && data.reasons) {
                const select = document.getElementById('domain-reason');
                data.reasons.forEach(reason => {
                    const option = document.createElement('option');
                    option.value = reason.id;
                    option.textContent = reason.name;
                    option.dataset.allowedTypes = JSON.stringify(reason.allowedTypes);
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load domain reasons:', error);
        }
    }

    /**
     * Load countries from API
     */
    async loadCountries() {
        try {
            const response = await fetch(`${this.options.apiBase}/countries`);
            const data = await response.json();

            if (data.success && data.countries) {
                const select = document.getElementById('country-select');
                data.countries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load countries:', error);
        }
    }

    /**
     * Handle domain reason change
     */
    async onDomainReasonChange(reasonId) {
        this.state.domainReasonId = reasonId;

        if (!reasonId) {
            this.state.allowedTypes = ['individual', 'organization'];
            this.state.isTypeLocked = false;
            this.updateTypeSelector();
            return;
        }

        try {
            const response = await fetch(`${this.options.apiBase}/registrant-types/${reasonId}`);
            const data = await response.json();

            if (data.success && data.config) {
                this.state.allowedTypes = data.config.allowedTypes;
                this.state.isTypeLocked = data.config.isLocked;
                this.state.registrantType = data.config.defaultType;
                this.updateTypeSelector();
            }
        } catch (error) {
            console.error('Failed to load registrant types:', error);
        }
    }

    /**
     * Update the type selector based on allowed types
     */
    updateTypeSelector() {
        const individualRadio = document.getElementById('type-individual');
        const organizationRadio = document.getElementById('type-organization');
        const lockedBadge = document.getElementById('locked-badge');

        individualRadio.disabled = !this.state.allowedTypes.includes('individual');
        organizationRadio.disabled = !this.state.allowedTypes.includes('organization');

        // Set the correct radio
        if (this.state.registrantType === 'individual') {
            individualRadio.checked = true;
        } else {
            organizationRadio.checked = true;
        }

        // Show/hide locked badge
        if (this.state.isTypeLocked) {
            lockedBadge.classList.remove('hidden');
        } else {
            lockedBadge.classList.add('hidden');
        }

        this.updateSearchUI();
    }

    /**
     * Handle registrant type change
     */
    onTypeChange(type) {
        this.state.registrantType = type;
        this.state.results = [];
        this.state.selectedResult = null;
        this.state.searchTerm = '';

        // Clear all search fields
        this.container.querySelectorAll('.search-field').forEach(input => {
            input.value = '';
        });

        this.updateSearchUI();
        this.clearResults();
    }

    /**
     * Update search UI based on current type
     */
    updateSearchUI() {
        const individualInputs = document.getElementById('individual-inputs');
        const organizationInputs = document.getElementById('organization-inputs');
        const countrySelector = document.getElementById('country-selector');
        const createNewType = document.getElementById('create-new-type');

        if (this.state.registrantType === 'individual') {
            individualInputs.classList.remove('hidden');
            organizationInputs.classList.add('hidden');
            countrySelector.classList.add('hidden');
            createNewType.textContent = 'individual';
        } else {
            individualInputs.classList.add('hidden');
            organizationInputs.classList.remove('hidden');
            countrySelector.classList.remove('hidden');
            createNewType.textContent = 'organization';
        }
    }

    /**
     * Handle search input with debounce
     */
    onSearchInput(input) {
        // Clear other inputs in the same group to enforce single-field search (optional UX choice)
        // For now, we'll allow multiple, but typically only one is used at a time in this context.
        // Actually, let's just update the state.

        // Find which field this is
        const fieldName = input.dataset.field;
        const val = input.value.trim();

        // Determine active inputs based on type
        const activeContainer = this.state.registrantType === 'individual'
            ? document.getElementById('individual-inputs')
            : document.getElementById('organization-inputs');

        // Check if we have enough length in ANY active input
        let hasValidTerm = false;
        activeContainer.querySelectorAll('.search-field').forEach(field => {
            if (field.value.trim().length >= this.options.minSearchLength) {
                hasValidTerm = true;
            }
        });

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        if (!hasValidTerm) {
            // Don't clear results immediately on backspace, but maybe we should?
            // If all are empty, clear.
            let allEmpty = true;
            activeContainer.querySelectorAll('.search-field').forEach(field => {
                if (field.value.trim().length > 0) allEmpty = false;
            });

            if (allEmpty) {
                this.clearResults();
            }
            return;
        }

        this.debounceTimer = setTimeout(() => {
            this.executeSearch();
        }, this.options.debounceMs);
    }

    /**
     * Execute the search - searches ALL filled fields for better matching
     */
    async executeSearch() {
        const activeContainer = this.state.registrantType === 'individual'
            ? document.getElementById('individual-inputs')
            : document.getElementById('organization-inputs');

        // Collect ALL filled fields into a searchTerms object
        const searchTerms = {};
        const searchFields = [];

        const inputs = Array.from(activeContainer.querySelectorAll('.search-field'));

        inputs.forEach(input => {
            const val = input.value.trim();
            const field = input.dataset.field;

            if (val.length >= 2) {  // Minimum 2 chars per field
                searchTerms[field] = val;
                searchFields.push(field);
            }
        });

        // Need at least one field with enough characters
        if (searchFields.length === 0) {
            return;
        }

        // For the API, we'll send the primary search term (first populated field)
        // and all the search terms as criteria
        const primaryField = searchFields[0];
        const primaryTerm = searchTerms[primaryField];

        this.setLoading(true);

        try {
            const endpoint = this.state.registrantType === 'individual'
                ? '/search-customers'
                : '/search-organizations';

            const response = await fetch(`${this.options.apiBase}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    searchTerm: primaryTerm,
                    searchFields: searchFields,
                    searchTerms: searchTerms,  // NEW: All search terms
                    limit: this.options.maxResults,
                    offset: 0,
                }),
            });

            const data = await response.json();

            if (data.success) {
                this.state.results = data.results || [];
                this.renderResults();
            } else {
                this.showError(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Search failed. Please try again.');
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Render search results
     */
    renderResults() {
        const resultsList = document.getElementById('results-list');
        const resultsHeader = document.getElementById('results-header');
        const resultsCount = document.getElementById('results-count');
        const noResults = document.getElementById('no-results');
        const createNewOption = document.getElementById('create-new-option');

        resultsList.innerHTML = '';

        if (this.state.results.length === 0) {
            noResults.classList.remove('hidden');
            resultsHeader.classList.add('hidden');
            createNewOption.classList.remove('hidden');
            return;
        }

        noResults.classList.add('hidden');
        resultsHeader.classList.remove('hidden');
        createNewOption.classList.remove('hidden');
        resultsCount.textContent = `${this.state.results.length} result${this.state.results.length !== 1 ? 's' : ''} found`;

        this.state.results.forEach((result, index) => {
            const item = document.createElement('div');
            item.className = 'result-item';
            item.dataset.index = index;
            item.dataset.id = result.id;

            if (this.state.registrantType === 'individual') {
                item.innerHTML = `
                    <div class="result-radio">
                        <input type="radio" name="selected-result" id="result-${index}" value="${result.id}">
                    </div>
                    <div class="result-content">
                        <div class="result-name">${this.escapeHtml(result.displayName)}</div>
                        <div class="result-details">
                            <span class="detail-item">Email: ${this.escapeHtml(result.maskedEmail)}</span>
                            ${(result.maskedNIC || result.partialNIC) ? `
                                <span class="detail-separator">|</span>
                                <span class="detail-item">NIC: ${this.escapeHtml(result.maskedNIC || result.partialNIC)}</span>
                            ` : ''}
                            <span class="detail-separator">|</span>
                            <span class="detail-item">Tel: ${this.escapeHtml(result.maskedPhone || 'N/A')}</span>
                        </div>
                    </div>
                `;
            } else {
                item.innerHTML = `
                    <div class="result-radio">
                        <input type="radio" name="selected-result" id="result-${index}" value="${result.id}">
                    </div>
                    <div class="result-content">
                        <div class="result-name">${this.escapeHtml(result.name)}</div>
                        <div class="result-details">
                            <span class="detail-item">BR: ${this.escapeHtml(result.partialBRNumber)}</span>
                            <span class="detail-separator">|</span>
                            <span class="detail-item">Country: ${this.escapeHtml(result.country)}</span>
                        </div>
                    </div>
                `;
            }

            // Add click handler for result selection
            item.addEventListener('click', () => {
                this.selectResult(result, index);
            });

            resultsList.appendChild(item);
        });
    }

    /**
     * Select a result
     */
    selectResult(result, index) {
        this.state.selectedResult = result;

        // Uncheck create new
        document.getElementById('create-new-checkbox').checked = false;

        // Update radio
        const radio = document.getElementById(`result-${index}`);
        if (radio) {
            radio.checked = true;
        }

        // Highlight selected item
        document.querySelectorAll('.result-item').forEach(item => {
            item.classList.remove('selected');
        });
        document.querySelector(`.result-item[data-index="${index}"]`)?.classList.add('selected');

        // If organization, set country and LOCK the dropdown
        if (this.state.registrantType === 'organization') {
            const countrySelect = document.getElementById('country-select');
            if (countrySelect) {
                // Set country from result (default to 1 = Sri Lanka if not provided)
                const countryId = result.countryId || 1;
                countrySelect.value = countryId;
                this.state.countryId = countryId;

                // Lock the country dropdown - user cannot change it
                countrySelect.disabled = true;
                countrySelect.classList.add('locked');
                countrySelect.style.backgroundColor = '#f0f0f0';
                countrySelect.style.cursor = 'not-allowed';

                console.log('Country dropdown LOCKED for organization:', result.name);
            }
        }

        this.updateContinueButton();
    }

    /**
     * Clear result selection
     */
    clearResultSelection() {
        document.querySelectorAll('.result-item').forEach(item => {
            item.classList.remove('selected');
        });
        document.querySelectorAll('input[name="selected-result"]').forEach(radio => {
            radio.checked = false;
        });
    }

    /**
     * Clear results display
     */
    clearResults() {
        document.getElementById('results-list').innerHTML = '';
        document.getElementById('results-header').classList.add('hidden');
        document.getElementById('no-results').classList.add('hidden');
        document.getElementById('error-message').classList.add('hidden');
        document.getElementById('create-new-option').classList.add('hidden');
        this.state.results = [];
        this.state.selectedResult = null;

        // Unlock the country dropdown when results are cleared
        const countrySelect = document.getElementById('country-select');
        if (countrySelect) {
            countrySelect.disabled = false;
            countrySelect.classList.remove('locked');
            countrySelect.style.backgroundColor = '';
            countrySelect.style.cursor = '';
        }

        this.updateContinueButton();
    }

    /**
     * Set loading state
     */
    setLoading(loading) {
        this.state.isLoading = loading;
        const spinner = document.getElementById('loading-spinner');
        const resultsList = document.getElementById('results-list');

        if (loading) {
            spinner.classList.remove('hidden');
            resultsList.classList.add('hidden');
        } else {
            spinner.classList.add('hidden');
            resultsList.classList.remove('hidden');
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        const errorEl = document.getElementById('error-message');
        errorEl.querySelector('p').textContent = message;
        errorEl.classList.remove('hidden');
        document.getElementById('no-results').classList.add('hidden');
    }

    /**
     * Update continue button state
     */
    updateContinueButton() {
        const continueBtn = document.getElementById('continue-btn');
        const createNewChecked = document.getElementById('create-new-checkbox').checked;

        let canContinue = false;

        if (createNewChecked) {
            canContinue = true;
        } else if (this.state.selectedResult) {
            if (this.state.registrantType === 'organization') {
                canContinue = this.state.countryId > 0;
            } else {
                canContinue = true;
            }
        }

        continueBtn.disabled = !canContinue;
    }

    /**
     * Handle continue button click
     */
    async onContinue() {
        const createNew = document.getElementById('create-new-checkbox').checked;

        if (createNew) {
            this.dispatchEvent('createNew', {
                registrantType: this.state.registrantType,
                domainReasonId: this.state.domainReasonId,
            });
            return;
        }

        if (!this.state.selectedResult) {
            return;
        }

        // Validate selection
        try {
            const response = await fetch(`${this.options.apiBase}/validate-selection`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    registrantType: this.state.registrantType,
                    domainReasonId: this.state.domainReasonId,
                    registrantId: this.state.selectedResult.id,
                    countryId: this.state.countryId,
                }),
            });

            const data = await response.json();

            if (data.success && data.valid) {
                this.dispatchEvent('selectionConfirmed', {
                    registrantType: this.state.registrantType,
                    registrant: this.state.selectedResult,
                    domainReasonId: this.state.domainReasonId,
                    countryId: this.state.countryId,
                });
            } else {
                this.showError(data.error || 'Validation failed');
            }
        } catch (error) {
            console.error('Validation error:', error);
            this.showError('Validation failed. Please try again.');
        }
    }

    /**
     * Handle cancel button click
     */
    onCancel() {
        this.dispatchEvent('cancelled', {});
    }

    /**
     * Dispatch custom event
     */
    dispatchEvent(name, detail) {
        const event = new CustomEvent(`registrantSearch:${name}`, {
            detail,
            bubbles: true,
        });
        this.container.dispatchEvent(event);
    }

    /**
     * Escape HTML for safe display
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    /**
     * Get current state
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Set domain reason programmatically
     */
    setDomainReason(reasonId) {
        document.getElementById('domain-reason').value = reasonId;
        this.onDomainReasonChange(reasonId);
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RegistrantSearch;
}

// Auto-initialize if container exists
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('registrant-search-container')) {
        window.registrantSearch = new RegistrantSearch();
    }
});

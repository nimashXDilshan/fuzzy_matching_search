<% require css('mysite/css/registrant-search.css') %>
<% require javascript('mysite/javascript/registrant-search.js') %>

<div id="registrant-search-container" 
     class="registrant-search-wrapper"
     data-api-base="$APIBase"
     data-min-search-length="$MinSearchLength"
     data-debounce-ms="$DebounceMs">
    <%-- JavaScript will render the component here --%>
    <noscript>
        <div class="no-js-message">
            <p>JavaScript is required to use the registrant search feature.</p>
        </div>
    </noscript>
</div>

<script>
    // Initialize with server-side configuration if provided
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.getElementById('registrant-search-container');
        
        if (container && typeof RegistrantSearch !== 'undefined') {
            window.registrantSearch = new RegistrantSearch({
                containerSelector: '#registrant-search-container',
                apiBase: container.dataset.apiBase || '/api/registration',
                minSearchLength: parseInt(container.dataset.minSearchLength, 10) || 3,
                debounceMs: parseInt(container.dataset.debounceMs, 10) || 400
            });

            // Listen for events
            container.addEventListener('registrantSearch:selectionConfirmed', function(e) {
                console.log('Selection confirmed:', e.detail);
                // Handle selection confirmation
                // e.detail contains: registrantType, registrant, domainReasonId, countryId
            });

            container.addEventListener('registrantSearch:createNew', function(e) {
                console.log('Create new:', e.detail);
                // Handle create new registrant
                // e.detail contains: registrantType, domainReasonId
            });

            container.addEventListener('registrantSearch:cancelled', function(e) {
                console.log('Search cancelled');
                // Handle cancellation
            });
        }
    });
</script>

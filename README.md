# Fuzzy Matching & Search – Customer & Organization

**Last Updated: March 3, 2026 (Mock Data Demo Focus)**

[![PHP CI](https://github.com/nimashXDilshan/fuzzy_matching_search/actions/workflows/php.yml/badge.svg)](https://github.com/nimashXDilshan/fuzzy_matching_search/actions/workflows/php.yml)

## Project Overview

This repository contains a **Fuzzy Search & Matching Demo** designed for customer and organization data. It showcases how to handle typos, phonetic variations, and data masking while maintaining high search accuracy.

### Key Features (Demo)
- **Fuzzy Search**: Implements Levenshtein, Soundex, Metaphone, and N-Gram algorithms.
- **Large Dataset**: Tested with 10,000+ mock individual and organization records.
- **Data Masking**: Automatically masks sensitive fields like NIC, Phone, and Email.
- **Instant Results**: Client-side filtering for immediate UI feedback.

## Requirements

- PHP 8.1+
- SilverStripe Framework 5.0+
- MySQL 5.7+ or MariaDB 10.2+

## Installation

1. **Clone or copy the project:**
   ```bash
   cd /path/to/your/silverstripe-project
   cp -r /path/to/this/module mysite
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Run database migration:**
   ```bash
   vendor/bin/sake dev/build flush=1
   ```

4. **Create full-text indexes:**
   ```bash
   mysql -u root -p your_database < database/migrations/create_indexes.sql
   ```

5. **Optimize search performance (Optional):**
   Run the optimization migration to improve search speed for organizations and customer filtering:
   ```bash
   mysql -u root -p your_database < database/migrations/optimize_search_indexes.sql
   ```

## Project Structure

```
mysite/
├── _config/
│   ├── config.yml          # SilverStripe configuration
│   └── routes.yml          # API route definitions
├── code/
│   ├── Controllers/API/
│   │   └── RegistrantSearchController.php
│   ├── DTOs/
│   │   ├── CustomerSearchResultDTO.php
│   │   └── OrganizationSearchResultDTO.php
│   ├── Models/
│   │   ├── Members/
│   │   │   ├── Customer.php
│   │   │   └── OrganizationMember.php
│   │   ├── Organizations/
│   │   │   └── Organization.php
│   │   └── Registration/
│   │       ├── Country.php
│   │       └── DomainReason.php
│   ├── Policies/
│   │   └── OrganizationAccessPolicy.php
│   ├── Services/
│   │   ├── Registration/
│   │   │   └── RegistrantTypeResolver.php
│   │   └── Search/
│   │       ├── CustomerSearchService.php
│   │       ├── FuzzyMatchEngine.php
│   │       └── OrganizationSearchService.php
│   └── Utils/
│       └── DataMaskingUtil.php
├── css/
│   └── registrant-search.css
├── javascript/
│   └── registrant-search.js
├── templates/Includes/
│   └── RegistrantSearch.ss
└── tests/
    ├── Integration/
    │   └── RegistrantSearchControllerTest.php
    └── Unit/
        ├── CustomerSearchServiceTest.php
        ├── DataMaskingUtilTest.php
        ├── OrganizationSearchServiceTest.php
        └── RegistrantTypeResolverTest.php
```

## API Endpoints

### Search Customers
```http
POST /api/registration/search-customers
Content-Type: application/json

{
  "searchTerm": "john",
  "searchFields": ["name", "nic", "email", "phone"],
  "limit": 20,
  "offset": 0
}
```

### Search Organizations
```http
POST /api/registration/search-organizations
Content-Type: application/json

{
  "searchTerm": "company",
  "limit": 20,
  "offset": 0
}
```

### Get Domain Reasons
```http
GET /api/registration/domain-reasons
```

### Get Registrant Types
```http
GET /api/registration/registrant-types/{domainReasonId}
```

### Get Countries
```http
GET /api/registration/countries
```

### Validate Selection
```http
POST /api/registration/validate-selection
Content-Type: application/json

{
  "registrantType": "organization",
  "domainReasonId": 3,
  "registrantId": 567,
  "countryId": 1
}
```

## Usage

### Include the Search Component

In your SilverStripe template:
```html
<% include RegistrantSearch %>
```

### JavaScript API

```javascript
// Initialize with custom options
const search = new RegistrantSearch({
    containerSelector: '#my-container',
    debounceMs: 400,
    minSearchLength: 3
});

// Listen for events
document.addEventListener('registrantSearch:selectionConfirmed', (e) => {
    console.log('Selected:', e.detail);
});

document.addEventListener('registrantSearch:createNew', (e) => {
    console.log('Create new:', e.detail);
});
```

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests only  
composer test:integration

# Run code linting
composer lint
```

### Backend Algorithm Tests
A standalone PHP script is provided to test the fuzzy matching algorithms in isolation:
```bash
php test_algorithms.php
```

## Running the UI Demo
You can run a standalone interactive demo of the search component:
1. Start the PHP development server:
   ```bash
   php -S localhost:8123
   ```
2. Open your browser and visit: [http://localhost:8123/demo/index.html](http://localhost:8123/demo/index.html)

## Docker Usage

### Local Development
You can run the entire application and its database using Docker Compose:

1. Copy `.env.example` to `.env`.
2. Start the containers:
   ```bash
   docker-compose up -d
   ```
3. The app will be available at [http://localhost:8080](http://localhost:8080).
4. Run migrations:
   ```bash
   docker-compose exec app vendor/bin/sake dev/build flush=1
   ```


### Docker Hub Automation
The project is configured with GitHub Actions to automatically build and push Docker images.

**Required GitHub Secrets:**
- `DOCKERHUB_USERNAME`: Your Docker Hub username.
- `DOCKERHUB_TOKEN`: Your Docker Hub Personal Access Token.

The image will be pushed as `<your-username>/fuzzy-matching-search:latest`.

## Data Masking




All search results automatically mask sensitive data:

| Field | Original | Masked |
|-------|----------|--------|
| Email | john.doe@gmail.com | jo***@gmail.com |
| NIC | 199012345678V | ***5678V |
| Name | John Doe | John D*** |
| BR Number | PV12345678 | ****5678 |

## Domain Reason Types

| Reason | Individual | Organization |
|--------|------------|--------------|
| Personal/Individual | ✅ | ❌ |
| Business/Commercial | ❌ | ✅ |
| Government Entity | ❌ | ✅ |
| Educational Institution | ❌ | ✅ |
| Non-Profit Organization | ❌ | ✅ |
| General Purpose | ✅ | ✅ |

## License

BSD-3-Clause

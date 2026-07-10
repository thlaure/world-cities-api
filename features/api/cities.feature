Feature: City API
  As an API consumer
  I want to retrieve cities
  So that I can display city information

  Scenario: GET /api/v1/cities returns empty collection when no cities exist
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities"
    Then the response status code should be 200
    And the response should be JSON
    And the JSON collection should be empty
    And the response content type should contain "application/ld+json"

  Scenario: GET /health returns ok when the database is reachable
    Given there are no cities in the database
    When I send a "GET" request to "/health" accepting "application/json"
    Then the response status code should be 200
    And the response should be JSON
    And the JSON response "status" should equal "ok"

  Scenario: GET /api/v1/cities returns all cities
    Given the following cities exist:
      | countryCode | localCode | name  | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris | 75              | 11         | 75001      |
      | FR          | 69123     | Lyon  | 69              | 84         | 69001      |
    When I send a "GET" request to "/api/v1/cities"
    Then the response status code should be 200
    And the response should be JSON
    And the JSON collection should have 2 items
    And the JSON response "totalItems" should equal "2"

  Scenario: GET /api/v1/cities filters by name (partial)
    Given the following cities exist:
      | countryCode | localCode | name  | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris | 75              | 11         | 75001      |
      | FR          | 69123     | Lyon  | 69              | 84         | 69001      |
    When I send a "GET" request to "/api/v1/cities?name=par"
    Then the response status code should be 200
    And the JSON collection should have 1 items
    And the JSON response "totalItems" should equal "1"

  Scenario: GET /api/v1/cities filters by exactName
    Given the following cities exist:
      | countryCode | localCode | name     | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris    | 75              | 11         | 75001      |
      | FR          | 75008     | Paris 8e | 75              | 11         | 75008      |
      | FR          | 69123     | Lyon     | 69              | 84         | 69001      |
    When I send a "GET" request to "/api/v1/cities?exactName=Paris"
    Then the response status code should be 200
    And the JSON collection should have 1 items
    And the JSON response "totalItems" should equal "1"

  Scenario: GET /api/v1/cities combines name and exactName filters
    Given the following cities exist:
      | countryCode | localCode | name      | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris     | 75              | 11         | 75001      |
      | FR          | 75008     | Paris 8e  | 75              | 11         | 75008      |
      | FR          | 75009     | Paris Sud | 75              | 11         | 75009      |
    When I send a "GET" request to "/api/v1/cities?name=Par&exactName=Paris"
    Then the response status code should be 200
    And the JSON collection should have 1 items
    And the JSON response "totalItems" should equal "1"

  Scenario: GET /api/v1/cities filters by departmentCode
    Given the following cities exist:
      | countryCode | localCode | name     | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris    | 75              | 11         | 75001      |
      | FR          | 75008     | Paris 8e | 75              | 11         | 75008      |
      | FR          | 69123     | Lyon     | 69              | 84         | 69001      |
    When I send a "GET" request to "/api/v1/cities?departmentCode=75"
    Then the response status code should be 200
    And the JSON collection should have 2 items

  Scenario: GET /api/v1/cities filters by regionCode
    Given the following cities exist:
      | countryCode | localCode | name  | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris | 75              | 11         | 75001      |
      | FR          | 69123     | Lyon  | 69              | 84         | 69001      |
    When I send a "GET" request to "/api/v1/cities?regionCode=84"
    Then the response status code should be 200
    And the JSON collection should have 1 items

  Scenario: GET /api/v1/cities filters by countryCode
    Given the following cities exist:
      | countryCode | localCode | name      | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris     | 75              | 11         | 75001      |
      | DE          | 08111000  | Stuttgart |                 |            | 70173      |
    When I send a "GET" request to "/api/v1/cities?countryCode=DE"
    Then the response status code should be 200
    And the JSON collection should have 1 items
    And the JSON response "totalItems" should equal "1"

  Scenario: GET /api/v1/cities rejects an invalid countryCode filter
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities?countryCode=XX"
    Then the response status code should be 422
    And the response should be JSON
    And the JSON response should be a RFC 7807 problem

  Scenario: GET /api/v1/cities rejects empty exactName filter
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities?exactName="
    Then the response status code should be 422
    And the response should be JSON
    And the JSON response should be a RFC 7807 problem

  Scenario: GET /api/v1/cities echoes X-Request-Id
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities" with headers:
      | X-Request-Id | test-request-id-123 |
    Then the response status code should be 200
    And the response header "X-Request-Id" should equal "test-request-id-123"

  Scenario: GET /api/v1/cities sanitizes an unsafe X-Request-Id
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities" with headers:
      | X-Request-Id | abc<script>123 |
    Then the response status code should be 200
    And the response header "X-Request-Id" should equal "abcscript123"

  Scenario: GET /api/v1/cities is rate limited
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities"
    And I send a "GET" request to "/api/v1/cities"
    Then the response status code should be 429
    And the response should be JSON
    And the response content type should contain "application/problem+json"
    And the JSON response should be a RFC 7807 problem

  Scenario: GET /api/v1/cities/{countryCode}/{localCode} returns a single city
    Given the following cities exist:
      | countryCode | localCode | name    | departmentCode | regionCode | postalCode |
      | FR          | 2A004     | Ajaccio | 2A              | 94         | 20000      |
    When I send a "GET" request to "/api/v1/cities/FR/2A004"
    Then the response status code should be 200
    And the response should be JSON
    And the JSON response should equal:
      | countryCode     | FR      |
      | localCode       | 2A004   |
      | name            | Ajaccio |
      | departmentCode  | 2A      |
      | postalCode      | 20000   |
    And the response content type should contain "application/ld+json"

  Scenario: GET /api/v1/cities/{countryCode}/{localCode} returns 404 for unknown local code
    Given there are no cities in the database
    When I send a "GET" request to "/api/v1/cities/FR/UNKNOWN"
    Then the response status code should be 404
    And the response should be JSON
    And the JSON response should be a RFC 7807 problem

  Scenario: GET /api/v1/cities/{countryCode}/{localCode} distinguishes the same local code across countries
    Given the following cities exist:
      | countryCode | localCode | name      | departmentCode | regionCode | postalCode |
      | FR          | 75056     | Paris     | 75              | 11         | 75001      |
      | DE          | 75056     | Not Paris |                 |            |            |
    When I send a "GET" request to "/api/v1/cities/DE/75056"
    Then the response status code should be 200
    And the JSON response should equal:
      | countryCode | DE        |
      | name        | Not Paris |

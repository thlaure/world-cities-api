Feature: Address search API
  As an API consumer
  I want to search for addresses
  So that I can resolve a partial address into a complete one, including its country

  Scenario: GET /api/v1/addresses/search returns matching addresses
    Given the address search returns:
      | label                       | houseNumber | street        | postalCode | city  | countryCode | latitude  | longitude |
      | 10 Rue de la Paix, Paris    | 10          | Rue de la Paix | 75002      | Paris | FR          | 48.868995 | 2.331141  |
    When I send a "GET" request to "/api/v1/addresses/search?q=10 rue de la paix paris" accepting "application/json"
    Then the response status code should be 200
    And the response should be JSON
    And the JSON collection should have 1 items

  Scenario: GET /api/v1/addresses/search filters by countryCode
    Given the address search returns:
      | label            | houseNumber | street       | postalCode | city    | countryCode | latitude | longitude |
      | Paris, France    |             |              |            | Paris   | FR          | 48.8566  | 2.3522    |
      | Berlin, Germany  |             |              |            | Berlin  | DE          | 52.5200  | 13.4050   |
    When I send a "GET" request to "/api/v1/addresses/search?q=berlin&countryCode=DE" accepting "application/json"
    Then the response status code should be 200
    And the JSON collection should have 1 items

  Scenario: GET /api/v1/addresses/search rejects an empty q parameter
    Given the address search returns:
      | label | houseNumber | street | postalCode | city | countryCode | latitude | longitude |
    When I send a "GET" request to "/api/v1/addresses/search?q=" accepting "application/json"
    Then the response status code should be 422
    And the response should be JSON
    And the JSON response should be a RFC 7807 problem

  Scenario: GET /api/v1/addresses/search rejects an invalid countryCode
    Given the address search returns:
      | label | houseNumber | street | postalCode | city | countryCode | latitude | longitude |
    When I send a "GET" request to "/api/v1/addresses/search?q=paris&countryCode=XX" accepting "application/json"
    Then the response status code should be 422
    And the response should be JSON
    And the JSON response should be a RFC 7807 problem

  Scenario: GET /api/v1/addresses/search returns 503 when the provider is unavailable
    Given the address search provider is unavailable
    When I send a "GET" request to "/api/v1/addresses/search?q=paris" accepting "application/json"
    Then the response status code should be 503
    And the response should be JSON
    And the JSON response should be a RFC 7807 problem

<?php
if (!defined('ABSPATH')) exit;

class BBH_Schema_Score_Engine {

    private $json = '';
    private $decoded = null;
    private $is_valid_json = false;
    private $extracted_schema = null;
    private $detected_types = array();
    private $primary_type = '';
    private $secondary_types = array();
    private $score = 0;
    private $breakdown = array();
    private $warnings = array();
    private $suggestions = array();

    const SCORE_SYNTAX_MAX = 20;
    const SCORE_STRUCTURAL_MAX = 30;
    const SCORE_TYPE_QUALITY_MAX = 20;
    const SCORE_RICH_RESULT_MAX = 30;
    const SCORE_TOTAL_MAX = 100;

    const TYPE_PRIORITY = array(
        'Article',
        'Product',
        'FAQPage',
        'LocalBusiness',
        'Service',
        'WebSite',
        'BreadcrumbList',
        'BlogPosting',
        'NewsArticle',
        'TechArticle',
        'Event',
        'Recipe',
        'Organization',
        'Person',
        'WebPage'
    );

    const RICH_RESULT_TYPES = array(
        'FAQPage', 'QAPage', 'HowTo', 'Recipe', 'Product', 'LocalBusiness',
        'Organization', 'Person', 'Event', 'Course', 'JobPosting', 'VideoObject',
        'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Book', 'Movie',
        'TVSeries', 'SoftwareApplication', 'MobileApplication', 'HotelRoom',
        'FlightReservation', 'TrainReservation', 'BusReservation', 'RentalCar',
        'TouristAttraction', 'Restaurant', 'BarOrPub', 'CafeOrCoffeeHouse', 'Bakery'
    );

    const DANGEROUS_TYPES = array(
        'CryptoCurrency', 'BankAccount', 'LoanOrCredit', 'FinancialProduct',
        'InsurancePolicy', 'GovernmentService', 'Permit', 'ReservationPackage'
    );

    const SCORING_PROFILES = array(
        'LocalBusiness' => array(
            'required' => array('@type', 'name', 'address'),
            'scoring' => array(
                'address.streetAddress' => 5,
                'address.addressLocality' => 3,
                'address.postalCode' => 3,
                'telephone' => 5,
                'geo.latitude' => 5,
                'geo.longitude' => 3,
                'openingHoursSpecification' => 5,
                'aggregateRating' => 3,
                'sameAs' => 3
            ),
            'relevant_fields' => array('name', 'address', 'telephone', 'geo', 'openingHoursSpecification', 'aggregateRating', 'sameAs', 'priceRange', 'image')
        ),
        'FAQPage' => array(
            'required' => array('@type', 'mainEntity'),
            'scoring' => array(
                'mainEntity' => 10,
                'question_count' => 5,
                'answer_length' => 10,
                'valid_qa_structure' => 10
            ),
            'relevant_fields' => array('mainEntity')
        ),
        'Article' => array(
            'required' => array('@type', 'headline', 'author', 'datePublished'),
            'scoring' => array(
                'headline' => 5,
                'author' => 5,
                'datePublished' => 5,
                'dateModified' => 3,
                'image' => 5,
                'mainEntityOfPage' => 5,
                'description' => 3,
                'publisher' => 3
            ),
            'relevant_fields' => array('headline', 'author', 'datePublished', 'dateModified', 'image', 'mainEntityOfPage', 'description', 'publisher', 'mainEntity')
        ),
        'Product' => array(
            'required' => array('@type', 'name'),
            'scoring' => array(
                'name' => 5,
                'description' => 3,
                'brand' => 5,
                'image' => 3,
                'sku' => 3,
                'offers.price' => 5,
                'offers.priceCurrency' => 5,
                'offers.availability' => 5,
                'offers.priceSpecification' => 3,
                'aggregateRating' => 3,
                'review' => 3
            ),
            'relevant_fields' => array('name', 'description', 'brand', 'image', 'sku', 'offers', 'aggregateRating', 'review')
        ),
        'Service' => array(
            'required' => array('@type', 'name', 'provider'),
            'scoring' => array(
                'name' => 5,
                'description' => 5,
                'provider' => 5,
                'areaServed' => 5,
                'serviceType' => 3,
                'url' => 3,
                'image' => 3
            ),
            'relevant_fields' => array('name', 'description', 'provider', 'areaServed', 'serviceType', 'url', 'image')
        ),
        'BreadcrumbList' => array(
            'required' => array('@type', 'itemListElement'),
            'scoring' => array(
                'itemListElement' => 10,
                'item_count' => 5,
                'item_url' => 5,
                'item_position' => 5
            ),
            'relevant_fields' => array('itemListElement')
        ),
        'WebSite' => array(
            'required' => array('@type', 'name', 'url'),
            'scoring' => array(
                'name' => 5,
                'url' => 5,
                'publisher' => 5,
                'potentialAction' => 5,
                'description' => 3
            ),
            'relevant_fields' => array('name', 'url', 'publisher', 'potentialAction', 'description')
        ),
        'WebPage' => array(
            'required' => array('@type', 'name'),
            'scoring' => array(
                'name' => 5,
                'description' => 3,
                'url' => 3,
                'primaryImageOfPage' => 3,
                'datePublished' => 3,
                'author' => 3
            ),
            'relevant_fields' => array('name', 'description', 'url', 'primaryImageOfPage', 'datePublished', 'author')
        ),
        'Organization' => array(
            'required' => array('@type', 'name'),
            'scoring' => array(
                'name' => 5,
                'url' => 5,
                'logo' => 5,
                'contactPoint' => 3,
                'sameAs' => 3,
                'address' => 3,
                'telephone' => 3
            ),
            'relevant_fields' => array('name', 'url', 'logo', 'contactPoint', 'sameAs', 'address', 'telephone')
        ),
        'Event' => array(
            'required' => array('@type', 'name', 'startDate'),
            'scoring' => array(
                'name' => 5,
                'startDate' => 5,
                'endDate' => 5,
                'location' => 5,
                'organizer' => 5,
                'image' => 3,
                'description' => 3
            ),
            'relevant_fields' => array('name', 'startDate', 'endDate', 'location', 'organizer', 'image', 'description')
        ),
        'Recipe' => array(
            'required' => array('@type', 'name', 'recipeIngredient'),
            'scoring' => array(
                'name' => 5,
                'recipeIngredient' => 5,
                'recipeInstructions' => 5,
                'author' => 3,
                'datePublished' => 3,
                'image' => 5,
                'prepTime' => 3,
                'cookTime' => 3,
                'recipeYield' => 3,
                'nutrition' => 3
            ),
            'relevant_fields' => array('name', 'recipeIngredient', 'recipeInstructions', 'author', 'datePublished', 'image', 'prepTime', 'cookTime', 'recipeYield', 'nutrition')
        ),
        'Person' => array(
            'required' => array('@type', 'name'),
            'scoring' => array(
                'name' => 5,
                'url' => 3,
                'jobTitle' => 3,
                'sameAs' => 3,
                'image' => 3,
                'address' => 3,
                'telephone' => 3
            ),
            'relevant_fields' => array('name', 'url', 'jobTitle', 'sameAs', 'image', 'address', 'telephone')
        )
    );

    public function __construct($json_string = '') {
        $this->json = is_string($json_string) ? trim($json_string) : '';
    }

    public function analyze() {
        $this->score = 0;
        $this->breakdown = array(
            'syntax' => 0,
            'structural' => 0,
            'type_quality' => 0,
            'rich_result' => 0
        );
        $this->warnings = array();
        $this->suggestions = array();
        $this->detected_types = array();
        $this->primary_type = '';
        $this->secondary_types = array();

        if (empty($this->json)) {
            $this->warnings[] = 'No schema provided';
            return $this->get_result();
        }

        if (!$this->validate_json()) {
            return $this->get_result();
        }

        $this->extract_schema();
        $this->detect_schema_types();
        
        if (empty($this->detected_types)) {
            $this->warnings[] = 'No schema types detected';
            return $this->get_result();
        }

        $this->identify_primary_type();
        $this->calculate_syntax_score();
        $this->calculate_structural_score();
        $this->calculate_type_quality_score();
        $this->calculate_rich_result_score();

        $this->score = $this->breakdown['syntax'] + $this->breakdown['structural'] +
                      $this->breakdown['type_quality'] + $this->breakdown['rich_result'];

        return $this->get_result();
    }

    private function validate_json() {
        $blocks = $this->parse_multiple_json_blocks();
        
        if (empty($blocks)) {
            $this->is_valid_json = false;
            $this->warnings[] = 'No JSON content found';
            return false;
        }
        
        $valid_blocks = array();
        $invalid_blocks = array();
        
        foreach ($blocks as $index => $block) {
            $decoded = json_decode($block, true);
            $error = json_last_error();
            
            if (JSON_ERROR_NONE === $error && is_array($decoded) && !empty($decoded)) {
                $valid_blocks[] = $decoded;
            } else {
                $invalid_blocks[] = $index + 1;
            }
        }
        
        if (empty($valid_blocks)) {
            $this->is_valid_json = false;
            $this->warnings[] = 'No valid JSON blocks found';
            return false;
        }
        
        $this->is_valid_json = true;
        
        if (count($valid_blocks) === 1) {
            $this->decoded = $valid_blocks[0];
        } else {
            $this->decoded = array(
                '@context' => 'https://schema.org',
                '@graph' => $valid_blocks
            );
        }
        
        if (!empty($invalid_blocks)) {
            $this->warnings[] = 'Block(s) ' . implode(', ', $invalid_blocks) . ' had errors (processed valid blocks)';
        }
        
        return $this->is_valid_json;
    }

    public function parse_multiple_json_blocks() {
        $input = $this->json;
        
        $input = preg_replace('/<\s*script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>/i', '', $input);
        $input = preg_replace('/<\s*\/script\s*>/i', '', $input);
        $input = trim($input);
        
        if (empty($input)) {
            return array();
        }
        
        $result = $this->extract_json_blocks($input);
        
        if (empty($result)) {
            $result = $this->detect_short_types($input);
        } else {
            $has_valid = false;
            foreach ($result as $block) {
                $test = json_decode($block, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($test)) {
                    $has_valid = true;
                    break;
                }
            }
            if (!$has_valid) {
                $result = $this->detect_short_types($input);
            }
        }
        
        return $result;
    }
    
    private function detect_short_types($input) {
        $blocks = array();
        
        $input = preg_replace('/\s+/', '', $input);
        
        if (preg_match_all('/\{([A-Za-z]+)\}/', $input, $matches)) {
            foreach ($matches[1] as $type) {
                $blocks[] = json_encode(array(
                    '@context' => 'https://schema.org',
                    '@type' => $type
                ), JSON_UNESCAPED_SLASHES);
            }
        }
        
        return $blocks;
    }
    
    private function extract_json_blocks($input) {
        $blocks = array();
        $buffer = '';
        $depth = 0;
        $in_string = false;
        $escape = false;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            
            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }
            
            if ($char === '\\' && $in_string) {
                $buffer .= $char;
                $escape = true;
                continue;
            }
            
            if ($char === '"') {
                $in_string = !$in_string;
                $buffer .= $char;
                continue;
            }
            
            if (!$in_string) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                }
            }
            
            $buffer .= $char;
            
            if ($depth === 0 && !$in_string && strlen(trim($buffer))) {
                $trimmed = trim($buffer);
                if (!empty($trimmed)) {
                    $test = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $blocks[] = $trimmed;
                        $buffer = '';
                        continue;
                    }
                }
            }
        }
        
        if (strlen(trim($buffer))) {
            $trimmed = trim($buffer);
            $test = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $blocks[] = $trimmed;
            }
        }
        
        return empty($blocks) ? array($input) : $blocks;
    }

    private function extract_schema() {
        if (isset($this->decoded['@graph']) && is_array($this->decoded['@graph'])) {
            $this->extracted_schema = $this->decoded['@graph'];
        } elseif (isset($this->decoded[0]['@graph'])) {
            $this->extracted_schema = $this->decoded['@graph'];
        } elseif (isset($this->decoded['@type'])) {
            $this->extracted_schema = array($this->decoded);
        } else {
            $this->extracted_schema = array();
        }
    }

    private function detect_schema_types() {
        $types_found = array();

        foreach ($this->extracted_schema as $schema) {
            if (!isset($schema['@type'])) {
                continue;
            }

            $type = $schema['@type'];
            if (is_array($type)) {
                foreach ($type as $t) {
                    $types_found[] = $t;
                }
            } else {
                $types_found[] = $type;
            }
        }

        $this->detected_types = array_unique($types_found);
    }

    private function identify_primary_type() {
        $type_order = self::TYPE_PRIORITY;

        foreach ($type_order as $priority_type) {
            if (in_array($priority_type, $this->detected_types, true)) {
                $this->primary_type = $priority_type;
                $this->secondary_types = array_values(array_filter(
                    $this->detected_types,
                    function($t) use ($priority_type) {
                        return $t !== $priority_type;
                    }
                ));
                return;
            }
        }

        if (!empty($this->detected_types)) {
            $this->primary_type = $this->detected_types[0];
            $this->secondary_types = array_slice($this->detected_types, 1);
        }
    }

    private function get_schema_by_type($type) {
        foreach ($this->extracted_schema as $schema) {
            if (!isset($schema['@type'])) {
                continue;
            }
            $schema_type = $schema['@type'];
            if (is_array($schema_type)) {
                if (in_array($type, $schema_type, true)) {
                    return $schema;
                }
            } elseif ($schema_type === $type) {
                return $schema;
            }
        }
        return null;
    }

    private function calculate_syntax_score() {
        $score = 0;

        if ($this->is_valid_json) {
            $score += 10;
        }

        foreach ($this->extracted_schema as $schema) {
            if (isset($schema['@context']) && isset($schema['@type'])) {
                $score += 5;
                break;
            }
        }

        $this->breakdown['syntax'] = min($score, self::SCORE_SYNTAX_MAX);

        if ($this->breakdown['syntax'] < 10) {
            $this->warnings[] = 'Invalid JSON syntax - cannot proceed with further analysis';
        }
    }

    private function calculate_structural_score() {
        if (!$this->is_valid_json || empty($this->primary_type)) {
            return;
        }

        $score = 0;
        $profile = isset(self::SCORING_PROFILES[$this->primary_type])
            ? self::SCORING_PROFILES[$this->primary_type]
            : null;

        if (!$profile) {
            $score = 15;
            $this->breakdown['structural'] = min($score, self::SCORE_STRUCTURAL_MAX);
            return;
        }

        $required = $profile['required'];
        $schema = $this->get_schema_by_type($this->primary_type);

        if ($schema) {
            $has_required = 0;
            $has_empty = 0;

            foreach ($required as $field) {
                if (isset($schema[$field]) && !empty($schema[$field])) {
                    $has_required++;
                } elseif (isset($schema[$field]) && empty($schema[$field])) {
                    $has_empty++;
                }
            }

            if (count($required) > 0 && $has_required >= count($required)) {
                $score += 15;
            }

            if ($has_empty > 0) {
                $score -= 3;
                $this->warnings[] = 'Empty required field(s) in ' . $this->primary_type;
            }

            $scoring_fields = $profile['scoring'];
            $relevant = $profile['relevant_fields'];

            foreach ($relevant as $field) {
                $value = null;
                $path = explode('.', $field);
                
                if (count($path) === 1) {
                    $value = isset($schema[$field]) ? $schema[$field] : null;
                } elseif (count($path) === 2) {
                    $value = isset($schema[$path[0]][$path[1]]) ? $schema[$path[0]][$path[1]] : null;
                } elseif (count($path) === 3) {
                    $value = isset($schema[$path[0]][0][$path[2]]) ? $schema[$path[0]][0][$path[2]] : null;
                }

                if ($value !== null && !empty($value)) {
                    $points = isset($scoring_fields[$field]) ? $scoring_fields[$field] : 3;
                    $score += $points;
                }
            }

            if ($this->primary_type === 'FAQPage' && isset($schema['mainEntity'])) {
                $entities = $schema['mainEntity'];
                if (isset($entities['@type']) && $entities['@type'] === 'Question') {
                    $entities = array($entities);
                }
                if (is_array($entities)) {
                    $score += min(count($entities), 5);
                }
            }

            if ($this->primary_type === 'BreadcrumbList' && isset($schema['itemListElement'])) {
                $items = $schema['itemListElement'];
                if (isset($items['url'])) {
                    $items = array($items);
                }
                if (is_array($items)) {
                    $score += min(count($items), 5);
                }
            }
        }

        $this->breakdown['structural'] = min(max($score, 0), self::SCORE_STRUCTURAL_MAX);

        if ($this->breakdown['structural'] < 15) {
            $this->suggestions[] = 'Add required fields for ' . $this->primary_type;
        }
    }

    private function calculate_type_quality_score() {
        if (!$this->is_valid_json) {
            return;
        }

        $score = 0;
        $primary_rich = in_array($this->primary_type, self::RICH_RESULT_TYPES, true);

        if ($primary_rich) {
            $score += 15;
        }

        if (isset($this->extracted_schema[0]['@id'])) {
            $score += 5;
        }

        foreach ($this->detected_types as $type) {
            if (in_array($type, self::RICH_RESULT_TYPES, true)) {
                $score += 3;
            }
            if (in_array($type, self::DANGEROUS_TYPES, true)) {
                $score -= 5;
                $this->warnings[] = 'Potentially problematic schema type: ' . $type;
            }
        }

        $this->breakdown['type_quality'] = min(max($score, 0), self::SCORE_TYPE_QUALITY_MAX);
    }

    private function calculate_rich_result_score() {
        if (!$this->is_valid_json || empty($this->primary_type)) {
            return;
        }

        $score = 0;

        switch ($this->primary_type) {
            case 'FAQPage':
                $score = $this->score_faq_rich_result();
                break;
            case 'LocalBusiness':
                $score = $this->score_localbusiness_rich_result();
                break;
            case 'Product':
                $score = $this->score_product_rich_result();
                break;
            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                $score = $this->score_article_rich_result();
                break;
            case 'Service':
                $score = $this->score_service_rich_result();
                break;
            case 'Recipe':
                $score = $this->score_recipe_rich_result();
                break;
            case 'Event':
                $score = $this->score_event_rich_result();
                break;
            default:
                $score = in_array($this->primary_type, self::RICH_RESULT_TYPES, true) ? 20 : 10;
        }

        $this->breakdown['rich_result'] = min(max($score, 0), self::SCORE_RICH_RESULT_MAX);

        if ($this->breakdown['rich_result'] < 15) {
            $this->suggestions[] = 'Improve ' . $this->primary_type . ' for Google Rich Results eligibility';
        }
    }

    private function score_faq_rich_result() {
        $score = 0;
        $schema = $this->get_schema_by_type('FAQPage');

        if (!$schema || !isset($schema['mainEntity'])) {
            $this->warnings[] = 'FAQPage missing mainEntity';
            return 0;
        }

        $entities = $schema['mainEntity'];
        if (isset($entities['@type']) && $entities['@type'] === 'Question') {
            $entities = array($entities);
        }

        if (!is_array($entities)) {
            return 0;
        }

        $valid_count = 0;
        $total = count($entities);

        foreach ($entities as $entity) {
            if (!isset($entity['answer'])) {
                continue;
            }

            $answer = $entity['answer'];
            $text = '';

            if (is_array($answer)) {
                $text = isset($answer['text']) ? $answer['text'] : (isset($answer[0]['text']) ? $answer[0]['text'] : '');
            }

            if (strlen($text) >= 20) {
                $valid_count++;
            }
        }

        if ($valid_count > 0) {
            $score += 15;
        }

        $score += min(5, $total);

        if ($valid_count < $total && $total > 0) {
            $this->warnings[] = 'Some FAQ answers are too short (<20 characters)';
        }

        return $score;
    }

    private function score_localbusiness_rich_result() {
        $score = 0;
        $schema = $this->get_schema_by_type('LocalBusiness');

        if (!$schema) {
            return 0;
        }

        if (!empty($schema['name'])) {
            $score += 5;
        }

        if (!empty($schema['address'])) {
            $score += 10;
            $addr = $schema['address'];
            $has_full = isset($addr['streetAddress']) && isset($addr['addressLocality']) &&
                       isset($addr['addressRegion']) && isset($addr['postalCode']);
            if ($has_full) {
                $score += 5;
            }
        }

        if (!empty($schema['telephone'])) {
            $score += 5;
        }

        if (!empty($schema['geo'])) {
            $score += 5;
        }

        return min($score, 30);
    }

    private function score_product_rich_result() {
        $score = 0;
        $schema = $this->get_schema_by_type('Product');

        if (!$schema) {
            return 0;
        }

        if (!empty($schema['name'])) {
            $score += 3;
        }

        if (!empty($schema['brand'])) {
            $score += 5;
        }

        if (isset($schema['offers'])) {
            $offers = $schema['offers'];
            if (isset($offers[0])) {
                $offers = $offers[0];
            }

            if (!empty($offers['price']) && !empty($offers['priceCurrency'])) {
                $score += 10;
            }

            if (isset($offers['availability']) &&
                strpos($offers['availability'], 'InStock') !== false) {
                $score += 5;
            }
        }

        if (isset($schema['aggregateRating'])) {
            $score += 5;
        }

        return min($score, 30);
    }

    private function score_article_rich_result() {
        $score = 0;
        $types = array('Article', 'BlogPosting', 'NewsArticle', 'TechArticle');
        $schema = null;

        foreach ($types as $t) {
            $schema = $this->get_schema_by_type($t);
            if ($schema) {
                break;
            }
        }

        if (!$schema) {
            return 0;
        }

        $required = array('headline', 'image', 'datePublished', 'author');
        $has_all = true;

        foreach ($required as $field) {
            if (empty($schema[$field])) {
                $has_all = false;
                break;
            }
        }

        if ($has_all) {
            $score += 20;
        } else {
            foreach ($required as $field) {
                if (!empty($schema[$field])) {
                    $score += 3;
                }
            }
        }

        return min($score, 30);
    }

    private function score_service_rich_result() {
        $score = 0;
        $schema = $this->get_schema_by_type('Service');

        if (!$schema) {
            return 0;
        }

        if (!empty($schema['name'])) {
            $score += 5;
        }

        if (!empty($schema['description'])) {
            $score += 10;
        }

        if (!empty($schema['provider'])) {
            $score += 5;
        }

        return min($score, 30);
    }

    private function score_recipe_rich_result() {
        $score = 0;
        $schema = $this->get_schema_by_type('Recipe');

        if (!$schema) {
            return 0;
        }

        $required = array('name', 'recipeIngredient', 'recipeInstructions', 'image');
        $has_all = true;

        foreach ($required as $field) {
            if (empty($schema[$field])) {
                $has_all = false;
                break;
            }
        }

        if ($has_all) {
            $score += 25;
        } else {
            foreach ($required as $field) {
                if (!empty($schema[$field])) {
                    $score += 4;
                }
            }
        }

        return min($score, 30);
    }

    private function score_event_rich_result() {
        $score = 0;
        $schema = $this->get_schema_by_type('Event');

        if (!$schema) {
            return 0;
        }

        if (!empty($schema['name']) && !empty($schema['startDate'])) {
            $score += 10;
        }

        if (!empty($schema['location'])) {
            $score += 5;
        }

        if (!empty($schema['offers'])) {
            $score += 5;
        }

        return min($score, 30);
    }

    private function get_result() {
        return array(
            'score' => (int) $this->score,
            'max_score' => self::SCORE_TOTAL_MAX,
            'is_valid' => $this->is_valid_json,
            'detected_types' => $this->detected_types,
            'primary_type' => $this->primary_type,
            'secondary_types' => $this->secondary_types,
            'breakdown' => $this->breakdown,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions
        );
    }

    public function get_score() {
        return $this->score;
    }

    public function get_warnings() {
        return $this->warnings;
    }

    public function get_suggestions() {
        return $this->suggestions;
    }

    public function is_valid() {
        return $this->is_valid_json;
    }

    public function get_decoded() {
        return $this->decoded;
    }

    public function get_extracted_schema() {
        return $this->extracted_schema;
    }
}

class BBH_Schema_Optimizer {

    private $original_json = '';
    private $decoded = null;
    private $schema = array();
    private $changes_made = array();
    private $original_score = 0;
    private $new_score = 0;

    public function __construct($json_string = '') {
        $this->original_json = is_string($json_string) ? trim($json_string) : '';
    }

    public function optimize() {
        $this->changes_made = array();

        if (empty($this->original_json)) {
            return $this->get_result();
        }

        $this->decoded = json_decode($this->original_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->changes_made[] = 'Unable to parse JSON - no changes made';
            return $this->get_result();
        }

        $this->schema = $this->decoded;
        $this->extract_schema();
        $this->normalize_to_graph();
        $this->fix_local_business();
        $this->fix_offer_price();
        $this->fix_faq_answers();
        $this->remove_dangerous_types();
        $this->ensure_rich_fields();

        return $this->get_result();
    }

    private function extract_schema() {
        if (isset($this->schema['@graph']) && is_array($this->schema['@graph'])) {
            $this->schema = $this->schema['@graph'];
        } else {
            $this->schema = array($this->schema);
        }
    }

    private function normalize_to_graph() {
        if (count($this->schema) === 1) {
            return;
        }

        $normalized = array();
        foreach ($this->schema as $s) {
            if (!empty($s)) {
                $normalized[] = $s;
            }
        }

        if (count($normalized) > 1) {
            $this->schema = $normalized;
        }
    }

    private function fix_local_business() {
        foreach ($this->schema as &$schema) {
            if (!isset($schema['@type']) || $schema['@type'] !== 'LocalBusiness') {
                continue;
            }

            if (!isset($schema['address']) || !is_array($schema['address'])) {
                $schema['address'] = array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => '',
                    'addressLocality' => '',
                    'addressRegion' => '',
                    'addressCountry' => ''
                );
                $this->changes_made[] = 'LocalBusiness: Added placeholder address structure';
                continue;
            }

            $address = $schema['address'];
            $fixed = false;

            if (!isset($address['@type'])) {
                $address['@type'] = 'PostalAddress';
                $fixed = true;
            }

            if (!isset($address['streetAddress'])) {
                $address['streetAddress'] = '';
                $fixed = true;
            }

            if (!isset($address['addressLocality'])) {
                $address['addressLocality'] = '';
                $fixed = true;
            }

            if ($fixed) {
                $schema['address'] = $address;
                $this->changes_made[] = 'LocalBusiness: Added missing address fields (fill in manually)';
            }

            if (!isset($schema['geo'])) {
                $schema['geo'] = array(
                    '@type' => 'GeoCoordinates',
                    'latitude' => '',
                    'longitude' => ''
                );
                $this->changes_made[] = 'LocalBusiness: Added placeholder geo coordinates (fill in manually)';
            }

            if (!isset($schema['openingHoursSpecification'])) {
                $schema['openingHoursSpecification'] = array(
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                    'opens' => '09:00',
                    'closes' => '17:00'
                );
                $this->changes_made[] = 'LocalBusiness: Added placeholder hours (fill in manually)';
            }
        }
    }

    private function fix_offer_price() {
        foreach ($this->schema as &$schema) {
            if (!isset($schema['@type']) || $schema['@type'] !== 'Offer') {
                continue;
            }

            $has_price = !empty($schema['price']);
            $has_price_spec = isset($schema['priceSpecification']);

            if (!empty($schema['priceRange'])) {
                unset($schema['priceRange']);

                if (!$has_price && !$has_price_spec) {
                    $schema['priceSpecification'] = array(
                        '@type' => 'PriceSpecification',
                        'minPrice' => '',
                        'maxPrice' => '',
                        'priceCurrency' => 'USD'
                    );
                    $this->changes_made[] = 'Offer: Converted priceRange to priceSpecification (fill in values)';
                }
            }
        }
    }

    private function fix_faq_answers() {
        foreach ($this->schema as &$schema) {
            if (!isset($schema['@type']) || $schema['@type'] !== 'FAQPage') {
                continue;
            }

            if (!isset($schema['mainEntity']) || !is_array($schema['mainEntity'])) {
                continue;
            }

            $questions = $schema['mainEntity'];
            if (isset($questions['@type']) && $questions['@type'] === 'Question') {
                $questions = array($questions);
            }

            $updated = false;
            foreach ($questions as &$q) {
                if (!isset($q['answer'])) {
                    $q['answer'] = array(
                        '@type' => 'Answer',
                        'text' => ''
                    );
                    $updated = true;
                    continue;
                }

                $answer = $q['answer'];
                if (is_array($answer)) {
                    if (isset($answer['text']) && strlen($answer['text']) < 20) {
                        $this->changes_made[] = 'FAQ: Answer too short - please add at least 20 characters';
                    } elseif (!isset($answer['text'])) {
                        $q['answer']['text'] = '';
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                $schema['mainEntity'] = $questions;
                $this->changes_made[] = 'FAQ: Fixed answer structure';
            }
        }
    }

    private function remove_dangerous_types() {
        $dangerous = BBH_Schema_Score_Engine::DANGEROUS_TYPES;
        $removed = false;

        foreach ($this->schema as $key => $schema) {
            if (!isset($schema['@type'])) {
                continue;
            }

            $type = is_array($schema['@type']) ? $schema['@type'][0] : $schema['@type'];

            if (in_array($type, $dangerous, true)) {
                unset($this->schema[$key]);
                $removed = true;
                $this->changes_made[] = "Removed problematic type: {$type}";
            }
        }

        if ($removed) {
            $this->schema = array_values($this->schema);
        }
    }

    private function ensure_rich_fields() {
        $score_engine = new BBH_Schema_Score_Engine($this->original_json);
        $score_engine->analyze();
        $primary_type = $score_engine->get_decoded();
        
        if (!isset($primary_type['@type'])) {
            $decoded = json_decode($this->original_json, true);
            $primary_type = is_array($decoded) ? $decoded['@type'] : '';
        }

        foreach ($this->schema as &$schema) {
            if (!isset($schema['@type'])) {
                continue;
            }

            $type = is_array($schema['@type']) ? $schema['@type'][0] : $schema['@type'];

            if ($type === 'Product' && !isset($schema['brand'])) {
                $schema['brand'] = array(
                    '@type' => 'Brand',
                    'name' => ''
                );
                $this->changes_made[] = 'Product: Added placeholder brand (fill in manually)';
            }

            if (in_array($type, array('Article', 'BlogPosting', 'NewsArticle')) &&
                !isset($schema['image'])) {
                $schema['image'] = array('');
                $this->changes_made[] = "{$type}: Added placeholder image URL (fill in manually)";
            }
        }
    }

    private function get_result() {
        $final_schema = $this->build_final_schema();

        $original_score_obj = new BBH_Schema_Score_Engine($this->original_json);
        $original_result = $original_score_obj->analyze();
        $this->original_score = $original_score_obj->get_score();

        $new_score_obj = new BBH_Schema_Score_Engine($final_schema);
        $new_result = $new_score_obj->analyze();
        $this->new_score = $new_score_obj->get_score();

        return array(
            'original_schema' => $this->original_json,
            'optimized_schema' => $final_schema,
            'original_score' => $this->original_score,
            'new_score' => $this->new_score,
            'score_delta' => $this->new_score - $this->original_score,
            'changes_made' => $this->changes_made,
            'warnings' => $new_result['warnings'],
            'suggestions' => $new_result['suggestions']
        );
    }

    private function build_final_schema() {
        $non_empty = array();
        foreach ($this->schema as $s) {
            if (!empty($s)) {
                $non_empty[] = $s;
            }
        }

        if (count($non_empty) > 1) {
            return json_encode(array(
                '@context' => 'https://schema.org',
                '@graph' => $non_empty
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (count($non_empty) === 1 && isset($non_empty[0]['@context'])) {
            return json_encode($non_empty[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (count($non_empty) === 1) {
            $single = $non_empty[0];
            $single['@context'] = 'https://schema.org';
            return json_encode($single, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $this->original_json;
    }

    public function get_changes() {
        return $this->changes_made;
    }
}

function bbhcuschma_calculate_schema_score($json) {
    $engine = new BBH_Schema_Score_Engine($json);
    return $engine->analyze();
}

function bbhcuschma_optimize_schema($json) {
    $optimizer = new BBH_Schema_Optimizer($json);
    return $optimizer->optimize();
}

class BBH_Schema_Combiner {

    private $input = '';
    private $original_blocks = array();
    private $combined = null;
    private $errors = array();
    private $messages = array();

    public function __construct($json_string = '') {
        $this->input = is_string($json_string) ? trim($json_string) : '';
    }

    public function combine() {
        $this->original_blocks = $this->parse_blocks($this->input);
        
        if (empty($this->original_blocks)) {
            $this->errors[] = 'No valid JSON blocks found';
            return $this->get_result();
        }

        $objects = array();
        $failed = array();

        foreach ($this->original_blocks as $index => $block) {
            try {
                $decoded = json_decode($block, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $failed[] = $index + 1;
                    $this->errors[] = 'Block ' . ($index + 1) . ': ' . json_last_error_msg();
                    continue;
                }
                $objects[] = $this->normalize_object($decoded);
                $this->messages[] = 'Block ' . ($index + 1) . ' parsed successfully';
            } catch (Exception $e) {
                $failed[] = $index + 1;
                $this->errors[] = 'Block ' . ($index + 1) . ': ' . $e->getMessage();
            }
        }

        if (empty($objects)) {
            $this->errors[] = 'No valid JSON objects to combine';
            return $this->get_result();
        }

        $objects = $this->flatten_graph($objects);
        $objects = $this->merge_objects($objects);
        
        $this->combined = array(
            '@context' => 'https://schema.org',
            '@graph' => $objects
        );

        return $this->get_result();
    }

    private function parse_blocks($input) {
        $input = preg_replace('/<\s*script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>/i', '', $input);
        $input = preg_replace('/<\s*\/script\s*>/i', '', $input);
        $input = trim($input);

        if (empty($input)) {
            return array();
        }

        $short_pattern = '/\{([A-Za-z]+)\}/';
        if (preg_match_all($short_pattern, $input, $matches)) {
            $input = preg_replace('/\s+/', '', $input);
            if (preg_match_all('/\{([A-Za-z]+)\}/', $input, $matches)) {
                $blocks = array();
                foreach ($matches[1] as $type) {
                    $blocks[] = json_encode(array(
                        '@context' => 'https://schema.org',
                        '@type' => $type
                    ), JSON_UNESCAPED_SLASHES);
                }
                return $blocks;
            }
        }

        return $this->extract_json_blocks($input);
    }

    private function extract_json_blocks($input) {
        $blocks = array();
        $buffer = '';
        $depth = 0;
        $in_string = false;
        $escape = false;

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];

            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\' && $in_string) {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $in_string = !$in_string;
                $buffer .= $char;
                continue;
            }

            if (!$in_string) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                }
            }

            $buffer .= $char;

            if ($depth === 0 && !$in_string && strlen(trim($buffer))) {
                $trimmed = trim($buffer);
                $test = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $blocks[] = $trimmed;
                    $buffer = '';
                }
            }
        }

        if (strlen(trim($buffer))) {
            $trimmed = trim($buffer);
            $test = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $blocks[] = $trimmed;
            }
        }

        return empty($blocks) ? array($input) : $blocks;
    }

    private function normalize_object($obj) {
        if (!is_array($obj)) {
            return $obj;
        }

        $result = array();
        foreach ($obj as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->normalize_object($value);
            } elseif ($this->is_numeric_string($key) && $this->is_numeric_string($value)) {
                $result[$key] = $this->convert_numeric($value);
            } elseif (in_array($key, array('latitude', 'longitude', 'ratingValue', 'price', 'minPrice', 'maxPrice', 'latitude', 'longitude'), true)) {
                $result[$key] = $this->convert_numeric($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function is_numeric_string($value) {
        return is_string($value) && preg_match('/^-?[\d.]+$/', $value);
    }

    private function convert_numeric($value) {
        if (is_string($value) && is_numeric($value)) {
            return floatval($value);
        }
        return $value;
    }

    private function flatten_graph($objects) {
        $flattened = array();

        foreach ($objects as $obj) {
            if (isset($obj['@graph']) && is_array($obj['@graph'])) {
                foreach ($obj['@graph'] as $graph_item) {
                    if (is_array($graph_item)) {
                        $flattened[] = $graph_item;
                    }
                }
            } else {
                $flattened[] = $obj;
            }
        }

        return $flattened;
    }

    private function merge_objects($objects) {
        $merged = array();
        $id_map = array();

        foreach ($objects as $obj) {
            if (!is_array($obj)) {
                continue;
            }

            $obj_id = isset($obj['@id']) ? $obj['@id'] : null;

            if ($obj_id && isset($id_map[$obj_id])) {
                $existing = $id_map[$obj_id];
                $merged[$existing] = array_merge($existing, $obj);
                continue;
            }

            if ($obj_id) {
                $id_map[$obj_id] = count($merged);
            }

            $merged[] = $obj;
        }

        return array_values($merged);
    }

    private function get_result() {
        $before_count = count($this->original_blocks);
        $after = $this->combined ? json_encode($this->combined, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';

        return array(
            'success' => !empty($this->combined),
            'original' => $this->input,
            'original_blocks' => $before_count,
            'combined' => $after,
            'is_combined' => !empty($this->combined),
            'errors' => $this->errors,
            'messages' => $this->messages
        );
    }
}

function bbhcuschma_combine_schema($json) {
    $combiner = new BBH_Schema_Combiner($json);
    return $combiner->combine();
}
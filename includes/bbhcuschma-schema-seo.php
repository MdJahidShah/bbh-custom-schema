<?php
if (!defined('ABSPATH')) exit;

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
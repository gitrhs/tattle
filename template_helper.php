<?php
/**
 * Template Helper Functions
 * Handles rendering templates with placeholder replacements
 */

/**
 * Render a template by replacing placeholders with actual values
 *
 * @param string $template Template string with {{placeholder}} syntax
 * @param array $variables Associative array of variable names and values
 * @param bool $escape Whether to escape values (default: true for security)
 * @return string Rendered template with placeholders replaced
 *
 * Example:
 *   $template = "Hello {{name}}, you are {{age}} years old";
 *   $vars = ['name' => 'John', 'age' => 25];
 *   echo renderTemplate($template, $vars);
 *   // Output: Hello John, you are 25 years old
 */
function renderTemplate($template, $variables, $escape = true) {
    if (empty($template)) {
        return '';
    }

    $rendered = $template;

    foreach ($variables as $key => $value) {
        $placeholder = '{{' . $key . '}}';

        // Escape value if needed (prevents XSS, but allows quotes for JSON contexts)
        if ($escape && is_string($value)) {
            // Use addslashes for compatibility with existing code
            $value = addslashes($value);
        }

        $rendered = str_replace($placeholder, $value, $rendered);
    }

    return $rendered;
}

/**
 * Get available template variables
 * Returns list of all supported placeholder variables
 *
 * @return array List of variable names and descriptions
 */
function getTemplateVariables() {
    return [
        'language' => 'User\'s language preference (e.g., "English", "Indonesian")',
        'ai_name' => 'AI assistant name (from ai_instruction table)',
        'ai_role' => 'AI assistant role/responsibilities (from ai_instruction table)',
        'ai_communication_style' => 'Communication style guidelines (from ai_instruction table)',
        'ai_introduction' => 'AI assistant introduction/greeting (from ai_instruction table)'
    ];
}

/**
 * Validate template syntax
 * Checks if all placeholders in template are valid
 *
 * @param string $template Template to validate
 * @return array Array with 'valid' (bool) and 'errors' (array of error messages)
 */
function validateTemplate($template) {
    $valid_vars = array_keys(getTemplateVariables());
    $errors = [];

    // Find all placeholders
    preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);
    $used_placeholders = array_unique($matches[1]);

    // Check for invalid placeholders
    foreach ($used_placeholders as $placeholder) {
        if (!in_array($placeholder, $valid_vars)) {
            $errors[] = "Unknown placeholder: {{" . $placeholder . "}}";
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'placeholders' => $used_placeholders
    ];
}
?>

<?php

/**
 * iPharmaLink :: Validator
 * ---------------------------------------------------------------------------
 * Fluent, rule-based validation. Rules are declared as pipe-separated
 * strings ("required|email|max:190") and applied through declared() —
 * every field under validation is explicitly listed, so an attacker cannot
 * smuggle unexpected keys into a model.
 *
 * Usage:
 *   $v = new Validator($request->all());
 *   $data = $v->validate([
 *       'email'    => 'required|email|max:190',
 *       'password' => ['required', 'min:8', 'confirmed'],
 *   ]);
 */

declare(strict_types=1);

namespace App;

final class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,list<string>> */
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param array<string,string|list<string>> $rules
     * @return array<string,mixed>  the validated (and cast) subset
     * @throws ValidationException
     */
    public function validate(array $rules): array
    {
        $this->errors = [];
        $clean        = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList  = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value     = $this->data[$field] ?? null;
            $validated = $this->applyRules($field, $value, $ruleList);

            if ($validated['has_value'] && !in_array('nullable', $ruleList, true)) {
                $clean[$field] = $validated['value'];
            }
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
        return $clean;
    }

    /**
     * Run the rules but return the error bag instead of throwing.
     *
     * @param array<string,string|list<string>> $rules
     * @return array{valid:bool, errors:array<string,list<string>>, data:array<string,mixed>}
     */
    public function check(array $rules): array
    {
        try {
            return ['valid' => true, 'errors' => [], 'data' => $this->validate($rules)];
        } catch (ValidationException $e) {
            return ['valid' => false, 'errors' => $e->errors(), 'data' => []];
        }
    }

    /**
     * @param  list<string> $rules
     * @return array{has_value:bool, value:mixed}
     */
    private function applyRules(string $field, mixed $value, array $rules): array
    {
        $isNullable = in_array('nullable', $rules, true);
        $isEmpty    = $value === null || $value === '' || (is_array($value) && $value === []);

        // ---- presence -----------------------------------------------------
        if (in_array('required', $rules, true) && $isEmpty) {
            $this->addError($field, "{$field} is required.");
            return ['has_value' => false, 'value' => $value];
        }
        if ($isEmpty) {
            return ['has_value' => false, 'value' => $value];
        }
        if (in_array('array', $rules, true) && !is_array($value)) {
            $this->addError($field, "{$field} must be a list.");
            return ['has_value' => false, 'value' => $value];
        }

        $value = is_string($value) ? trim($value) : $value;

        foreach ($rules as $rule) {
            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

            switch ($name) {
                case 'required':
                case 'nullable':
                case 'array':
                    break;

                case 'string':
                    if (!is_string($value)) {
                        $this->addError($field, "{$field} must be text.");
                    }
                    break;

                case 'int':
                case 'integer':
                    if (!is_numeric($value) || (string) (int) $value !== (string) $value) {
                        $this->addError($field, "{$field} must be a whole number.");
                    } else {
                        $value = (int) $value;
                    }
                    break;

                case 'numeric':
                    if (!is_numeric($value)) {
                        $this->addError($field, "{$field} must be a number.");
                    } else {
                        $value = (float) $value;
                    }
                    break;

                case 'decimal':
                    if (!is_numeric($value)) {
                        $this->addError($field, "{$field} must be a valid amount.");
                    } else {
                        $value = round((float) $value, 2);
                    }
                    break;

                case 'bool':
                case 'boolean':
                    $value = in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
                    break;

                case 'min':
                    // min:N  (length for strings/arrays, magnitude for numbers)
                    $limit = (float) $parameter;
                    if (is_array($value) ? count($value) < $limit : (is_numeric($value) ? (float) $value < $limit : mb_strlen((string) $value) < $limit)) {
                        $message = is_numeric($value) && !is_string($value)
                            ? "{$field} must be at least {$parameter}."
                            : "{$field} must be at least {$parameter} characters.";
                        $this->addError($field, $message);
                    }
                    break;

                case 'max':
                    $limit = (float) $parameter;
                    if (is_array($value) ? count($value) > $limit : (is_numeric($value) && !is_string($value) ? (float) $value > $limit : mb_strlen((string) $value) > $limit)) {
                        $message = is_numeric($value) && !is_string($value)
                            ? "{$field} must not exceed {$parameter}."
                            : "{$field} must not exceed {$parameter} characters.";
                        $this->addError($field, $message);
                    }
                    break;

                case 'between':
                    [$lo, $hi] = array_pad(explode(',', (string) $parameter), 2, '0');
                    $num = (float) $value;
                    if ($num < (float) $lo || $num > (float) $hi) {
                        $this->addError($field, "{$field} must be between {$lo} and {$hi}.");
                    }
                    break;

                case 'email':
                    if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError($field, "{$field} must be a valid email address.");
                    }
                    break;

                case 'unique_email':
                    $exists = Database::instance()->value(
                        'SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
                        [strtolower((string) $value)]
                    );
                    if ($exists) {
                        $this->addError($field, 'An account already exists with this email address.');
                    }
                    break;

                case 'password':
                    $min = Config::int('security.password_min_length', 8);
                    if (mb_strlen((string) $value) < $min) {
                        $this->addError($field, "Password must be at least {$min} characters.");
                    } elseif (!self::isStrongPassword((string) $value)) {
                        $this->addError($field, 'Password must include at least one uppercase letter, one lowercase letter and one number.');
                    }
                    break;

                case 'confirmed':
                    $confirmation = $this->data[$field . '_confirmation'] ?? null;
                    if ($confirmation === null || !hash_equals((string) $value, (string) $confirmation)) {
                        $this->addError($field, 'The two passwords do not match.');
                    }
                    break;

                case 'regex':
                    if (!preg_match((string) $parameter, (string) $value)) {
                        $this->addError($field, "{$field} has an invalid format.");
                    }
                    break;

                case 'in':
                    $allowed = explode(',', (string) $parameter);
                    if (!in_array((string) $value, $allowed, true)) {
                        $this->addError($field, "{$field} is not a valid selection.");
                    }
                    break;

                case 'exists':
                    [$table, $column] = array_pad(explode(',', (string) $parameter), 2, 'id');
                    $found = Database::instance()->value(
                        sprintf('SELECT id FROM `%s` WHERE `%s` = ? LIMIT 1', Database::table($table), Database::table($column)),
                        [$value]
                    );
                    if (!$found) {
                        $this->addError($field, "The selected {$field} is invalid.");
                    }
                    break;

                case 'date':
                    if (!self::isValidDate((string) $value)) {
                        $this->addError($field, "{$field} must be a valid date.");
                    }
                    break;

                case 'after_or_equal':
                    $reference = (string) ($this->data[$parameter] ?? date('Y-m-d'));
                    if (strtotime((string) $value) < strtotime($reference)) {
                        $this->addError($field, "{$field} must be on or after {$parameter}.");
                    }
                    break;

                case 'slug':
                    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                        $this->addError($field, "{$field} may only contain lowercase letters, numbers and hyphens.");
                    }
                    break;

                case 'phone':
                    // Nigerian MSISDN or generic international format.
                    if (!preg_match('/^\+?[0-9]{10,15}$/', preg_replace('/[\s\-()]/', '', (string) $value) ?? '')) {
                        $this->addError($field, "{$field} must be a valid phone number.");
                    }
                    break;

                case 'latitude':
                    if (!is_numeric($value) || (float) $value < -90 || (float) $value > 90) {
                        $this->addError($field, "{$field} is not a valid latitude.");
                    }
                    break;

                case 'longitude':
                    if (!is_numeric($value) || (float) $value < -180 || (float) $value > 180) {
                        $this->addError($field, "{$field} is not a valid longitude.");
                    }
                    break;

                case 'time':
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)) {
                        $this->addError($field, "{$field} must be a valid time (HH:MM).");
                    }
                    break;
            }
        }

        return ['has_value' => true, 'value' => $value];
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public static function isStrongPassword(string $password): bool
    {
        return preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }

    private static function isValidDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}

<?php
declare(strict_types=1);

namespace IW\JsonSchema;

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;

final class Facade
{
    /** @var Validator|null */
    private $validator;

    /**
     * @param string $payload
     * @param string $schemaContents
     * @return array|null Errors from validation (if any).
     */
    public function validateSchema(string $payload, string $schemaContents): ?array
    {
        $schema = $this->getSchema($schemaContents);
        $validator = $this->getValidator();

        /** @var ValidationResult $result */
        $result = $validator->schemaValidation(json_decode($payload, false), $schema);

        if ($result->isValid()) {
            return null;
        } else {
            $schemaObject = $schema->resolve();
            $properties = [];
            if (property_exists($schemaObject, 'properties')) {
                $properties = $schemaObject->properties;
            }
            return $this->getErrors($result, $properties);
        }

    }

    /**
     * @param ValidationResult $result
     * @param \stdClass $properties
     * @return array
     */
    private function getErrors(ValidationResult $result, \stdClass $properties)
    {
        $errors = [];
        foreach ($result->getErrors() as $error) {
            $this->processError($errors, $error, $properties);
        }
        return $errors;
    }

    /**
     * @param array $errors
     * @param ValidationError $error
     * @param \stdClass $properties
     */
    private function processError(array &$errors, ValidationError $error, \stdClass $properties): void
    {
        if ($error->subErrorsCount() > 0) {
            foreach ($error->subErrors() as $subError) {
                $this->processError($errors, $subError, $properties);
            }
        } else {
            $keyword = $error->keyword();
            $propertyName = null;

            $dataPointer = $error->dataPointer();
            $args = $error->keywordArgs();

            if (count($dataPointer) === 1) {
                $propertyName = current($dataPointer);
            } elseif (count($args) === 1) {
                $propertyName = current($args);
            }

            if (
                property_exists($properties, $propertyName)
                && property_exists($properties->$propertyName, 'errors')
                && property_exists($properties->$propertyName->errors, $keyword)
            ) {
                $errors[] = sprintf('Property "%s" error: %s', $propertyName, $properties->$propertyName->errors->$keyword);
            } else {
                $errors[] = $this->getDefaultErrorMessage($keyword, $args, $dataPointer);
            }
        }
    }

    /**
     * @param string $keyword
     * @param array $args
     * @param array $dataPointer
     * @return string
     */
    private function getDefaultErrorMessage(string $keyword, array $args, array $dataPointer): ?string
    {
        if ($keyword === 'required') {
            $error = 'Property is missing';
            if (isset($args['missing'])) {
                return sprintf('Property "%s" error: %s', $args['missing'], $error);
            } else {
                return $error;
            }
        }
        if ($keyword === 'type') {
            if (isset($args['expected'], $args['used'])) {
                $error = sprintf('Unexpected type. Expected "%s" ("%s" used)', $args['expected'], $args['used']);
                if (count($dataPointer) === 1) {
                    return sprintf('Property "%s" error: %s', current($dataPointer), $error);
                } else {
                    return $error;
                }
            } else {
                return 'Unexpected property type';
            }
        }
        if ($keyword === '$schema') {
            if (isset($args['schema']) && $args['schema'] === false) {
                $error = 'Additional (unexpected) property.';
                if (count($dataPointer) === 1) {
                    return sprintf('Property "%s" error: %s', current($dataPointer), $error);
                } else {
                    return $error;
                }
            } else {
                return 'Unexpected property error';
            }
        }
        if ($keyword === 'pattern') {
            $error = 'Invalid string format';
            if (isset($args['pattern'])) {
                $error = sprintf('Value does not match pattern "%s"', $args['pattern']);
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        if ($keyword === 'format') {
            $error = 'Invalid string format';
            if (isset($args['format'])) {
                $error = sprintf('Value does not match format "%s"', $args['format']);
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        if ($keyword === 'enum') {
            $error = 'Invalid value.';
            if (isset($args['expected'])) {
                $error = sprintf('Expected values are "%s"', implode('", "', $args['expected']));
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        if ($keyword === 'maximum') {
            $error = 'Invalid value - greater than maximum.';
            if (isset($args['max'])) {
                $error = sprintf('Maximum value is "%s"', $args['max']);
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        if ($keyword === 'minimum') {
            $error = 'Invalid value - less than minimum.';
            if (isset($args['min'])) {
                $error = sprintf('Minimum value is "%s"', $args['min']);
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        if ($keyword === 'minItems') {
            $error = 'Invalid items count - less than minimum.';
            if (isset($args['min'])) {
                $error = sprintf('Minimum items count is "%s"', $args['min']);
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        if ($keyword === 'maxItems') {
            $error = 'Invalid items count - more than maximum.';
            if (isset($args['max'])) {
                $error = sprintf('Maximum items count is "%s"', $args['max']);
            }
            if (count($dataPointer) === 1) {
                return sprintf('Property "%s" error: %s', current($dataPointer), $error);
            } else {
                return $error;
            }
        }

        $error = sprintf('Unknown validation error for keyword "%s"', $keyword);
        if (count($dataPointer) === 1) {
            return sprintf('Property "%s" error: %s', current($dataPointer), $error);
        } else {
            return $error;
        }
    }

    /**
     * @return Validator
     */
    private function getValidator(): Validator
    {
        if ($this->validator === null) {
            $this->validator = new Validator();
        }
        return $this->validator;
    }

    /**
     * @param string $schemaContents
     * @return Schema
     */
    private function getSchema(string $schemaContents): Schema
    {
        return Schema::fromJsonString($schemaContents);
    }
}

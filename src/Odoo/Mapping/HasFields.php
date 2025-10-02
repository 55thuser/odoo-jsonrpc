<?php


namespace Obuchmann\OdooJsonRpc\Odoo\Mapping;


use Obuchmann\OdooJsonRpc\Attributes\BelongsTo;
use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Attributes\HasMany;
use Obuchmann\OdooJsonRpc\Attributes\Key;
use Obuchmann\OdooJsonRpc\Attributes\KeyName;
use Obuchmann\OdooJsonRpc\Odoo\Casts\CastHandler;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;
use stdClass;

trait HasFields
{
    protected static function fieldNames(): array
    {
        $fieldNames = [];

        $reflectionClass = new \ReflectionClass(static::class);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            // Field attributes
            $attributes = $property->getAttributes(Field::class);
            foreach ($attributes as $attribute) {
                $fieldNames[] = $attribute->newInstance()->name ?? $property->name;
            }
            
            // HasMany relationships
            $hasManyAttributes = $property->getAttributes(HasMany::class);
            foreach ($hasManyAttributes as $attribute) {
                $fieldNames[] = $attribute->newInstance()->name;
            }
            
            // BelongsTo relationships
            $belongsToAttributes = $property->getAttributes(BelongsTo::class);
            foreach ($belongsToAttributes as $attribute) {
                $fieldNames[] = $attribute->newInstance()->name;
            }
        }
        return $fieldNames;
    }

    public static function hydrate(object $response): static
    {
        $castsExists = CastHandler::hasCasts();

        $reflectionClass = new \ReflectionClass(static::class);
        $properties = $reflectionClass->getProperties();

        $instance = static::newInstance();
        $instance->id = $response->id ?? null; // Id is always present

        foreach ($properties as $property) {
            $isKey = !empty($property->getAttributes(Key::class));
            $isKeyName = !empty($property->getAttributes(KeyName::class));
            
            // Handle Field attributes
            $attributes = $property->getAttributes(Field::class);
            foreach ($attributes as $attribute) {
                $field = $attribute->newInstance()->name ?? $property->name;
                if (isset($response->{$field})) {
                    if ($isKey) {
                        $value = $response->{$field}[0] ?? null;
                    } elseif ($isKeyName) {
                        $value = $response->{$field}[1] ?? null;
                    } else {
                        $value = $response->{$field};
                    }
                    $instance->{$property->name} = $castsExists ? CastHandler::cast($property, $value) : $value;
                }
            }
            
            // Handle HasMany relationships
            $hasManyAttributes = $property->getAttributes(HasMany::class);
            foreach ($hasManyAttributes as $attribute) {
                $hasManyInstance = $attribute->newInstance();
                $field = $hasManyInstance->name;
                if (isset($response->{$field})) {
                    $relatedIds = $response->{$field};
                    if (is_array($relatedIds) && !empty($relatedIds)) {
                        // Hydrate the related models
                        $relatedClass = $hasManyInstance->class;
                        $relatedModels = $relatedClass::read($relatedIds);
                        $instance->{$property->name} = $relatedModels;
                    } else {
                        $instance->{$property->name} = [];
                    }
                } else {
                    $instance->{$property->name} = [];
                }
            }
            
            // Handle BelongsTo relationships
            $belongsToAttributes = $property->getAttributes(BelongsTo::class);
            foreach ($belongsToAttributes as $attribute) {
                $belongsToInstance = $attribute->newInstance();
                $field = $belongsToInstance->name;
                if (isset($response->{$field})) {
                    $relatedData = $response->{$field};
                    if ($relatedData !== false && $relatedData !== null) {
                        // Odoo returns [id, name] for many2one fields
                        $relatedId = is_array($relatedData) ? ($relatedData[0] ?? null) : $relatedData;
                        if ($relatedId !== null) {
                            $relatedClass = $belongsToInstance->class;
                            $relatedModel = $relatedClass::find($relatedId);
                            $instance->{$property->name} = $relatedModel;
                        } else {
                            $instance->{$property->name} = null;
                        }
                    } else {
                        $instance->{$property->name} = null;
                    }
                }
            }
        }

        return $instance;
    }

    public static function dehydrate(OdooModel $model): object
    {
        $castsExists = CastHandler::hasCasts();
        $item = new stdClass();

        $reflectionClass = new \ReflectionClass(static::class);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Field::class);

            foreach ($attributes as $attribute) {
                $field = $attribute->newInstance()->name ?? $property->name;
                if ($property->isInitialized($model)) {
                    $item->{$field} = $castsExists ? CastHandler::uncast($property, $model->{$property->name}) : $model->{$property->name};
                }
            }

            // Handle HasMany relationships
            $hasManyRelations = $property->getAttributes(HasMany::class);
            foreach ($hasManyRelations as $attribute) {
                $field = $attribute->newInstance()->name ?? $property->name;
                if ($property->isInitialized($model)) {

                    $values = $model->{$property->name};
                    if (null === $values)
                        continue;

                    if (self::isIdArray($values)) {
                        $item->{$field} = [[6, 0, $values]]; // Syncs given Ids
                    } else {
                        $commands = [];
                        foreach ($values as $value) {
                            if ($value instanceof OdooModel) {
                                if ($value->exists()) {
                                    $commands[] = [1, $value->id, $value->dehydrate($value)]; // Update related
                                } else {
                                    $commands[] = [0, 0, $value->dehydrate($value)]; // Create related
                                }
                            }
                        }
                        $item->{$field} = $commands;
                    }

                }
            }
            
            // Handle BelongsTo relationships
            $belongsToRelations = $property->getAttributes(BelongsTo::class);
            foreach ($belongsToRelations as $attribute) {
                $field = $attribute->newInstance()->name ?? $property->name;
                if ($property->isInitialized($model)) {
                    $value = $model->{$property->name};
                    if ($value instanceof OdooModel && $value->exists()) {
                        $item->{$field} = $value->id;
                    } elseif (is_int($value)) {
                        $item->{$field} = $value;
                    } elseif ($value === null) {
                        $item->{$field} = false; // Odoo expects false for empty many2one
                    }
                }
            }

        }

        return $item;
    }

    protected static function newInstance()
    {
        return new static();
    }

    private static function isIdArray(array $arr)
    {
        foreach ($arr as $item) {
            if (!is_int($item))
                return false;
        }
        return true;
    }
}

<?php

namespace Lnext\EndlessOptions\Traits;

use Illuminate\Database\Eloquent\Model;

/*
 |===========================================================================|===========================================================================|
 |    To create mixed options                                                |     To create boolean options                                             |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |  create table in DB for option                                            |  create table in DB for toggle option                                     |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |                                                                           |                                                                           |
 |   Schema::create('{nameOptionTable}', function (Blueprint $table) {       |   Schema::create('{nameOptionTable}', function (Blueprint $table) {       |
 |       $table->id();                                                       |       $table->id();                                                       |
 |       $table->unsignedInteger('{nameField}');                             |       $table->unsignedInteger('{nameField}');                             |
 |       $table->string('name');                                             |       $table->string('name');                                             |
 |       $table->string('value')->nullable();                                |       $table->boolean('value')->default(1)->nullable();                   |
 |       $table->foreign('{NameField}')->references('id')->on('{nameTable}');|       $table->foreign('{NameField}')->references('id')->on('{nameTable}');|
 |   });                                                                     |   });                                                                     |
 |                                                                           |                                                                           |
 |   Create model {nameOptionTable} for this table                           |   Create model {nameOptionTable} for this table                           |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |  add properties in model                                                  |  add properties in model                                                  |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |                                                                           |                                                                           |
 |   array $optionFields = [];                                               |   array $toggleFields = [];                                               |
 |     *in it, list the fields that you want to use for the option.          |     *in it, list the fields that you want to use for the Boolean option.  |
 |-------------------------------------------------------------------------------------------------------------------------------------------------------|
 |                                                                  array $optionCasts = [                                                               |
 |                                                                         '{nameField}' => 'array',                                                     |
 |                                                                         '{nameField}' => 'boolean',                                                   |
 |                                                                         '{nameField}' => 'int',                                                       |
 |                                                                    ];                                                                                 |
 |                                                                     *it sets the types to be reduced to.                                              |
 |                                                                    **supported types                                                                  |
 |                                                                   ***the property is optional                                                         |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |  add a relation in model                                                  |  add a relation in model                                                  |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |    public function options(): HasMany                                     |    public function booleanOptions(): HasMany                              |
 |    {                                                                      |    {                                                                      |
 |         return $this->hasMany({NameModelOptionTable}::class);             |         return $this->hasMany({NameModelOptionTable}::class);             |
 |    }                                                                      |    }                                                                      |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |  add properties for permanent loading                                     |  add properties for permanent loading                                     |
 |---------------------------------------------------------------------------|---------------------------------------------------------------------------|
 |   protected $with = [                                                     |   protected $with = [                                                     |
 |       'options',                                                          |       'booleanOptions',                                                   |
 |   ];                                                                      |   ];                                                                      |
 |===========================================================================|===========================================================================|
*/

trait EndlessOptions
{
    private array $afterCreation = [];

    public static function boot(): void
    {
        parent::boot();
        self::created(function ($model) {
            foreach ($model->afterCreation as $action => $dates) {
                foreach ($dates as $date) {
                    $model->$action()->create($date);
                }
            }
        });
    }

    public function getFillable(): array
    {
        $fillable = $this->fillable;
        if (isset($this->toggleFields)) {
            $fillable = array_merge($fillable, $this->toggleFields);
        }
        if (isset($this->optionFields)) {
            $fillable = array_merge($fillable, $this->optionFields);
        }
        return $fillable;
    }

    public function getAttribute($key)
    {
        if ($this->checkFieldOption('mixed', $key)) {
            return $this->getOption($key);
        } elseif ($this->checkFieldOption('toggle', $key)) {
            return $this->getBooleanOption($key);
        } else {
            return parent::getAttribute($key);
        }
    }

    public function setAttribute($key, $value)
    {
        $mixed = $this->checkFieldOption('mixed', $key);
        $toggle = $this->checkFieldOption('toggle', $key);
        if ($mixed || $toggle) {
            if ($this->exists) {
                if ($mixed) {
                    $this->setOption($key, $value);
                } elseif ($toggle) {
                    $this->setBooleanOption($key, $value);
                }
            } else {
                $keyForAfter = $toggle ? 'booleanOptions' : 'options';
                $this->afterCreation[$keyForAfter][] = ['name' => $key, 'value' => $value];
            }
            return $this;
        } else {
            return parent::setAttribute($key, $value);
        }
    }

    public function getToggleFields()
    {
        return $this->toggleFields ?? [];
    }

    public function getOptionFields()
    {
        return $this->optionFields ?? [];
    }

    public function getOptionCasts()
    {
        return $this->optionCasts ?? [];
    }

    // --------- PRIVATE FUNCTION  --------------------------------------

    private function getBooleanOption($field): bool|null
    {
        return $this->booleanOptions->where('name', $field)->first()?->value;
    }

    private function getOption($field): mixed
    {
        $value = $this->options->where('name', $field)->first()?->value;

        if (isset($this->optionCasts) && !is_null($value)) {
            $cast = $this->optionCasts[$field] ?? null;
            if (!is_null($cast)) {
                $value = match ($cast) {
                    'array' => json_decode($value, true),
                    'boolean' => (bool) $value,
                    'int' => (int) $value,
                    default => $value,
                };
            }
        }

        return $value;
    }

    private function setBooleanOption($field, bool $value): void
    {
        if ($option = $this->booleanOptions->where('name', $field)->first()) {
           $option->update(['value' => $value]);
        } else {
           $this->booleanOptions()->create(['name' => $field, 'value' => $value]);
        }
    }

    private function setOption($field, $value): void
    {
        if (isset($this->optionCasts)) {
            $cast = $this->optionCasts[$field] ?? null;
            if (!is_null($cast)) {
                $value = match ($cast) {
                    'array' => json_encode($value),
                    'boolean' => (bool) $value,
                    'int' => (int) $value,
                    default => $value,
                };
            }
        }

        if ($option = $this->options->where('name', $field)->first()) {
            $option->update(['value' => $value]);
        } else {
            $this->options()->create(['name' => $field, 'value' => $value]);
        }
    }

    private function checkFieldOption($case, $key): bool
    {
        return match ($case) {
            'toggle' => isset($this->toggleFields) && in_array($key, $this->toggleFields) && method_exists($this, 'booleanOptions'),
            'mixed' => isset($this->optionFields) && in_array($key, $this->optionFields) && method_exists($this, 'options'),
            default => false
        };
    }

}

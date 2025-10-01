<?php

namespace Lnext\EndlessOptions\Traits;

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
 |   public array $optionFields = [];                                        |   public array $toggleFields = [];                                        |
 |     *in it, list the fields that you want to use for the option.          |     *in it, list the fields that you want to use for the Boolean option.  |
 |-------------------------------------------------------------------------------------------------------------------------------------------------------|
 |   public array $optionCasts = [                                           |                                                                           |
 |     '{nameField}' => 'array',                                             |                                                                           |
 |     '{nameField}' => 'boolean',                                           |                                                                           |
 |     '{nameField}' => 'int',                                               |                                                                           |
 |     '{nameField}' => 'date',                                              |                                                                           |
 |   ];                                                                      |                                                                           |
 |      *it sets the types to be reduced to.                                 |                                                                           |
 |      **supported types                                                    |                                                                           |
 |      ***the property is optional                                          |                                                                           |
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

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait EndlessOptions
{
    private array $afterData = [];

    protected static function booted(): void
    {
        self::saved(function ($model) {
            foreach ($model->afterData as $action => $data) {
                $isConvert = $action == 'options' && isset($model->optionCasts);
                foreach ($data as $datum) {
                    if ($isConvert) {
                        $field = $datum['name'];
                        $value = $datum['value'];
                        $cast = $model->optionCasts[$field] ?? null;
                        if (!is_null($cast)) {
                            $value = $model->convertingField($cast, $value);
                        }
                        $datum['value'] = $value;
                    }
                    $model->$action()->create($datum);
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
                $this->afterData[$keyForAfter][] = ['name' => $key, 'value' => $value];
            }
            return $this;
        } else {
            return parent::setAttribute($key, $value);
        }
    }

    public function getToggleFields(): array
    {
        return $this->toggleFields ?? [];
    }

    public function getOptionFields(): array
    {
        return $this->optionFields ?? [];
    }

    public function getOptionCasts(): array
    {
        return $this->optionCasts ?? [];
    }

    public function scopeWhereToggle(Builder $builder, string $name, bool $value = null): Builder
    {
        return
            $builder->whereHas('booleanOptions', function (Builder $q) use ($name, $value) {
                $q->where('name', $name)
                    ->where('value', $value);
            });
    }

    public function scopeWhereOptions(Builder $builder, string $name, bool|int|string $ov, bool|int|string $v): Builder
    {
        if (func_num_args() === 3) {
            $operator = '=';
            $value = $ov;
        } elseif (!in_array($ov, $this->allowedOperators())) {
            return $builder;
        } else {
            $operator = $ov;
            $value = $v;
        }
        return
            $builder->whereHas('options', function (Builder $q) use ($name, $operator, $value) {
                $q->where('name', $name)
                    ->where('value', $operator , $value);
            });
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
                    'date' => Carbon::parse($value),
                    default => $value,
                };
            }
        }

        return $value;
    }

    private function setBooleanOption($field, bool $value): void
    {
        $fromValue = $this->$field;
        if ($option = $this->booleanOptions->where('name', $field)->first()) {
            $option->update(['value' => $value]);
        } else {
            $this->afterData['booleanOptions'][] = ['name' => $field, 'value' => $value];
            $this->updated_at = now();
        }
        if ($fromValue !== $value) {
            $this->changes = array_merge($this->changes, [$field => $fromValue]);
        }
    }

    private function setOption($field, $value): void
    {
        if (isset($this->optionCasts)) {
            $cast = $this->optionCasts[$field] ?? null;
            if (!is_null($cast)) {
                $value = $this->convertingField($cast, $value);
            }
        }
        $fromValue = $this->$field;
        if ($option = $this->options->where('name', $field)->first()) {
            $option->update(['value' => $value]);
        } else {
            $this->afterData['options'][] = ['name' => $field, 'value' => $value];
            $this->updated_at = now();
        }
        if ($fromValue !== $value) {
            $this->changes = array_merge($this->changes, [$field => $fromValue]);
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

    private function allowedOperators(): array
    {
        return [
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            'like', 'like binary', 'not like', 'ilike',
            '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
            'rlike', 'not rlike', 'regexp', 'not regexp',
            '~', '~*', '!~', '!~*', 'similar to',
            'not similar to', 'not ilike', '~~*', '!~~*',
        ];
    }

    private function convertingField(string $cast, mixed $value): mixed
    {
        return match ($cast) {
            'array' => json_encode($value),
            'boolean' => (bool) $value,
            'int' => (int) $value,
            default => $value,
        };
    }

}

<?php

namespace Lnext\EndlessOptions\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TransferringOptions extends Command
{
    private Model $model;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'endlessOption:transferring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'moving from endless options to the table and back';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {

        $inputName = $this->ask('Enter the model name');
        $model = 'App\Models\\'.str($inputName)->ucfirst()->value();
        if (!class_exists($model)) {
            $this->error('There is no such model in the system.');
            $this->newLine();
            return;
        }
        $this->model = new $model;
        if (!$this->checkTrait()) {
            $this->error('The trait EndlessOptions is not enabled in this model.');
            $this->newLine();
            return;
        }
        $select = $this->choice(
            'direction of movement?  ',
            ['from entity to options (out)', 'from options to entity (in)', '~cancel~']
        );
        if ($select == '~cancel~') {
            return;
        }
        $action = str($select)->between('(', ')')->value();
        $allFields = $this->model->getFillable();
        $optionFields = array_merge($this->model->getToggleFields(), $this->model->getOptionFields());
        $FillableFields = array_diff($allFields, $optionFields);
        $valueSelect = match ($action) {
            'out' => $FillableFields,
            'in' => array_diff($optionFields, $FillableFields),
            default => []
        };
        if (empty($select)) {
            $this->error('Something went wrong!');
            return;
        }
        $valueSelect[] = '~cancel~';
        $field = $this->choice(
            'direction of movement?  ',
            $valueSelect
        );
        if ($field == '~cancel~') {
            return;
        }
        $response = match ($action) {
            'out' => $this->mToO($field),
            'in' => $this->oToM($field)
        };

        if ($response == 1) {
            $this->info('transfer completed!!');
            $this->question('What should I do next?');
            $this->comment("You need to remove the field $field from the ".$this->model::class.'::$fillable and it to '
                .$this->model::class.'::$optionFields or '.$this->model::class.'::$toggleFields. if necessary, then in the '
                .$this->model::class.'::$optionCasts. Depending on the settings.');
        } elseif ($response == 2) {
            $this->info('transfer completed!!');
            $this->question('What should I do next?');
            $this->comment("You need to remove the field $field from the ".$this->model::class.'::$optionFields or '
                .$this->model::class.'::$toggleFields. if there is then from '.$this->model::class.'::$optionCasts. Depending on the settings. And add it to '
                .$this->model::class.'::$fillable');
        }
    }


    //----------------------------------
    private function mToO(string $field): int
    {
        $table = $this->model->getTable();
        if (!Schema::hasColumn($table, $field)) {
            $this->error("The model does not have a $field column.");
            $this->comment("You may need to remove the field names from the ".$this->model::class.'::$fillable and add it to '
                .$this->model::class.'::$optionFields or '.$this->model::class.'::$toggleFields. Depending on the settings. To exclude the wrong option in select.');
            $this->newLine();
            return 0;
        }
        $relation = Schema::getColumnType($table, $field) == 'tinyint' ? 'booleanOptions' : 'options';
        if (!method_exists($this->model::class, $relation)) {
            $this->alert("You need to add a $relation relationship to the model ".$this->model::class);
            return 0;
        }
        /** @var Model $modelClass */
        $modelClass = $this->model::class;
        foreach ($modelClass::all() as $item) {
            $value = $item->$field;
            $item->$relation()->create([
                'name' => $field,
                'value' => $value
            ]);
        }
        Schema::table($table, function (Blueprint $table) use ($field) {
            $table->dropColumn($field);
        });
        return 1;
    }

    private function oToM(string $field): int
    {
        $table = $this->model->getTable();
        if (Schema::hasColumn($table, $field)) {
            $this->error("Such a column $field exists");
            $this->comment("You may need to remove the field $field from the ".$this->model::class.'::$optionFields or '
                .$this->model::class.'::$toggleFields. if there is then from '.$this->model::class.'::$optionCasts. Depending on the settings. And add it to '
                .$this->model::class.'::$fillable. To exclude the wrong option in select.');
            $this->newLine();
            return 0;
        }
        $typeField = match (true) {
            in_array($field, $this->model->getToggleFields()) => 'boolean',
            in_array($field, $this->model->getOptionFields()) => 'string',
        };
        if (isset($this->model->optionCasts[$field])) {
            $typeField = match ($this->model->optionCasts[$field]) {
                'array' => 'json',
                'int' => 'integer',
                'boolean' => 'boolean',
                default => 'string'
            };
        }
        $relation = match (true) {
            in_array($field, $this->model->getToggleFields()) => 'booleanOptions',
            in_array($field, $this->model->getOptionFields()) => 'options',
        };
        Schema::table($table, function (Blueprint $table) use ($typeField, $field) {
            $table->$typeField($field)->nullable();
        });
        /** @var Model $modelClass */
        $modelClass = $this->model::class;
        foreach ($modelClass::all() as $item) {
            $value = $item->$field;
            DB::table($table)->where('id', $item->id)->update([$field => $value]);
            $item->$relation()->where('name', $field)->delete();
        }
        return 2;
    }

    private function checkTrait(): bool
    {
        foreach (class_uses($this->model::class) as $use) {
            if (str($use)->afterLast('\\')->value() == 'EndlessOptions') {
                return true;
            }
        }
        return false;
    }
}

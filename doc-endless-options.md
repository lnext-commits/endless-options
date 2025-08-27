# Endless options fot model

### what needs to be done for implementation

To get started, you need to connect the trait in the model
```php
 use EndlessOptions
```
and create the necessary relationships and tables in the database as described below

<hr style="height:4px; width: 485px;">
<table>
    <tr>
        <th>To create mixed options</th>
    </tr>
    <tr>
        <td>create table in DB for option</td>
    </tr>
    <tr>
        <td>
            Schema::create('{nameOptionTable}', function (Blueprint $table) { <br>
            &nbsp;&nbsp; $table->id();<br>
            &nbsp;&nbsp; $table->unsignedBigInteger('nameField');<br>
            &nbsp;&nbsp; $table->string('name');   <br>
            &nbsp;&nbsp; $table->string('value')->nullable(); <br>
            &nbsp;&nbsp; $table->foreign('NameField')->references('id')->on('nameTable'); <br>
            });<br><br>
            Create model {nameOptionTable} for this table   
        </td>
    </tr>
    <tr>
        <td>add properties in model</td>
    </tr>
    <tr>
        <td>
            private array $optionFields = [ ]; <br>
            &nbsp;&nbsp; <i>*in it, list the fields that you want to use for the option.</i>  <br>
            <br>
            private array $optionCasts = [ <br>
            &nbsp;&nbsp; '{nameField}' => 'array', <br>
            &nbsp;&nbsp; '{nameField}' => 'boolean', <br>
            &nbsp;&nbsp; '{nameField}' => 'int', <br>
            &nbsp;&nbsp; '{nameField}' => 'date', <br>
            ];  <br>
            &nbsp; <i>*it sets the types to be reduced to.</i> <br>
            &nbsp; <i>**supported types</i> <br>
            &nbsp; <i>***the property is optional</i>  <br>
        </td>
    </tr>
    <tr>
        <td>add a relation in model</td>
    </tr>
    <tr>
        <td>
            public function options(): HasMany <br>
            { <br>
            &nbsp;&nbsp; return $this->hasMany({NameModelOptionTable}::class);  <br>
            } <br>
        </td>
    </tr> 
    <tr>
        <td>add properties for permanent loading </td>
    </tr>
    <tr>
        <td>
            protected $with = [  <br>
            &nbsp;&nbsp; 'options', <br>
            ];
        </td>
    </tr>
</table>

<hr style="height:4px; width: 485px;">

<table>
    <tr>
        <th>To create boolean options </th>
    </tr>
    <tr>
        <td>create table in DB for toggle option</td>
    </tr>
    <tr>
        <td>
            Schema::create('{nameOptionTable}', function (Blueprint $table) { <br>
            &nbsp;&nbsp; $table->id();<br>
            &nbsp;&nbsp; $table->unsignedBigInteger('nameField');<br>
            &nbsp;&nbsp; $table->string('name');   <br>
            &nbsp;&nbsp; $table->boolean('value')->default(1)->nullable();  <br>
            &nbsp;&nbsp; $table->foreign('NameField')->references('id')->on('nameTable'); <br>
            });<br><br>
            Create model {nameOptionTable} for this table   
        </td>
    </tr>
    <tr>
        <td>add properties in model</td>
    </tr>
    <tr>
        <td>
            private array $toggleFields = [ ]; <br>
            &nbsp;&nbsp; <i>*in it, list the fields that you want to use for the option.</i>  <br>
        </td>
    </tr>
    <tr>
        <td>add a relation in model</td>
    </tr>
    <tr>
        <td>
            public function booleanOptions(): HasMany <br>
            { <br>
            &nbsp;&nbsp; return $this->hasMany({NameModelOptionTable}::class);  <br>
            } <br>
        </td>
    </tr> 
    <tr>
        <td>add properties for permanent loading </td>
    </tr>
    <tr>
        <td>
            protected $with = [  <br>
            &nbsp;&nbsp; 'booleanOptions', <br>
            ];
        </td>
    </tr>
</table>
<hr style="height:4px; width: 700px;">

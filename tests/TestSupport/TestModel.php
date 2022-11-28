<?php

namespace Hellotess\Storable\Test\TestSupport;

use Illuminate\Database\Eloquent\Model;
use Hellotess\Storable\HasTranslations;

class TestModel extends Model
{
    use HasTranslations;

    protected $table = 'test_models';

    protected $guarded = [];
    public $timestamps = false;

    public $storable = ['name', 'other_field', 'field_with_mutator'];

    public function setFieldWithMutatorAttribute($value)
    {
        $this->attributes['field_with_mutator'] = $value;
    }
}

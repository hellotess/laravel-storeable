<?php

namespace Hellotess\Storable\Test\TestSupport;

use Hellotess\Storable\HasTranslations;
use Illuminate\Database\Eloquent\Model;

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

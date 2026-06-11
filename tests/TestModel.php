<?php

namespace Tsrgtm\FilamentMediaLibrary\Tests;

use Illuminate\Database\Eloquent\Model;
use Tsrgtm\FilamentMediaLibrary\Traits\HasMedia;

class TestModel extends Model
{
    use HasMedia;

    protected $table = 'test_models';
    protected $guarded = [];

    public function mediaCollections(): array
    {
        return [
            'avatar' => [
                'single' => true,
            ],
            'gallery' => [
                'multiple' => true,
            ]
        ];
    }
}

<?php

namespace Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model {
  
  use SoftDeletes;

  public $incrementing = false;

  public function folder() {
    return $this->belongsTo('Api\Models\Folder');
  }

}

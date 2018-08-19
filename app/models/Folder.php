<?php

namespace Api\Models;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model {

  public $incrementing = false;

  public function files() {
    return $this->hasMany('Api\Models\File');
  }

}

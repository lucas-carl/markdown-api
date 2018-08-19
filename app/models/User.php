<?php

namespace Api\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {

  public $incrementing = false;

  public function folders() {
    return $this->hasMany('Api\Models\Folder');
  }

  public function files() {
    return $this->hasMany('Api\Models\File');
  }

}

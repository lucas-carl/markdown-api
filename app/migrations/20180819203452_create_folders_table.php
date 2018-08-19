<?php

use Api\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFoldersTable extends Migration {

  public function up() {
    $this->schema->create('folders', function(Blueprint $table) {
      $table->string('id')->unique();
      $table->string('user_id');
      $table->string('title');
      $table->timestamps();
    });
  }

  public function down() {
    $this->schema->drop('folders');
  }

}

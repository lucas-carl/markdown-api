<?php

use Api\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFilesTable extends Migration {

  public function up() {
    $this->schema->create('files', function(Blueprint $table) {
      $table->string('id')->unique();
      $table->string('user_id');
      $table->string('folder_id')->nullable();
      $table->string('title');
      $table->string('content');
      $table->softDeletes();
      $table->timestamps();
    });
  }

  public function down() {
    $this->schema->drop('files');
  }

}

<?php

use Api\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration {

  public function up() {
    $this->schema->create('users', function(Blueprint $table) {
      $table->string('id')->unique();
      $table->string('email')->unique();
      $table->string('password');
      $table->string('token')->nullable();
      $table->dateTime('token_expire')->nullable();
      $table->timestamps();
    });
  }

  public function down() {
    $this->schema->drop('users');
  }

}

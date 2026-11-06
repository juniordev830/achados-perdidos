<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('found_items',function(Blueprint $table){
   $table->id();$table->string('category',50);$table->string('public_description',160);
   $table->date('found_at');$table->timestamp('returned_at')->nullable();$table->string('return_reference',80)->nullable();
   $table->timestamps();$table->index(['returned_at','category','found_at']);
  });
 }
 public function down():void {Schema::dropIfExists('found_items');}
};

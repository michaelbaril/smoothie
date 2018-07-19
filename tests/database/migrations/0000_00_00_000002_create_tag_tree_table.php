<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTagTreeTable extends Migration
{
    protected $mainTableName = 'tags';
    protected $closureTableName = 'tag_tree';
    protected $mainTableKey = 'id';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->closureTableName, function (Blueprint $table) {
            $table->unsignedInteger('ancestor_id');
            $table->unsignedInteger('descendant_id');
            $table->unsignedSmallInteger('depth');

            $table->unique(['ancestor_id', 'descendant_id']);
            $table->unique(['descendant_id', 'depth']);
            $table->index('depth');

            $table->foreign('ancestor_id')->references($this->mainTableKey)->on($this->mainTableName);
            $table->foreign('descendant_id')->references($this->mainTableKey)->on($this->mainTableName);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->closureTableName);
    }
}

<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
class FoundItemsTest extends TestCase {
 use RefreshDatabase;
 public function test_returned_object_leaves_open_list_but_keeps_history():void {
  $create=$this->postJson('/api/items',['category'=>'livros','public_description'=>'Caderno simples','found_at'=>'2026-11-05']);
  $create->assertCreated();$id=$create->json('id');
  $this->getJson('/api/items?category=livros')->assertOk()->assertJsonCount(1);
  $this->postJson("/api/items/$id/return",['return_reference'=>'protocolo-local-001'])->assertOk();
  $this->getJson('/api/items')->assertOk()->assertJsonCount(0);
  $this->getJson("/api/items/$id")->assertOk()->assertJsonPath('return_reference','protocolo-local-001');
  $this->postJson("/api/items/$id/return",['return_reference'=>'outra'])->assertStatus(409);
 }
 public function test_invalid_record_is_rejected():void {
  $this->postJson('/api/items',['category'=>'', 'public_description'=>'', 'found_at'=>'2100-01-01'])->assertUnprocessable();
 }
}

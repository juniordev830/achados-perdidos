<?php
namespace App\Http\Controllers;
use App\Models\FoundItem;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
class FoundItemsController extends Controller {
 public function index(Request $request){
  $data=$request->validate(['category'=>'sometimes|string|max:50','from'=>'sometimes|date','to'=>'sometimes|date']);
  $q=FoundItem::query()->whereNull('returned_at');
  if(!empty($data['category']))$q->where('category',$data['category']);
  if(!empty($data['from']))$q->whereDate('found_at','>=',$data['from']);
  if(!empty($data['to']))$q->whereDate('found_at','<=',$data['to']);
  return response()->json($q->orderBy('found_at','desc')->get(['id','category','public_description','found_at']));
 }
 public function store(Request $request){
  $data=$request->validate(['category'=>'required|string|max:50','public_description'=>'required|string|max:160','found_at'=>'required|date|before_or_equal:today']);
  $item=FoundItem::create($data);
  return response()->json($item->only(['id','category','public_description','found_at']),201);
 }
 public function show(FoundItem $item){
  return response()->json($item->only(['id','category','public_description','found_at','returned_at']));
 }
 public function markReturned(Request $request,FoundItem $item){
  $data=$request->validate(['return_reference'=>'required|string|max:80']);
  if($item->returned_at!==null)return response()->json(['error'=>'Objeto já devolvido'],409);
  $item->update(['returned_at'=>now(),'return_reference'=>$data['return_reference']]);
  return response()->json(['id'=>$item->id,'returned'=>true,'returned_at'=>$item->returned_at,'return_reference'=>$item->return_reference]);
 }
}

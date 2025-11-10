<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class ConversationController extends Controller
{
public function index(Request $request): JsonResponse
{
// TODO: داده واقعی از DB. فعلاً ماک برای بالا آمدن فرانت‌اند
$data = [
[
'id' => 1,
'type' => 'direct',
'partner_id' => 2,
'last_message' => 'Hi there!',
'updated_at' => now()->toISOString(),
],
[
'id' => 2,
'type' => 'group',
'room_id' => 1,
'last_message' => 'Welcome to General',
'updated_at' => now()->toISOString(),
],
];


return response()->json($data);
}
}
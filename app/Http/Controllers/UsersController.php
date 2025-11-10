<?php
// app/Http/Controllers/UsersController.php
namespace App\Http\Controllers;

use App\Models\User;

class UsersController extends Controller
{
    public function index()
    {
        $meId = auth()->id();
        $users = User::when($meId, fn($q) => $q->where('id', '!=', $meId))
            ->select('id', 'name as first_name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($users, 200);
    }
}

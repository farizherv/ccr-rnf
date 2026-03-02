<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        // ✅ pakai get() biar mudah di-group & tidak “hilang”
        $users = User::orderBy('role')->orderBy('username')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        // Admin tidak boleh bikin director
        if (auth()->user()->role === UserRole::Admin && $request->role === 'director') {
            abort(403);
        }

        $data = $request->validate([
            'name'     => ['required','string','max:120'],
            'username' => ['required','string','max:60', Rule::unique('users','username')],
            'role'     => ['required', Rule::in(UserRole::values())],
            'password' => ['required','string','min:8','max:255','regex:/[A-Z]/','regex:/[0-9]/'],
        ], [
            'password.min'   => 'Password minimal 8 karakter.',
            'password.regex'  => 'Password harus mengandung minimal 1 huruf besar dan 1 angka.',
        ]);

        // ✅ email dummy aman & tidak bentrok
        $safe = preg_replace('/[^a-zA-Z0-9]/', '_', $data['username']);
        $hash = substr(md5($data['username']), 0, 8);
        $email = strtolower($safe) . '.' . $hash . '@local.test';

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $email,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil dibuat.');
    }

    public function edit(User $user)
    {
        if (auth()->user()->role === UserRole::Admin && $user->role === UserRole::Director) {
            abort(403);
        }

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        if (auth()->user()->role === UserRole::Admin) {
            if ($user->role === UserRole::Director) abort(403);
            if ($request->role === 'director') abort(403);
        }

        $data = $request->validate([
            'name'     => ['required','string','max:120'],
            'username' => ['required','string','max:60', Rule::unique('users','username')->ignore($user->id)],
            'role'     => ['required', Rule::in(UserRole::values())],
            'password' => ['nullable','string','min:8','max:255','regex:/[A-Z]/','regex:/[0-9]/'],
        ]);

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->role = $data['role'];

        $safe = preg_replace('/[^a-zA-Z0-9]/', '_', $data['username']);
        $hash = substr(md5($data['username']), 0, 8);
        $user->email = strtolower($safe) . '.' . $hash . '@local.test';

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diupdate.');
    }

    public function destroy(User $user)
    {   
    // hanya admin yang boleh hapus (kalau kamu punya role system)
    if (auth()->user()->role !== UserRole::Admin) {
        return back()->with('error', 'Anda tidak punya akses untuk menghapus user.');
    }

    // tidak boleh hapus diri sendiri
    if ($user->id === auth()->id()) {
        return back()->with('error', 'Tidak bisa menghapus akun yang sedang login.');
    }

    // tidak boleh hapus admin terakhir
    $isAdmin = ($user->role === UserRole::Admin);
    if ($isAdmin) {
        $adminCount = User::where('role', 'admin')->count();
        if ($adminCount <= 1) {
            return back()->with('error', 'Tidak bisa menghapus admin terakhir.');
        }
    }

    $user->delete();

    return back()->with('success', 'User berhasil dihapus.');
    }
    
}

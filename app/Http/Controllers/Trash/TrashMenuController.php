<?php

namespace App\Http\Controllers\Trash;

use App\Http\Controllers\Controller;

class TrashMenuController extends Controller
{
    public function index()
    {
        return view('trash.menu');
    }
}

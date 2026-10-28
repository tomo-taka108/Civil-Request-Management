<?php

namespace App\Http\Controllers;

use App\Support\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 進捗ダッシュボード（要件定義書 §2.7 / mockup/dashboard.html）。
 *
 * ログイン後のトップ画面（home → dashboard.index）。集計ロジックは
 * DashboardStats に集約し、ここではロールを渡すだけにする。
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = ($request->user()->role ?? null) === 'admin';

        return view('dashboard.index', [
            'stats' => new DashboardStats($isAdmin),
        ]);
    }
}

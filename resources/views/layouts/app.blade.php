<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title') | CivilTrack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/common.css') }}">
</head>
<body>
<header class="app-header">
    <div class="brand">
        <span class="brand-name">CivilTrack</span>
        <span class="brand-sub">土木施設要望・対応管理システム</span>
    </div>
    <nav>
        {{-- 一覧・地図は未実装のため、ルートが存在する場合のみリンクを出す（実装フェーズで有効化） --}}
        @if (Route::has('requests.index'))
            <a href="{{ route('requests.index') }}" class="@yield('nav-requests')">案件一覧</a>
        @endif
        @if (Route::has('map.index'))
            <a href="{{ route('map.index') }}" class="@yield('nav-map')">地図表示</a>
        @endif
        <a href="{{ route('requests.create') }}" class="@yield('nav-create')">新規登録</a>
        {{-- ユーザー管理リンクは次フェーズ（ユーザー管理実装時）に @can('admin') で追加する（画面設計書3.3） --}}
    </nav>
    <div class="user">
        @php
            $departmentLabels = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
            $user = auth()->user();
        @endphp
        {{ $user->office?->name }}
        @if ($user->department)
            {{ $departmentLabels[$user->department] ?? '' }}課
        @endif
        {{ $user->name }}
        ｜<a href="{{ route('password.edit') }}">パスワード変更</a>
        ｜<form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-link">ログアウト</button>
        </form>
    </div>
</header>

<main class="app-main">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>

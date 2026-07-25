@extends('layouts.guest')

@section('title', 'パスワード変更')

@section('content')
    <h1>パスワード変更</h1>

    @if (auth()->user()->must_change_password)
        <div class="subtitle">
            初期パスワードのままログインしています。<br>
            続けるには新しいパスワードを設定してください。
        </div>
    @else
        <div class="subtitle">
            現在のパスワードを入力のうえ<br>
            新しいパスワードを設定してください
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        @method('PUT')
        <div class="field">
            <label for="current_password">現在のパスワード（初回ログイン時は初期パスワード）</label>
            <input type="password" id="current_password" name="current_password"
                   placeholder="現在のパスワードを入力" autofocus>
        </div>
        <div class="field">
            <label for="password">新しいパスワード</label>
            <input type="password" id="password" name="password"
                   placeholder="8文字以上の新しいパスワード">
        </div>
        <div class="field">
            <label for="password_confirmation">新しいパスワード（確認）</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   placeholder="確認のためもう一度入力">
        </div>
        <button type="submit" class="btn btn-primary">変更する</button>
    </form>

    <div class="login-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-link">ログアウト</button>
        </form>
    </div>
@endsection

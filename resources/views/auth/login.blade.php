@extends('layouts.guest')

@section('title', 'ログイン')

@section('content')
    <h1>CivilTrack</h1>
    <div class="tagline">土木施設要望・対応管理システム</div>
    <div class="subtitle">土木事務所 職員ログイン</div>

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
            <label for="user_id">ユーザーID</label>
            <input type="text" id="user_id" name="user_id"
                   value="{{ old('user_id') }}"
                   placeholder="ユーザーIDを入力" autofocus>
        </div>
        <div class="field">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password"
                   placeholder="パスワードを入力">
        </div>
        <button type="submit" class="btn btn-primary">ログイン</button>
    </form>

    <div class="login-footer">
        ID・パスワードを忘れた場合は管理者にお問い合わせください
    </div>
@endsection

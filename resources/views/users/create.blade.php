@extends('layouts.app')

@section('title', 'ユーザー登録')
@section('nav-users', 'active')

@php
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $role = old('role', 'staff');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1 class="page-title">ユーザー登録</h1>
        <a class="btn btn-secondary" href="{{ route('users.index') }}">ユーザー一覧に戻る</a>
    </div>

    <div class="note" style="margin-bottom:16px;">この画面は「システム管理者」権限を持つユーザーのみ利用できます。</div>

    @if ($errors->any())
        <div class="alert alert-danger">入力内容にエラーがあります。各項目をご確認ください。</div>
    @endif

    <form class="card" method="POST" action="{{ route('users.store') }}">
        @csrf

        <div class="section-heading">アカウント情報</div>
        <div class="form-grid">
            <label class="required">ユーザーID</label>
            <div>
                <input type="text" name="user_id" value="{{ old('user_id') }}" placeholder="例：yamada_t">
                <div class="note">ログイン時に使用します。システム全体で一意である必要があります。</div>
                @error('user_id')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">氏名</label>
            <div>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="例：山田 太郎">
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">初期パスワード</label>
            <div>
                <input type="password" name="password" placeholder="初期パスワードを入力（8文字以上）">
                <input type="password" name="password_confirmation" placeholder="確認のためもう一度入力" style="margin-top:8px;">
                <div class="note">初回ログイン時に本人がパスワードを変更する運用を想定しています。</div>
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        @include('users.partials.role-fields', ['role' => $role, 'departments' => $departments, 'selectedOffice' => old('office_id'), 'selectedDept' => old('department')])

        <div class="btn-row">
            <a class="btn btn-secondary" href="{{ route('users.index') }}">キャンセル</a>
            <button type="submit" class="btn btn-primary">登録する</button>
        </div>
    </form>
@endsection

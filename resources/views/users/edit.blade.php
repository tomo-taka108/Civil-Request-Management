@extends('layouts.app')

@section('title', 'ユーザー編集')
@section('nav-users', 'active')

@php
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $role = old('role', $user->role);
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1 class="page-title">ユーザー編集</h1>
        <a class="btn btn-secondary" href="{{ route('users.index') }}">ユーザー一覧に戻る</a>
    </div>

    <div class="note" style="margin-bottom:16px;">この画面は「システム管理者」権限を持つユーザーのみ利用できます。</div>

    @if ($errors->any())
        <div class="alert alert-danger">入力内容にエラーがあります。各項目をご確認ください。</div>
    @endif

    <form class="card" method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="section-heading">アカウント情報</div>
        <div class="form-grid">
            <label class="required">ユーザーID</label>
            <div>
                <input type="text" name="user_id" value="{{ old('user_id', $user->user_id) }}">
                <div class="note">ログイン時に使用します。入力ミスの訂正等で変更できますが、システム全体で一意である必要があります。</div>
                @error('user_id')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">氏名</label>
            <div>
                <input type="text" name="name" value="{{ old('name', $user->name) }}">
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>パスワード</label>
            <div>
                {{-- 再発行は別フォーム（PUT）。更新フォームにネストできないため外に置く。 --}}
                <button type="submit" form="reissue-form" class="btn btn-secondary"
                        onclick="return confirm('初期パスワードを再発行します。よろしいですか？');">初期パスワードを再発行する</button>
                <div class="note">本人がパスワードを忘れた場合、システム管理者が初期パスワードを再発行し、次回ログイン時に本人が再設定します。</div>
            </div>
        </div>

        @include('users.partials.role-fields', ['role' => $role, 'departments' => $departments, 'selectedOffice' => old('office_id', $user->office_id), 'selectedDept' => old('department', $user->department)])

        <div class="btn-row">
            <a class="btn btn-secondary" href="{{ route('users.index') }}">キャンセル</a>
            <button type="submit" class="btn btn-primary">更新する</button>
        </div>
    </form>

    {{-- アカウント状態（無効化）。自分自身は無効化できない。 --}}
    <div class="card" style="border-color:var(--color-danger);">
        <div class="section-heading" style="color:var(--color-danger); border-bottom-color:var(--color-danger);">アカウント状態</div>
        <div class="form-grid">
            <label>現在の状態</label>
            <div>
                @if ($user->status === 'active')
                    <span class="badge badge-status-対応完了">有効</span>
                @else
                    <span class="badge badge-status-未対応">無効化済み</span>
                @endif
            </div>

            @if ($user->status === 'active')
                <label>無効化</label>
                <div>
                    @if ($user->id === auth()->id())
                        <button type="button" class="btn btn-danger" disabled>このアカウントを無効化する</button>
                        <div class="note">自分自身のアカウントは無効化できません。</div>
                    @else
                        <button type="submit" form="deactivate-form" class="btn btn-danger"
                                onclick="return confirm('このアカウントを無効化します。無効化するとログインできなくなります。よろしいですか？');">このアカウントを無効化する</button>
                        <div class="note">異動・退職等で本システムを利用しなくなる場合に無効化してください。登録済み案件の登録者としての表示は残ります。</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <form id="reissue-form" method="POST" action="{{ route('users.reissue-password', $user) }}" style="display:none;">
        @csrf
        @method('PUT')
    </form>
    @if ($user->status === 'active' && $user->id !== auth()->id())
        <form id="deactivate-form" method="POST" action="{{ route('users.deactivate', $user) }}" style="display:none;">
            @csrf
            @method('PUT')
        </form>
    @endif
@endsection

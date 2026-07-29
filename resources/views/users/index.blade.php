@extends('layouts.app')

@section('title', 'ユーザー一覧')
@section('nav-users', 'active')

@php
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $roles = ['staff' => '一般職員', 'admin' => 'システム管理者'];
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1 class="page-title">ユーザー一覧</h1>
        <a class="btn btn-primary" href="{{ route('users.create') }}">ユーザーを登録する</a>
    </div>

    <div class="note" style="margin-bottom:16px;">
        この画面は「システム管理者」権限を持つユーザーのみ利用できます。システム管理者は事務所に紐づかないため、全事務所のユーザーが表示対象です。
    </div>

    <div class="card">
        <form class="search-form" method="GET" action="{{ route('users.index') }}" style="grid-template-columns: 1fr 2fr;">
            <div class="field">
                <label>所属事務所</label>
                <select name="office_id">
                    <option value="">すべて表示</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office->id }}" @selected($selectedOfficeId === $office->id)>{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="display:flex; align-items:flex-end; justify-content:flex-end; gap:10px;">
                <a class="btn btn-secondary" href="{{ route('users.index') }}">クリア</a>
                <button type="submit" class="btn btn-secondary">絞り込み</button>
            </div>
        </form>
    </div>

    <div class="card">
        <table class="list-table">
            <thead>
                <tr>
                    <th>ユーザーID</th>
                    <th>氏名</th>
                    <th>所属事務所</th>
                    <th>担当部署</th>
                    <th>権限区分</th>
                    <th>アカウント状態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr onclick="location.href='{{ route('users.edit', $user) }}'" style="cursor:pointer; @if($user->status === 'inactive') opacity:0.55; @endif">
                        <td>{{ $user->user_id }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->office?->name ?? '-' }}</td>
                        <td>{{ $departments[$user->department] ?? '-' }}</td>
                        <td>{{ $roles[$user->role] ?? $user->role }}</td>
                        <td>
                            @if ($user->status === 'active')
                                <span class="badge badge-status-対応完了">有効</span>
                            @else
                                <span class="badge badge-status-未対応">無効化済み</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:24px 0;">該当するユーザーはいません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $users->links('vendor.pagination.civiltrack') }}
    </div>
@endsection

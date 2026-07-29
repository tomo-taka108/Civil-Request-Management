@extends('layouts.app')

@section('title', '新規登録')
@section('nav-create', 'active')

@php
    $receptionMethods = ['window' => '窓口', 'phone' => '電話', 'email' => 'メール', 'letter' => '要望書', 'fax' => 'FAX', 'patrol' => '職員パトロール', 'other' => 'その他'];
    $requesterCategories = ['individual' => '個人', 'neighborhood_association' => '自治会', 'municipality' => '市町村', 'council_member' => '議員', 'anonymous' => '匿名', 'staff_patrol' => '職員パトロール', 'other' => 'その他'];
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $requestTypes = ['complaint' => '苦情', 'request' => '要望', 'anomaly' => '異常発見'];
    $necessities = ['yes' => 'あり', 'no' => 'なし', 'unknown' => '不明'];
    $urgencies = ['high' => '高', 'medium' => '中', 'low' => '低'];
    $statuses = ['not_started' => '未対応', 'in_progress' => '対応中', 'completed' => '対応完了'];
@endphp

@section('content')
    <h1 class="page-title">新規登録</h1>

    @if ($errors->any())
        <div class="alert alert-danger">入力内容にエラーがあります。各項目をご確認ください。</div>
    @endif

    <form class="card" method="POST" action="{{ route('requests.store') }}">
        @csrf

        <div class="section-heading">管理情報</div>
        <div class="form-grid">
            <label class="required">受付日時</label>
            <div>
                <div style="display:flex; gap:8px;">
                    <input type="date" name="reception_date" value="{{ old('reception_date', now()->format('Y-m-d')) }}" style="flex:1;">
                    <input type="time" name="reception_time" value="{{ old('reception_time', now()->format('H:i')) }}" style="flex:1;">
                </div>
                @error('reception_date')<div class="error">{{ $message }}</div>@enderror
                @error('reception_time')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">受付方法</label>
            <div>
                <select name="reception_method" id="reception_method"
                        onchange="document.getElementById('reception_method_other_wrap').style.display = (this.value === 'other') ? 'block' : 'none';">
                    @foreach ($receptionMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('reception_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div id="reception_method_other_wrap" style="margin-top:8px; @if(old('reception_method') !== 'other') display:none; @endif">
                    <input type="text" name="reception_method_other" value="{{ old('reception_method_other') }}" placeholder="その他の場合は具体的に入力">
                </div>
                @error('reception_method')<div class="error">{{ $message }}</div>@enderror
                @error('reception_method_other')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>登録者（受付職員）</label>
            <input type="text" value="{{ auth()->user()->name }}（ログインユーザー）" disabled>
        </div>

        <div class="section-heading">相手方情報</div>
        <div class="form-grid">
            <label class="required">区分</label>
            <div>
                <div class="radio-group">
                    @foreach ($requesterCategories as $value => $label)
                        <label><input type="radio" name="requester_category" value="{{ $value }}" @checked(old('requester_category', 'individual') === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('requester_category')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>要望者</label>
            <div>
                <input type="text" name="requester_name" value="{{ old('requester_name') }}" placeholder="氏名・団体名等を入力">
                <div class="note">区分が「匿名」「職員パトロール」の場合は入力不要</div>
                @error('requester_name')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="section-heading">案件情報</div>
        <div class="form-grid">
            <label class="required">対応部署</label>
            <div>
                <select name="department">
                    @foreach ($departments as $value => $label)
                        <option value="{{ $value }}" @selected(old('department') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('department')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">種別</label>
            <div>
                <div class="radio-group">
                    @foreach ($requestTypes as $value => $label)
                        <label><input type="radio" name="request_type" value="{{ $value }}" @checked(old('request_type', 'complaint') === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('request_type')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">要望の内容</label>
            <div>
                <textarea name="content" placeholder="要望・苦情・異常発見の内容を入力">{{ old('content') }}</textarea>
                @error('content')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>要望箇所（住所）</label>
            <div>
                <input type="text" name="address" value="{{ old('address') }}" placeholder="例：〇〇市△△町1丁目付近">
                @error('address')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>要望箇所（地図）</label>
            <div>
                {{-- 地図クリックで緯度経度を hidden にセットする（データベース設計上 NULL 可）。
                     バリデーションエラー時は old() を初期値に復元する。 --}}
                @include('requests.partials.location-map', [
                    'mapId' => 'create-map',
                    'editable' => true,
                    'latitude' => old('latitude'),
                    'longitude' => old('longitude'),
                ])
                @error('latitude')<div class="error">{{ $message }}</div>@enderror
                @error('longitude')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="section-heading">対応情報</div>
        <div class="form-grid">
            <label class="required">対応の必要性</label>
            <div>
                <div class="radio-group">
                    @foreach ($necessities as $value => $label)
                        <label><input type="radio" name="response_necessity" value="{{ $value }}" @checked(old('response_necessity', 'yes') === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('response_necessity')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">緊急性</label>
            <div>
                <div class="radio-group">
                    @foreach ($urgencies as $value => $label)
                        <label><input type="radio" name="urgency" value="{{ $value }}" @checked(old('urgency', 'medium') === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('urgency')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>対応方針</label>
            <div>
                <textarea name="response_policy" placeholder="現時点での対応方針があれば入力（未定なら空欄可）">{{ old('response_policy') }}</textarea>
                @error('response_policy')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">対応状況</label>
            <div>
                <select name="response_status">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('response_status', 'not_started') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('response_status')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="btn-row">
            <a class="btn btn-secondary" href="{{ route('requests.create') }}">キャンセル</a>
            <button type="submit" class="btn btn-primary">登録する</button>
        </div>
    </form>
@endsection

@extends('layouts.app')

@section('title', '案件編集')
@section('nav-requests', 'active')

@php
    $receptionMethods = ['window' => '窓口', 'phone' => '電話', 'email' => 'メール', 'letter' => '要望書', 'fax' => 'FAX', 'patrol' => '職員パトロール', 'other' => 'その他'];
    $requesterCategories = ['individual' => '個人', 'neighborhood_association' => '自治会', 'municipality' => '市町村', 'council_member' => '議員', 'anonymous' => '匿名', 'staff_patrol' => '職員パトロール', 'other' => 'その他'];
    $departments = ['road' => '道路', 'river' => '河川', 'sabo' => '砂防'];
    $requestTypes = ['complaint' => '苦情', 'request' => '要望', 'anomaly' => '異常発見'];
    $necessities = ['yes' => 'あり', 'no' => 'なし', 'unknown' => '不明'];
    $urgencies = ['high' => '高', 'medium' => '中', 'low' => '低'];
    $statuses = ['not_started' => '未対応', 'in_progress' => '対応中', 'completed' => '対応完了'];

    // 表示値：バリデーションエラー時は old() を優先し、なければ既存の登録値を使う。
    $method = old('reception_method', $request->reception_method);
    $status = old('response_status', $request->response_status);
@endphp

@section('content')
    <h1 class="page-title">案件編集（受付番号：{{ $request->reception_number }}）</h1>

    @if ($errors->any())
        <div class="alert alert-danger">入力内容にエラーがあります。各項目をご確認ください。</div>
    @endif

    <form class="card" method="POST" action="{{ route('requests.update', $request) }}">
        @csrf
        @method('PUT')

        <div class="section-heading">管理情報</div>
        <div class="form-grid">
            <label>受付番号</label>
            <input type="text" value="{{ $request->reception_number }}" disabled>

            <label class="required">受付日時</label>
            <div>
                <div style="display:flex; gap:8px;">
                    <input type="date" name="reception_date" value="{{ old('reception_date', $request->reception_date->format('Y-m-d')) }}" style="flex:1;">
                    <input type="time" name="reception_time" value="{{ old('reception_time', \Illuminate\Support\Str::substr($request->reception_time, 0, 5)) }}" style="flex:1;">
                </div>
                @error('reception_date')<div class="error">{{ $message }}</div>@enderror
                @error('reception_time')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">受付方法</label>
            <div>
                <select name="reception_method" id="reception_method"
                        onchange="document.getElementById('reception_method_other_wrap').style.display = (this.value === 'other') ? 'block' : 'none';">
                    @foreach ($receptionMethods as $value => $label)
                        <option value="{{ $value }}" @selected($method === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div id="reception_method_other_wrap" style="margin-top:8px; @if($method !== 'other') display:none; @endif">
                    <input type="text" name="reception_method_other" value="{{ old('reception_method_other', $request->reception_method_other) }}" placeholder="その他の場合は具体的に入力">
                </div>
                @error('reception_method')<div class="error">{{ $message }}</div>@enderror
                @error('reception_method_other')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>登録者（受付職員）</label>
            <input type="text" value="{{ $request->registeredBy?->name ?? '-' }}" disabled>

            <label>最終更新日時</label>
            <input type="text" value="{{ $request->updated_at->format('Y-m-d H:i') }}" disabled>
        </div>

        <div class="section-heading">相手方情報</div>
        <div class="form-grid">
            <label class="required">区分</label>
            <div>
                <div class="radio-group">
                    @foreach ($requesterCategories as $value => $label)
                        <label><input type="radio" name="requester_category" value="{{ $value }}" @checked(old('requester_category', $request->requester_category) === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('requester_category')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>要望者</label>
            <div>
                <input type="text" name="requester_name" value="{{ old('requester_name', $request->requester_name) }}" placeholder="氏名・団体名等を入力">
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
                        <option value="{{ $value }}" @selected(old('department', $request->department) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('department')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">種別</label>
            <div>
                <div class="radio-group">
                    @foreach ($requestTypes as $value => $label)
                        <label><input type="radio" name="request_type" value="{{ $value }}" @checked(old('request_type', $request->request_type) === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('request_type')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">要望の内容</label>
            <div>
                <textarea name="content" placeholder="要望・苦情・異常発見の内容を入力">{{ old('content', $request->content) }}</textarea>
                @error('content')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>要望箇所（住所）</label>
            <div>
                <input type="text" name="address" value="{{ old('address', $request->address) }}" placeholder="例：〇〇市△△町1丁目付近">
                @error('address')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>要望箇所（地図）</label>
            <div>
                {{-- 既存の緯度経度をピンで初期表示し、クリック/ドラッグで再設定・クリアできる。
                     バリデーションエラー時は old() を優先する。 --}}
                @include('requests.partials.location-map', [
                    'mapId' => 'edit-map',
                    'editable' => true,
                    'latitude' => old('latitude', $request->latitude),
                    'longitude' => old('longitude', $request->longitude),
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
                        <label><input type="radio" name="response_necessity" value="{{ $value }}" @checked(old('response_necessity', $request->response_necessity) === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('response_necessity')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">緊急性</label>
            <div>
                <div class="radio-group">
                    @foreach ($urgencies as $value => $label)
                        <label><input type="radio" name="urgency" value="{{ $value }}" @checked(old('urgency', $request->urgency) === $value)>{{ $label }}</label>
                    @endforeach
                </div>
                @error('urgency')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>対応方針</label>
            <div>
                <textarea name="response_policy" placeholder="現時点での対応方針があれば入力（未定なら空欄可）">{{ old('response_policy', $request->response_policy) }}</textarea>
                @error('response_policy')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label class="required">対応状況</label>
            <div>
                <select name="response_status" id="response_status"
                        onchange="document.getElementById('response_completed_date').disabled = (this.value !== 'completed');">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('response_status')<div class="error">{{ $message }}</div>@enderror
            </div>

            <label>対応完了日</label>
            <div>
                <input type="date" name="response_completed_date" id="response_completed_date"
                       value="{{ old('response_completed_date', $request->response_completed_date?->format('Y-m-d')) }}"
                       @disabled($status !== 'completed')>
                <div class="note">対応状況が「対応完了」の場合のみ入力できます</div>
                @error('response_completed_date')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="btn-row" style="justify-content:space-between;">
            <button type="submit" form="delete-form" class="btn btn-danger"
                    onclick="return confirm('この案件を削除します。よろしいですか？');">この案件を削除する</button>
            <div style="display:flex; gap:10px;">
                <a class="btn btn-secondary" href="{{ route('requests.show', $request) }}">キャンセル</a>
                <button type="submit" class="btn btn-primary">更新する</button>
            </div>
        </div>
    </form>

    {{-- 削除は別フォーム（DELETE）。更新フォームの中にネストできないため外に置く。 --}}
    <form id="delete-form" method="POST" action="{{ route('requests.destroy', $request) }}" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
@endsection

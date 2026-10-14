{{--
    対応履歴タイムライン（要件定義書 §2.6）。show.blade.php から include する。

    パラメータ:
    - $request … 案件（親ビューから継承）

    編集権限（$canManageLog）がある場合のみ、各履歴の「編集」「削除」操作と、
    末尾の追加フォームを表示する。他部署の職員は閲覧のみ。

    編集フォームは各履歴ごとに用意し、既定で非表示にしておく。「編集」ボタンで
    表示本体と入れ替える（新規JS依存を追加しないため、登録・編集フォームの
    「その他」トグルと同じ素朴な style.display 切り替えで実装する）。

    各履歴には、その対応で撮影した現場写真（§2.8）を表示する。写真は追加・編集の
    フォームから登録する（1件の履歴につき最大5枚）。

    追加フォームと各編集フォームは同じ項目名を使うため、入力エラーで戻ってきたときに
    どのフォームの入力・エラーかを editing_log_id（編集フォームのみ送る）で見分け、
    該当する編集フォームを開いた状態で表示する。
--}}
@php
    $canManageLog = auth()->user()->can('manageLog', $request);
    $logs = $request->logs;
    $maxPhotos = \App\Models\RequestPhoto::MAX_PER_GROUP;
    $editingLogId = old('editing_log_id');
    $photoError = $errors->first('photos') ?: $errors->first('photos.*');
@endphp

<div class="section-heading">対応履歴</div>

@if ($logs->isEmpty())
    <div class="note">対応履歴はまだありません。</div>
@else
    <ul class="timeline">
        @foreach ($logs as $log)
            @php $isEditing = $editingLogId !== null && (int) $editingLogId === $log->id; @endphp
            <li>
                {{-- 表示本体。編集ボタンでフォームに切り替える。 --}}
                <div id="log-view-{{ $log->id }}" @if ($isEditing) style="display:none;" @endif>
                    <div class="log-head">
                        <span class="log-date">{{ $log->logged_on->format('Y-m-d') }}</span>
                        <span class="log-author">記録者：{{ $log->recordedBy->name }}</span>
                        @if ($log->wasEdited())
                            {{-- 記録の訂正を隠さず明示する（要件定義書 §2.6）。 --}}
                            <span class="log-edited">編集済み（{{ $log->updated_at->format('Y-m-d H:i') }}）</span>
                        @endif
                        @can('manageLog', $request)
                            <span class="log-actions">
                                <button type="button" class="btn-link" onclick="
                                    document.getElementById('log-view-{{ $log->id }}').style.display='none';
                                    document.getElementById('log-edit-{{ $log->id }}').style.display='grid';
                                ">編集</button>
                                <button type="submit" form="log-delete-form-{{ $log->id }}" class="btn-link"
                                        onclick="return confirm('この対応履歴を削除します。よろしいですか？');">削除</button>
                            </span>
                        @endcan
                    </div>
                    <div class="log-body">{{ $log->content }}</div>
                    @include('requests.partials.photo-grid', ['request' => $request, 'photos' => $log->photos])
                </div>

                {{-- 編集フォーム（既定では非表示）。 --}}
                @can('manageLog', $request)
                    <form id="log-edit-{{ $log->id }}" class="form-grid" style="display:{{ $isEditing ? 'grid' : 'none' }}; margin-top:10px;"
                          method="POST" action="{{ route('requests.logs.update', [$request, $log]) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="editing_log_id" value="{{ $log->id }}">

                        <label class="required">対応日</label>
                        <div>
                            <input type="date" name="logged_on" value="{{ $isEditing ? old('logged_on') : $log->logged_on->format('Y-m-d') }}">
                            @if ($isEditing)
                                @error('logged_on')<div class="error">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <label class="required">対応内容</label>
                        <div>
                            <textarea name="content">{{ $isEditing ? old('content') : $log->content }}</textarea>
                            <div class="note">内容を訂正すると「編集済み」と訂正日時が表示されます（記録の訂正を隠さないため）。</div>
                            @if ($isEditing)
                                @error('content')<div class="error">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <label>写真を追加</label>
                        <div>
                            @if ($log->photos->count() >= $maxPhotos)
                                <div class="note">この対応履歴には写真が上限の{{ $maxPhotos }}枚登録されています。追加するには、先に不要な写真を削除してください。</div>
                            @else
                                <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple>
                                <div class="note">この対応履歴に紐づく写真として登録されます。JPEG・PNG のみ、1ファイル5MBまで、1件の対応履歴につき最大{{ $maxPhotos }}枚まで（この履歴は現在{{ $log->photos->count() }}枚）。</div>
                            @endif
                            @if ($isEditing && $photoError)
                                <div class="error">{{ $photoError }}</div>
                            @endif
                        </div>

                        <div style="grid-column:2; display:flex; gap:10px; justify-content:flex-end;">
                            <button type="button" class="btn btn-secondary" onclick="
                                document.getElementById('log-edit-{{ $log->id }}').style.display='none';
                                document.getElementById('log-view-{{ $log->id }}').style.display='block';
                            ">キャンセル</button>
                            <button type="submit" class="btn btn-primary">保存する</button>
                        </div>
                    </form>

                    <form id="log-delete-form-{{ $log->id }}" method="POST"
                          action="{{ route('requests.logs.destroy', [$request, $log]) }}" style="display:none;">
                        @csrf
                        @method('DELETE')
                    </form>
                @endcan
            </li>
        @endforeach
    </ul>
@endif

@can('manageLog', $request)
    {{-- 対応履歴の追加フォーム。登録後は案件の「最終更新日時」も自動で更新される（$touches）。 --}}
    <div class="section-heading">履歴を追加</div>
    <form class="form-grid" method="POST" action="{{ route('requests.logs.store', $request) }}" enctype="multipart/form-data">
        @csrf

        <label class="required">対応日</label>
        <div>
            <input type="date" name="logged_on" value="{{ $editingLogId === null ? old('logged_on', now()->format('Y-m-d')) : now()->format('Y-m-d') }}">
            <div class="note">実際に対応した日を入力してください（未来日は登録できません）。</div>
            @if ($editingLogId === null)
                @error('logged_on')<div class="error">{{ $message }}</div>@enderror
            @endif
        </div>

        <label class="required">対応内容</label>
        <div>
            <textarea name="content" placeholder="実施した対応・経過を入力（1000文字以内）">{{ $editingLogId === null ? old('content') : '' }}</textarea>
            @if ($editingLogId === null)
                @error('content')<div class="error">{{ $message }}</div>@enderror
            @endif
        </div>

        <label>現場写真</label>
        <div>
            <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple>
            <div class="note">この対応で撮影した写真を一緒に登録できます。JPEG・PNG のみ、1ファイル5MBまで、1件の対応履歴につき最大{{ $maxPhotos }}枚まで。</div>
            @if ($editingLogId === null && $photoError)
                <div class="error">{{ $photoError }}</div>
            @endif
        </div>

        <div style="grid-column:2; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">履歴を追加する</button>
        </div>
    </form>

    <div class="alert alert-info" style="margin-top:18px; margin-bottom:0;">
        撮影の際は、通行人の顔・表札・車両のナンバー等、対応に不要な個人情報が写り込まないようご注意ください（要件定義書 4.1）。
    </div>
@endcan

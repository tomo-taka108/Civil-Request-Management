{{--
    現場写真のサムネイル一覧（要件定義書 §2.8）。対応履歴・受付時の写真で共用する。

    パラメータ:
    - $request … 案件（削除フォームの送信先に使う）
    - $photos  … 表示する写真（RequestPhoto のコレクション）

    画像は期限付きの署名付きURLで表示する。クリックで別タブに拡大表示する。
    削除ボタンは写真の操作権限（managePhoto）がある場合のみ表示する。
--}}
@if ($photos->isNotEmpty())
    <div class="photo-grid">
        @foreach ($photos as $photo)
            @php $url = $photo->temporaryUrl(); @endphp
            <div class="photo-item">
                <a href="{{ $url }}" target="_blank" rel="noopener">
                    <img src="{{ $url }}" alt="現場写真：{{ $photo->original_name }}" loading="lazy">
                </a>
                <div class="photo-meta">
                    <span class="photo-name" title="{{ $photo->original_name }}">{{ $photo->original_name }}</span>
                    @can('managePhoto', $request)
                        <form method="POST" action="{{ route('requests.photos.destroy', [$request, $photo]) }}"
                              onsubmit="return confirm('この写真を削除します。削除した写真は元に戻せません。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-link">削除</button>
                        </form>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- 想定外のエラー（500）。APP_DEBUG=false の本番で表示される。
     問い合わせ番号（リクエストID）を表示し、利用者の報告とログを突き合わせられるようにする
     （ログ・監視設計書 8章）。エラーの中身は利用者に見せない。 --}}
@extends('layouts.guest')

@section('title', 'エラーが発生しました')

@section('content')
    <h1>CivilTrack</h1>
    <div class="subtitle">エラーが発生しました</div>

    <p style="margin:16px 0;line-height:1.8;">
        処理中に問題が発生しました。お手数ですが、時間をおいてもう一度お試しください。
        繰り返し発生する場合は、下の問い合わせ番号を添えて管理者にお知らせください。
    </p>

    @php($requestId = \Illuminate\Support\Facades\Context::get('request_id'))
    @if ($requestId)
        <div class="field">
            <label>問い合わせ番号</label>
            <div style="font-family:monospace;word-break:break-all;">{{ $requestId }}</div>
        </div>
    @endif

    <a href="{{ url('/') }}" class="btn btn-primary" style="display:block;text-align:center;">トップへ戻る</a>
@endsection

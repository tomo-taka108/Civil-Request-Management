{{-- プロジェクト共通CSS（.pagination > a / span）に合わせたページネーション。
     Laravel標準のTailwind/Bootstrap用ビューは使わず、mockup/list.html の構造に揃える。 --}}
@if ($paginator->hasPages())
    <nav class="pagination">
        {{-- 前へ --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true">&laquo;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>
        @endif

        {{-- ページ番号 --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span>{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <a href="{{ $url }}" class="current" aria-current="page">{{ $page }}</a>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- 次へ --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
        @else
            <span aria-disabled="true">&raquo;</span>
        @endif
    </nav>
@endif

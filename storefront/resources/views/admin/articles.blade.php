@extends('layouts.admin')

@section('title', 'مقاله‌ها')

{{--
    «مقالات» — everything written, drafts included.

    Not branch-scoped: an article is the shop's, and the shop is one shop with
    several counters.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">
        {{ $articles->total() > 0 ? fa_number($articles->total()).' مقاله' : 'هنوز مقاله‌ای نوشته نشده' }}
    </p>

    <div class="vp-adm-head-side">
        <a class="vp-adm-clear is-on" href="{{ route('admin.article.create') }}">مقالهٔ تازه</a>
    </div>
</div>

<section class="vp-adm-card">
    @if ($articles->isEmpty())
        <p class="vp-adm-empty">هنوز مقاله‌ای نوشته نشده.</p>
    @else
        <table class="vp-admin-table">
            <thead>
                <tr><th>عنوان</th><th>نشانی</th><th>تاریخ انتشار</th><th>وضعیت</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($articles as $article)
                <tr>
                    <td>
                        <a href="{{ route('admin.article.edit', $article) }}">{{ $article->title }}</a>
                        <span class="vp-adm-sub">{{ $article->summary(80) }}</span>
                    </td>
                    <td><bdi dir="ltr">{{ $article->slug }}</bdi></td>
                    <td>{{ $article->published_at ? fa_date($article->published_at) : '—' }}</td>
                    <td>
                        <span class="vp-adm-badge is-{{ $article->isPublished() ? 'delivered' : 'placed' }}">
                            {{ \App\Models\Article::LABELS[$article->status] }}
                        </span>
                    </td>
                    <td>
                        <div class="vp-adm-inline">
                            <a class="vp-adm-mini is-quiet" href="{{ route('admin.article.edit', $article) }}">ویرایش</a>
                            @if ($article->isPublished())
                                <a class="vp-adm-mini is-quiet" href="{{ storefront_route('article', $article) }}">روی سایت</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="vp-adm-pager">{{ $articles->links('pagination.vikyplus') }}</div>
    @endif
</section>
@endsection

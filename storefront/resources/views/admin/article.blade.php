@extends('layouts.admin')

@section('title', $article->exists ? $article->title : 'مقالهٔ تازه')

{{--
    One article — «متن ساده با عکس و تیتر».

    One form with five fields on it. No editor, no media library, no revisions:
    a screen that asks for more than was asked for is a screen nobody finishes
    filling in.

    The body is plain text and is printed as plain text. Whatever is typed here
    reaches the page escaped, with its line breaks kept — so a paragraph is a
    blank line, and pasted markup is words rather than a page taking somebody
    else's shape.
--}}

@section('content')
<div class="vp-adm-head">
    <p class="vp-adm-sub">
        @if ($article->exists)
            <bdi dir="ltr">{{ $article->slug }}</bdi>
        @else
            عنوان و متن لازم است؛ عکس و خلاصه اختیاری‌اند.
        @endif
    </p>

    <div class="vp-adm-head-side">
        @if ($article->exists && $article->isPublished())
            <a class="vp-adm-clear" href="{{ storefront_route('article', $article) }}">دیدن روی سایت</a>
        @endif
        <a class="vp-adm-clear" href="{{ route('admin.articles') }}">بازگشت به مقاله‌ها</a>
    </div>
</div>

<section class="vp-adm-card">
    <div class="vp-adm-card-head">
        <h2 class="vp-adm-card-title">{{ $article->exists ? 'ویرایش مقاله' : 'مقالهٔ تازه' }}</h2>
    </div>

    {{-- `enctype`, because of the photograph. Without it the file arrives as
         a filename and the article publishes with no picture and no error. --}}
    <form class="vp-adm-form" method="post" enctype="multipart/form-data"
          action="{{ $article->exists ? route('admin.article.update', $article) : route('admin.article.store') }}">
        @csrf

        <label for="a-title">عنوان</label>
        <input id="a-title" name="title" value="{{ old('title', $article->title) }}" required maxlength="200">

        <div class="vp-adm-form-row">
            <div class="vp-adm-form">
                {{-- Left empty on a new article and written from the title.
                     Kept as it is afterwards: the address is what other sites
                     link to, so rewording a headline must not move it. --}}
                <label for="a-slug">نشانی صفحه</label>
                <input id="a-slug" name="slug" value="{{ old('slug', $article->slug) }}" maxlength="200"
                       placeholder="از روی عنوان ساخته می‌شود">
            </div>
            <div class="vp-adm-form">
                <label for="a-status">وضعیت</label>
                <select id="a-status" name="status">
                    @foreach (\App\Models\Article::LABELS as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('status', $article->status ?? \App\Models\Article::DRAFT) === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <label for="a-excerpt">خلاصه</label>
        <textarea id="a-excerpt" name="excerpt" rows="2" maxlength="400">{{ old('excerpt', $article->excerpt) }}</textarea>

        <label for="a-body">متن</label>
        <textarea id="a-body" name="body" rows="16" maxlength="40000" required>{{ old('body', $article->body) }}</textarea>

        @if ($article->image)
            <span class="vp-adm-form-label">عکس فعلی</span>
            <img class="vp-adm-art-shot" src="{{ asset($article->image) }}" alt="">
        @endif

        <label for="a-image">{{ $article->image ? 'جایگزینی عکس' : 'عکس' }}</label>
        <input id="a-image" type="file" name="image" accept="image/*">

        <button type="submit" class="vp-adm-apply">{{ $article->exists ? 'ثبت' : 'ساختن مقاله' }}</button>
    </form>
</section>

@if ($article->exists)
    <section class="vp-adm-card">
        <div class="vp-adm-card-head">
            <h2 class="vp-adm-card-title">حذف</h2>
        </div>

        <p class="vp-adm-sub">
            حذف مقاله برگشت‌پذیر نیست؛ اگر فقط نمی‌خواهید روی سایت دیده شود،
            وضعیتش را «پیش‌نویس» کنید.
        </p>

        <form method="post" action="{{ route('admin.article.destroy', $article) }}">
            @csrf
            <button type="submit" class="vp-adm-mini is-bad">حذف مقاله</button>
        </form>
    </section>
@endif
@endsection

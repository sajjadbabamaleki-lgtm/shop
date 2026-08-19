{{--
    Which branch the panel is pointed at, and the way to point it somewhere
    else. Rendered twice — in the topbar for a desktop and in the drawer's head
    for a phone — from one file, so the two cannot come to disagree about what
    a branch switcher is or about who is allowed to see more than one.

    `$id` is required because both copies are in the document at every width
    (the shell has no way of knowing which one the window will show), and two
    elements cannot carry the same id. `$label` is the visible label the
    drawer has room for and the topbar does not.
--}}
@php
    $id = $id ?? 'vp-branch';
    $label = $label ?? false;
@endphp

@if ($branch && $branches->count() > 1)
    <form method="post" action="{{ route('admin.branch.switch') }}">
        @csrf
        <label class="{{ $label ? '' : 'visually-hidden' }}" for="{{ $id }}">شعبه</label>
        <select id="{{ $id }}" name="branch" onchange="this.form.submit()">
            @foreach ($branches as $option)
                <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
            @endforeach
        </select>
        <noscript><button type="submit">برو</button></noscript>
    </form>
@elseif ($branch)
    @if ($label)
        <label for="{{ $id }}">شعبه</label>
    @endif
    <span class="vp-adm-branch" id="{{ $id }}">{{ $branch->name }}</span>
@endif

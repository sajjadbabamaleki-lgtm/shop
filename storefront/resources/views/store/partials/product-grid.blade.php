@if($products->isEmpty())
    <p class="pane muted" style="margin-top:14px">هنوز محصولی در این فروشگاه ثبت نشده است.</p>
@else
    <div class="grid grid--3" style="margin-top:14px">
        @foreach($products as $product)
            <a class="pane pane--tight" href="{{ route('store.product', $product) }}">
                @php $image = $product->media->firstWhere('is_primary', true) ?? $product->media->first(); @endphp
                @if($image)
                    <img class="thumb" src="{{ asset($image->path) }}" alt="{{ $image->alt ?? $product->title }}">
                @else
                    <span class="thumb" style="display:block"></span>
                @endif
                <h3 style="margin-top:10px">{{ $product->short_title ?? $product->title }}</h3>
                @if($product->brand)<span class="faint">{{ $product->brand->name }}</span>@endif
                @if(($prices[$product->id] ?? null) !== null)
                    <div class="price" style="margin-top:6px">@toman($prices[$product->id])</div>
                @endif
            </a>
        @endforeach
    </div>
@endif

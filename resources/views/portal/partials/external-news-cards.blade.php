@foreach ($articles as $article)
    <a class="external-news-card" href="{{ $article->article_url }}" target="_blank" rel="noopener noreferrer" data-article-id="{{ $article->id }}" data-published-at="{{ $article->published_at->toIso8601String() }}">
        @if ($article->image())
            <span class="external-news-image">
                <img src="{{ $article->image() }}" alt="{{ $article->title }}" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('.external-news-image').remove()">
            </span>
        @endif
        <span class="external-news-copy">
            <span class="external-news-meta">
                <strong>{{ $article->source }}</strong>
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->timezone('UTC')->format('d M Y · H:i').' UTC' }}</time>
            </span>
            <span class="external-news-category">{{ $article->category }}</span>
            <strong class="external-news-title">{{ $article->title }}</strong>
            @if ($article->summary)
                <span class="external-news-summary">{{ $article->summary }}</span>
            @endif
            <span class="external-news-read">Read original article ↗</span>
        </span>
    </a>
@endforeach

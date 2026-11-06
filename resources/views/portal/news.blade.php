@extends('layouts.portal')

@section('title', 'News')

@section('content')
    <div class="page-heading">
        <div><h1>News &amp; Aktivitas</h1><p>Berita logistik internasional dan update shipment CargoTrace.</p></div>
        <span class="news-live-indicator {{ $isLive ? 'is-live' : 'is-stale' }}" id="news-live-indicator">
            <i aria-hidden="true"></i>{{ $isLive ? 'LIVE' : 'FEED OFFLINE' }}
        </span>
    </div>

    <section class="panel news-panel">
        <div class="panel-head">
            <h2>Global Logistics Headlines</h2>
            <span class="news-sync-meta">
                <span id="news-last-updated">Last updated: {{ $lastUpdatedAt ? \Illuminate\Support\Carbon::parse($lastUpdatedAt)->format('d M Y · H:i:s') : 'Waiting for first sync' }}</span>
                <span>Auto-refresh every {{ config('news.refresh_minutes') }} min</span>
                <span>{{ count($successfulFeeds) }} feeds active{{ count($failedFeeds) ? ' · '.count($failedFeeds).' unavailable' : '' }}</span>
            </span>
        </div>
        <div class="panel-body">
            <div class="external-news-grid" id="external-news-grid" data-next-url="{{ $articles->nextCursor() ? route('news.articles', ['cursor' => $articles->nextCursor()->encode()]) : '' }}">
                @include('portal.partials.external-news-cards', ['articles' => $articles])
            </div>
            @if ($articles->isEmpty())
                <div class="empty news-empty" id="news-empty">
                    Belum ada berita tersimpan. Feed internasional akan tampil setelah sinkronisasi pertama berhasil.
                </div>
            @endif
            <p class="news-load-status" id="news-load-status" role="status" aria-live="polite"></p>
            <button class="btn subtle news-load-more" id="news-load-more" type="button" @if (! $articles->hasMorePages()) hidden @endif>
                Load more stories
            </button>
        </div>
    </section>

    <section class="panel news-activity-panel">
        <div class="panel-head"><h2>Shipment Activity</h2><span class="text-link">{{ number_format($events->total()) }} updates</span></div>
        <div class="panel-body">
            <div class="news-feed">
                @forelse ($events as $event)
                    @php
                        $headline = $event->description ?: str($event->event_type->value)->replace('_', ' ')->title().' — '.$event->shipment->shipment_number;
                        $location = $event->location?->city ?? $event->location?->name ?? 'Rute logistik';
                    @endphp
                    <a class="news-feed-item" href="{{ route('news.show', $event) }}">
                        <img src="{{ $event->newsImage() }}" alt="" loading="lazy">
                        <span class="news-feed-copy">
                            <span class="news-feed-meta">{{ $event->occurred_at->format('d M Y · H:i') }} · {{ $location }}</span>
                            <strong>{{ $headline }}</strong>
                            <span class="news-feed-route">{{ $event->shipment->origin?->city }} → {{ $event->shipment->destination?->city }}</span>
                        </span>
                        <span class="news-feed-chevron">›</span>
                    </a>
                @empty
                    <div class="empty">Belum ada aktivitas shipment untuk ditampilkan.</div>
                @endforelse
            </div>
        </div>
        @if ($events->hasPages())
            <div class="pagination">{{ $events->links() }}</div>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const grid = document.getElementById('external-news-grid');
            const loadButton = document.getElementById('news-load-more');
            const loadStatus = document.getElementById('news-load-status');
            const liveIndicator = document.getElementById('news-live-indicator');
            const lastUpdated = document.getElementById('news-last-updated');
            let latestId = {{ (int) $latestArticleId }};
            let loadingMore = false;

            const setFeedStatus = (isLive) => {
                liveIndicator.classList.toggle('is-live', isLive);
                liveIndicator.classList.toggle('is-stale', !isLive);
                liveIndicator.innerHTML = `<i aria-hidden="true"></i>${isLive ? 'LIVE' : 'FEED OFFLINE'}`;
            };

            const formatUpdatedAt = (value) => {
                if (!value) return;
                lastUpdated.textContent = `Last updated: ${new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'medium'
                }).format(new Date(value))}`;
            };

            const loadMore = async () => {
                const nextUrl = grid.dataset.nextUrl;
                if (!nextUrl || loadingMore) return;
                loadingMore = true;
                loadButton.disabled = true;
                loadStatus.textContent = 'Loading more stories…';

                try {
                    const response = await fetch(nextUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`News pagination failed (${response.status}).`);
                    const data = await response.json();
                    grid.insertAdjacentHTML('beforeend', data.html);
                    grid.dataset.nextUrl = data.next || '';
                    loadButton.hidden = !data.next;
                    loadStatus.textContent = '';
                } catch (error) {
                    loadStatus.textContent = 'Could not load more stories. Please try again.';
                    console.error(error);
                } finally {
                    loadingMore = false;
                    loadButton.disabled = false;
                }
            };

            loadButton.addEventListener('click', loadMore);

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) loadMore();
                }, { rootMargin: '250px' });
                observer.observe(loadButton);
            }

            const refreshNews = async () => {
                try {
                    const response = await fetch(`{{ route('news.latest') }}?after_id=${latestId}`, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store'
                    });
                    if (!response.ok) throw new Error(`News refresh failed (${response.status}).`);
                    const data = await response.json();
                    if (data.html) grid.insertAdjacentHTML('afterbegin', data.html);
                    latestId = data.latest_id;
                    setFeedStatus(data.is_live);
                    formatUpdatedAt(data.last_updated_at);
                } catch (error) {
                    loadStatus.textContent = 'Feed update is temporarily unavailable; automatic retry is scheduled.';
                    console.error(error);
                }
            };

            window.setInterval(refreshNews, {{ (int) config('news.refresh_minutes') * 60 * 1000 }});
        })();
    </script>
@endpush

<?php

namespace App\Services;

use App\Models\ExternalNewsArticle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ExternalNewsService
{
    /**
     * Fetch each configured RSS source independently so a failing publisher
     * never prevents other feeds from updating.
     *
     * @return array{successful: list<string>, failed: array<string, string>, imported: int}
     */
    public function sync(): array
    {
        $successful = [];
        $failed = [];
        $imported = 0;
        $imageLookups = 0;

        foreach (config('news.feeds', []) as $feed) {
            try {
                $response = Http::accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
                    ->timeout(8)
                    ->retry(1, 150)
                    ->get($feed['url']);
                $response->throw();

                $items = $this->parseFeed($response->body(), $feed['trusted_publishers_only'] ?? false);
                foreach ($items as $item) {
                    if ($item['image'] === null && $imageLookups < 20 && $this->needsArticleImage($item)) {
                        $cacheKey = $this->articleImageCacheKey($item['url']);
                        if (! Cache::has($cacheKey)) {
                            $imageLookups++;
                        }

                        $item['image'] = $this->fetchArticleImage($item['url']);
                    }

                    if ($this->store($item, $feed['source'])) {
                        $imported++;
                    }
                }

                $successful[] = $feed['source'];
            } catch (Throwable $exception) {
                $failed[$feed['source']] = $exception->getMessage();
                Log::warning('External news feed could not be synchronized.', [
                    'source' => $feed['source'],
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        cache()->put('news_last_sync_at', now()->toIso8601String(), now()->addDays(30));
        cache()->put('news_last_sync_sources', $successful, now()->addDays(30));
        cache()->put('news_last_sync_failures', array_keys($failed), now()->addDays(30));

        return ['successful' => $successful, 'failed' => $failed, 'imported' => $imported];
    }

    /**
     * @return list<array{title: string, summary: string, source: string, category: string, url: string, image: ?string, published_at: Carbon}>
     */
    private function parseFeed(string $xml, bool $trustedPublishersOnly): array
    {
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if (! $feed instanceof SimpleXMLElement) {
                throw new RuntimeException('The feed returned invalid XML.');
            }

            $entries = isset($feed->channel->item) ? $feed->channel->item : $feed->entry;
            $items = [];

            foreach ($entries as $entry) {
                $title = trim(html_entity_decode(strip_tags((string) $entry->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $url = $this->entryUrl($entry);
                $published = (string) ($entry->pubDate ?? $entry->published ?? $entry->updated ?? '');
                $source = $this->entrySource($entry);

                if ($title === '' || ! $this->isHttpsUrl($url) || $published === ''
                    || ($trustedPublishersOnly && ! $this->isTrustedPublisher($source))) {
                    continue;
                }

                try {
                    $publishedAt = Carbon::parse($published);
                } catch (Throwable) {
                    continue;
                }

                if ($publishedAt->lt(now()->subDays(14))) {
                    continue;
                }

                $summary = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($entry->description ?? $entry->summary ?? ''))) ?? '');
                $headlineContext = $title.' '.$summary;
                if (preg_match('/ship|logistic|freight|cargo|port|container|supply chain|maritime|vessel|carrier|trade|tariff|export|import/i', $headlineContext) !== 1) {
                    continue;
                }

                $items[] = [
                    'title' => Str::limit($title, 500, ''),
                    'summary' => '',
                    'source' => $source,
                    'category' => $this->category($entry, $headlineContext),
                    'url' => $url,
                    'image' => $this->entryImage($entry),
                    'published_at' => $publishedAt,
                ];
            }

            return $items;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }
    }

    private function store(array $item, string $fallbackSource): bool
    {
        $source = Str::limit(trim($item['source']) ?: $fallbackSource, 120, '');
        $normalizedUrl = $this->normalizedUrl($item['url']);
        $titleHash = hash('sha256', Str::lower(Str::squish($item['title'])));
        $urlHash = hash('sha256', $normalizedUrl);
        $existing = ExternalNewsArticle::query()
            ->where('url_hash', $urlHash)
            ->orWhere('title_hash', $titleHash)
            ->first();

        if ($existing) {
            if (! $existing->image_url && $item['image']) {
                $existing->update(['image_url' => $item['image']]);
            }

            return false;
        }

        $article = ExternalNewsArticle::query()->create([
            'title' => $item['title'],
            'summary' => $item['summary'],
            'source' => $source,
            'category' => $item['category'],
            'article_url' => $item['url'],
            'image_url' => $item['image'],
            'url_hash' => $urlHash,
            'title_hash' => $titleHash,
            'published_at' => $item['published_at'],
            'synced_at' => now(),
        ]);

        return $article->wasRecentlyCreated;
    }

    private function needsArticleImage(array $item): bool
    {
        $urlHash = hash('sha256', $this->normalizedUrl($item['url']));
        $titleHash = hash('sha256', Str::lower(Str::squish($item['title'])));

        return ! ExternalNewsArticle::query()
            ->where(function ($query) use ($urlHash, $titleHash): void {
                $query->where('url_hash', $urlHash)->orWhere('title_hash', $titleHash);
            })
            ->whereNotNull('image_url')
            ->exists();
    }

    private function fetchArticleImage(string $articleUrl): ?string
    {
        $cacheKey = $this->articleImageCacheKey($articleUrl);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($articleUrl): ?string {
            $currentUrl = $articleUrl;

            try {
                for ($redirect = 0; $redirect <= 5; $redirect++) {
                    if (! $this->isTrustedArticleUrl($currentUrl)) {
                        return null;
                    }

                    $response = Http::withHeaders(['User-Agent' => 'CargoTraceNewsBot/1.0 (+article metadata preview)'])
                        ->withOptions(['allow_redirects' => false])
                        ->timeout(4)
                        ->get($currentUrl);

                    if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                        $location = trim($response->header('Location'));
                        if ($location === '') {
                            return null;
                        }

                        $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);

                        continue;
                    }

                    if (! $response->successful() || strlen($response->body()) > 2_000_000) {
                        return null;
                    }

                    return $this->extractArticleImage($response->body());
                }
            } catch (Throwable $exception) {
                Log::debug('News article image could not be fetched.', [
                    'url' => $articleUrl,
                    'message' => $exception->getMessage(),
                ]);
            }

            return null;
        });
    }

    private function articleImageCacheKey(string $articleUrl): string
    {
        return 'news:article-image:'.hash('sha256', $articleUrl);
    }

    private function extractArticleImage(string $html): ?string
    {
        $previousErrorSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument;

        try {
            if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return null;
            }

            $xpath = new \DOMXPath($document);
            $images = $xpath->query(
                '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:image" or translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image" or translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image:src"]/@content'
            );

            foreach ($images ?: [] as $image) {
                $url = trim($image->nodeValue ?? '');
                if (str_starts_with($url, '//')) {
                    $url = 'https:'.$url;
                }

                if ($this->isHttpsUrl($url)) {
                    return $url;
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        return null;
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        if ($this->isHttpsUrl($location)) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }

        if (str_starts_with($location, '/')) {
            return 'https://'.parse_url($currentUrl, PHP_URL_HOST).$location;
        }

        return '';
    }

    private function isTrustedArticleUrl(string $url): bool
    {
        if (! $this->isHttpsUrl($url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (config('news.article_hosts', []) as $trustedHost) {
            if ($host === $trustedHost || str_ends_with($host, '.'.$trustedHost)) {
                return true;
            }
        }

        return false;
    }

    private function entryUrl(SimpleXMLElement $entry): string
    {
        if (isset($entry->link)) {
            $link = $entry->link;
            $attributes = $link->attributes();
            if (isset($attributes['href'])) {
                return (string) $attributes['href'];
            }

            return trim((string) $link);
        }

        return '';
    }

    private function entrySource(SimpleXMLElement $entry): string
    {
        if (isset($entry->source)) {
            return trim((string) $entry->source);
        }

        $googleNews = $entry->children('http://search.yahoo.com/mrss/');
        if (isset($googleNews->source)) {
            return trim((string) $googleNews->source);
        }

        return '';
    }

    private function entryImage(SimpleXMLElement $entry): ?string
    {
        $media = $entry->children('http://search.yahoo.com/mrss/');
        foreach (['content', 'thumbnail'] as $element) {
            foreach ($media->{$element} ?? [] as $mediaItem) {
                $attributes = $mediaItem->attributes();
                $url = (string) ($attributes['url'] ?? '');
                if ($this->isHttpsUrl($url)) {
                    return $url;
                }
            }
        }

        if (isset($entry->enclosure)) {
            $enclosure = $entry->enclosure;
            $attributes = $enclosure->attributes();
            $url = (string) ($attributes['url'] ?? '');
            if (str_starts_with((string) ($attributes['type'] ?? ''), 'image/') && $this->isHttpsUrl($url)) {
                return $url;
            }
        }

        return null;
    }

    private function category(SimpleXMLElement $entry, string $text): string
    {
        $category = trim((string) ($entry->category[0] ?? ''));
        if ($category !== '') {
            return Str::limit($category, 80, '');
        }

        return match (true) {
            preg_match('/port|vessel|maritime|ship|container/i', $text) === 1 => 'Maritime',
            preg_match('/supply chain|freight|cargo|logistics|shipping/i', $text) === 1 => 'Logistics',
            preg_match('/trade|tariff|export|import/i', $text) === 1 => 'Trade',
            default => 'World',
        };
    }

    private function isTrustedPublisher(string $source): bool
    {
        return preg_match('/^(reuters|bbc(?: news)?|cnn(?: international)?|al jazeera(?: english)?)(?:\b|$)/i', trim($source)) === 1;
    }

    private function normalizedUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^www\./i', '', $parts['host'] ?? '') ?? '');
        $path = rtrim($parts['path'] ?? '/', '/') ?: '/';

        return $host.$path;
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}

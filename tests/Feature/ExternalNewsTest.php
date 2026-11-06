<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExternalNewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class ExternalNewsTest extends TestCase
{
    use CreatesCargoData, RefreshDatabase;

    public function test_news_sync_imports_and_deduplicates_articles_while_other_feeds_fail_independently(): void
    {
        $publishedAt = now()->subMinutes(4)->toRfc2822String();
        $firstFeed = $this->feedXml([
            $this->feedItem(
                'Major shipping port faces delays',
                'https://www.reuters.com/world/shipping/port-story/',
                $publishedAt,
                'Container vessel and freight news',
                'Reuters',
                'https://images.reuters.com/port.jpg',
            ),
            $this->feedItem(
                'Maritime championship final results announced',
                'https://example.test/athletics/final',
                $publishedAt,
                'A maritime college athletics team won its final match.',
                'Maritime College Athletics',
            ),
        ]);
        $secondFeed = $this->feedXml([
            $this->feedItem(
                'Major shipping port faces delays',
                'https://reuters.com/world/shipping/port-story/?utm_source=rss',
                $publishedAt,
                'Container vessel and freight news',
                'Reuters',
            ),
            $this->feedItem(
                'Global supply chain trade route expands',
                'https://www.bbc.com/news/business/trade-route',
                $publishedAt,
                'New freight route opens',
                'BBC News',
            ),
        ]);

        config(['news.feeds' => [
            ['source' => 'Google News · Logistics', 'trusted_publishers_only' => true, 'url' => 'https://feeds.test/one.xml'],
            ['source' => 'BBC News', 'url' => 'https://feeds.test/two.xml'],
            ['source' => 'Al Jazeera', 'url' => 'https://feeds.test/failed.xml'],
        ]]);
        Http::fake([
            'https://feeds.test/one.xml' => Http::response($firstFeed),
            'https://feeds.test/two.xml' => Http::response($secondFeed),
            'https://feeds.test/failed.xml' => Http::response('Feed unavailable', 503),
        ]);

        $this->artisan('news:sync')
            ->expectsOutput('News sync complete: 2 new articles from 2 successful feeds.')
            ->expectsOutputToContain('Al Jazeera feed failed:')
            ->assertExitCode(0);

        $this->assertDatabaseCount('external_news_articles', 2);
        $this->assertDatabaseHas('external_news_articles', [
            'title' => 'Major shipping port faces delays',
            'source' => 'Reuters',
            'category' => 'Maritime',
            'image_url' => 'https://images.reuters.com/port.jpg',
        ]);
        $this->assertSame(['Google News · Logistics', 'BBC News'], cache('news_last_sync_sources'));
        $this->assertSame(['Al Jazeera'], cache('news_last_sync_failures'));
    }

    public function test_news_sync_uses_the_original_articles_open_graph_image_when_feed_has_no_thumbnail(): void
    {
        config(['news.feeds' => [
            ['source' => 'BBC News', 'url' => 'https://feeds.test/bbc.xml'],
        ]]);
        Http::fake([
            'https://feeds.test/bbc.xml' => Http::response($this->feedXml([
                $this->feedItem(
                    'Shipping route faces disruption after port closure',
                    'https://www.bbc.com/news/articles/shipping-port-closure',
                    now()->subMinutes(3)->toRfc2822String(),
                    'A key cargo route is affected.',
                    'BBC News',
                ),
            ])),
            'https://www.bbc.com/news/articles/shipping-port-closure' => Http::response(
                '<html><head><meta property="og:image" content="https://ichef.bbci.co.uk/news/story.jpg"></head></html>',
            ),
        ]);

        $this->artisan('news:sync')->assertExitCode(0);

        $this->assertDatabaseHas('external_news_articles', [
            'title' => 'Shipping route faces disruption after port closure',
            'image_url' => 'https://ichef.bbci.co.uk/news/story.jpg',
        ]);
    }

    public function test_news_page_and_cursor_endpoint_provide_infinite_external_articles(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        foreach (range(0, 12) as $index) {
            $this->createArticle($index);
        }

        $this->actingAs($customer);
        cache()->put('news_last_sync_at', now()->toIso8601String());
        cache()->put('news_last_sync_sources', ['Reuters']);
        $newsPage = $this->get('/news');
        $newsPage
            ->assertOk()
            ->assertSee('Global Logistics Headlines')
            ->assertSee('LIVE')
            ->assertSee('External headline 0')
            ->assertSee('Reuters')
            ->assertSee('Logistics')
            ->assertSee('Read original article')
            ->assertDontSee('Ilustrasi')
            ->assertDontSee('logistics-network.svg');
        $this->assertStringContainsString('/news/articles?cursor=', $newsPage->getContent());

        $firstPage = $this->getJson('/news/articles')->assertOk();
        $this->assertSame(12, substr_count($firstPage->json('html'), 'external-news-card'));
        $this->assertNotNull($firstPage->json('next'));

        $nextPageUrl = parse_url($firstPage->json('next'), PHP_URL_PATH).'?'.parse_url($firstPage->json('next'), PHP_URL_QUERY);
        $this->getJson($nextPageUrl)
            ->assertOk()
            ->assertJsonPath('next', null)
            ->assertSee('External headline 12');
    }

    public function test_latest_news_endpoint_only_returns_articles_added_after_the_client_cursor(): void
    {
        $customer = $this->makeUser(UserRole::Customer);
        $existing = $this->createArticle(1);
        $newArticle = $this->createArticle(2);
        cache()->put('news_last_sync_at', now()->toIso8601String());
        cache()->put('news_last_sync_sources', ['BBC News']);

        $this->actingAs($customer)
            ->getJson('/news/latest?after_id='.$existing->id)
            ->assertOk()
            ->assertJsonPath('is_live', true)
            ->assertJsonPath('latest_id', $newArticle->id)
            ->assertSee('External headline 2');
    }

    private function createArticle(int $index): ExternalNewsArticle
    {
        return ExternalNewsArticle::query()->create([
            'title' => 'External headline '.$index,
            'summary' => null,
            'source' => 'Reuters',
            'category' => 'Logistics',
            'article_url' => 'https://reuters.com/story/'.$index,
            'image_url' => null,
            'url_hash' => hash('sha256', 'url-'.$index),
            'title_hash' => hash('sha256', 'title-'.$index),
            'published_at' => now()->subMinutes($index),
            'synced_at' => now(),
        ]);
    }

    private function feedXml(array $items): string
    {
        return '<?xml version="1.0"?><rss xmlns:media="http://search.yahoo.com/mrss/"><channel>'.implode('', $items).'</channel></rss>';
    }

    private function feedItem(
        string $title,
        string $url,
        string $publishedAt,
        string $description,
        string $source,
        ?string $image = null,
    ): string {
        $imageTag = $image ? '<media:thumbnail url="'.$image.'"/>' : '';

        return '<item><title>'.$title.'</title><link>'.$url.'</link><description>'.$description.'</description><pubDate>'.$publishedAt.'</pubDate><source>'.$source.'</source>'.$imageTag.'</item>';
    }
}

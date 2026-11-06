<?php

return [
    'feeds' => [
        [
            'source' => 'Google News · Logistics',
            'trusted_publishers_only' => true,
            'url' => 'https://news.google.com/rss/search?q=shipping+OR+logistics+OR+maritime+OR+%22supply+chain%22+when%3A7d&hl=en-US&gl=US&ceid=US%3Aen',
        ],
        [
            'source' => 'Reuters · Google News',
            'url' => 'https://news.google.com/rss/search?q=site%3Areuters.com+%28shipping+OR+logistics+OR+maritime+OR+%22supply+chain%22%29+when%3A7d&hl=en-US&gl=US&ceid=US%3Aen',
        ],
        [
            'source' => 'CNN · Google News',
            'url' => 'https://news.google.com/rss/search?q=site%3Acnn.com+%28shipping+OR+logistics+OR+maritime+OR+%22supply+chain%22%29+when%3A7d&hl=en-US&gl=US&ceid=US%3Aen',
        ],
        [
            'source' => 'BBC News',
            'url' => 'https://feeds.bbci.co.uk/news/business/rss.xml',
        ],
        [
            'source' => 'Al Jazeera',
            'url' => 'https://www.aljazeera.com/xml/rss/all.xml',
        ],
    ],
    'refresh_minutes' => 2,
    'page_size' => 12,
    'article_hosts' => [
        'news.google.com',
        'reuters.com',
        'bbc.com',
        'bbc.co.uk',
        'cnn.com',
        'aljazeera.com',
    ],
];

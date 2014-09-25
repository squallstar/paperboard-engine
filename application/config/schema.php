<?php

$config['db_schema'] = [

  'drop_database' => false,
  'insert_data' => false,

  'collections' => [

    'users' => [
      'indexes' => [
        [
          ['email' => 1], ['unique' => true]
        ],
        [
          ['password' => 1]
        ],
        [
          ['auth_token' => 1], ['unique' => true]
        ],
        [
          ['connected_accounts.id' => 1]
        ],
        [
          ['connected_accounts.following.updated_at' => 1]
        ],
        [
          ['connected_accounts.access_token.user_id' => 1]
        ]
      ]
    ],

    'categories' => [
      'indexes' => [
        [
          ['id' => 1], ['unique' => true]
        ],
        [
          ['slug' => 1], ['unique' => true]
        ]
      ],
      'data' => [
        ['id' => newid('c'), 'name' => 'Featured', 'slug' => 'top-picks', 'collection_count' => 0],
        ['id' => newid('c'), 'name' => 'Tech', 'slug' => 'tech', 'collection_count' => 0],
        ['id' => newid('c'), 'name' => 'Business', 'slug' => 'business', 'collection_count' => 0],
        ['id' => newid('c'), 'name' => 'Sport', 'slug' => 'sport', 'collection_count' => 0]
      ]
    ],

    'collections' => [
      'indexes' => [
        [
          ['id' => 1], ['unique' => true]
        ],
        [
          ['private_id' => 1], ['unique' => true]
        ],
        [
          ['user.id' => 1]
        ],
        [
          ['position' => 1]
        ],
        [
          ['sources' => 1]
        ],
        [
          ['publicly_visible' => 1]
        ],
        [
          ['category.slug' => 1]
        ],
        [
          ['followers.id' => 1]
        ],
        [
          ['tags' => 1]
        ],
        [
          ['featured' => 1]
        ]
      ]
    ],

    'user_categories' => [
      'indexes' => [
        [
          ['id' => 1], ['unique' => true]
        ],
        [
          ['user_id' => 1]
        ],
        [
          ['text' => 1]
        ],
        [
          ['source_uri' => 1]
        ]
      ]
    ],

    'category_children' => [
      'indexes' => [
        [
          ['id' => 1]
        ],
        [
          ['feed_id' => 1, 'category_id' => 1], ['unique' => true]
        ],
        [
          ['external_key' => 1]
        ],
        [
          ['category_id' => 1]
        ],
        [
          ['source_uri' => 1]
        ],
        [
          ['user_id' => 1]
        ]
      ]
    ],

    'feeds' => [
      'indexes' => [
        [
          ['url' => 1, 'type' => 1], ['unique' => true]
        ],
        [
          ['type' => 1, 'processed_at' => 1]
        ],
        [
          ['external_id' => 1]
        ],
        [
          ['failed_count' => 1]
        ],
        [
          ['title' => 'text', 'url' => 'text']
        ]
      ]
    ],

    'counters' => [
      'data' => [
        ['_id' => 'user_id', 'seq' => 0],
        ['_id' => 'collection_id', 'seq' => 0]
      ]
    ],

    'jobs' => [
      'indexes' => [
        [
          ['type' => 1]
        ],
        [
          ['added_at' => -1]
        ]
      ]
    ],

    'articles' => [
      'indexes' => [
        [
          ['id' => 1], ['unique' => true]
        ],
        [
          ['fetched' => 1]
        ],
        [
          ['fetched_at' => 1]
        ],
        [
          ['type' => 1]
        ],
        [
          ['has_image' => 1, 'images_processed' => 1]
        ],
        [
          ['published_at' => -1]
        ],
        [
          ['processed_at' => 1]
        ],
        [
          ['source' => 1, 'published_at' => -1]
        ],
        [
          ['name' => 'text', 'description' => 'text', 'source' => 1, 'published_at' => -1]
        ]
      ]
    ],

    'parsers' => [
      'indexes' => [
        [
          ['host' => 1], ['unique' => true]
        ]
      ]
    ]
  ]
];